<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;

/**
 * Collects the "Needs attention" alerts shared by the dashboard and the API.
 *
 * Moved out of `DashboardController` (task 0202) so the mobile API serves the
 * very same alerts the dashboard shows, from one implementation: the
 * seasonal-calendar pass, low-stock inventory, and whatever optional
 * submodules contribute through hook_hivelog_needs_attention_alerts().
 *
 * Each row is the descriptor the dashboard renders (`severity`, `chip`,
 * `title`, `context`, `action*`, `sort`), plus two optional data keys that
 * carry the same information without markup, for a caller that is not a web
 * page: `kind` (a short machine name) and `subject` (`apiary`, `hive`,
 * `detail`, and for seasonal rows the `calendar_action`). See
 * hook_hivelog_needs_attention_alerts() in hivelog.api.php.
 */
class HivelogAlertCollector {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Collects every alert for the given apiaries, most urgent first.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param int $year
   *   The year to check seasonal reporting against.
   * @param int $current_week
   *   The current ISO week number.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects cache tags and dependencies.
   *
   * @return array{alerts: array[], seasonal: array, low_stock: array[]}
   *   `alerts` is the merged, sorted list; `seasonal` and `low_stock` are the
   *   individual passes, for the dashboard's stat tiles.
   */
  public function collect(array $apiaries, int $year, int $current_week, CacheableMetadata $cache): array {
    $seasonal = $this->collectSeasonalAlerts($apiaries, $year, $current_week, $cache);
    $low_stock = $this->collectLowStockAlerts($apiaries, $cache);
    $contributed = $this->collectSensorAlerts($apiaries, $cache);

    $alerts = array_merge($seasonal['alerts'], $low_stock, $contributed);
    usort($alerts, fn($a, $b) => $a['sort'] <=> $b['sort']);

    return ['alerts' => $alerts, 'seasonal' => $seasonal, 'low_stock' => $low_stock];
  }

  /**
   * Collects seasonal-calendar alerts and the open-task tally in one pass.
   *
   * Walks every enabled CalendarAction across the visible apiaries (and,
   * for hive-scoped actions, every visible hive), skipping any already
   * reported done / ignored for $year. Each remaining "open" action is
   * counted (with an overdue sub-count); the subset whose window is
   * overdue or covers the current week additionally becomes an alert row
   * ordered overdue → due-this-week.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param int $year
   *   The year to check reporting against (the current year).
   * @param int $current_week
   *   The current ISO week number.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row dependencies.
   *
   * @return array{alerts: array[], open_total: int, open_overdue: int}
   *   `alerts` are the overdue / due row descriptors (see
   *   buildAttentionRow()); `open_total` / `open_overdue` feed the
   *   "Open seasonal tasks" stat tile.
   */
  public function collectSeasonalAlerts(array $apiaries, int $year, int $current_week, CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);
    $empty = ['alerts' => [], 'open_total' => 0, 'open_overdue' => 0];

    $action_ids = $etm->getStorage('calendar_action')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('enabled', TRUE)
      ->sort('week_start', 'ASC')
      ->execute();
    if (!$action_ids) {
      return $empty;
    }
    /** @var \Drupal\hivelog\Entity\CalendarAction[] $actions */
    $actions = array_filter(
      $etm->getStorage('calendar_action')->loadMultiple($action_ids),
      fn($action) => $action->access('view')
    );
    if (!$actions) {
      return $empty;
    }

    $cache->addCacheTags($etm->getDefinition('calendar_action')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('apiary_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive')->getListCacheTags());

    $apiary_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'apiary');
    $hive_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'hive');

    $alerts = [];
    $open_total = 0;
    $open_overdue = 0;

    if ($apiary_scoped) {
      $logs = $this->indexApiaryLogs($apiary_ids, array_keys($apiary_scoped), $year);
      foreach ($apiary_scoped as $action) {
        $log = $logs[$action->id()] ?? NULL;
        if ($log && $log->get('status')->value !== 'pending') {
          continue;
        }
        $open_total++;
        if ($current_week > $this->effectiveWeekEnd($action)) {
          $open_overdue++;
        }
        $timing = $this->attentionTiming($action, $current_week);
        if (!$timing) {
          continue;
        }
        $cache->addCacheableDependency($action);
        if ($log) {
          $cache->addCacheableDependency($log);
        }
        $apiary = $apiaries[(int) $action->get('apiary')->target_id];
        $alerts[] = [
          'severity' => $timing['severity'],
          'chip' => $timing['chip'],
          'title' => $action->label(),
          'kind' => 'calendar_action',
          'subject' => [
            'apiary' => $apiary,
            'hive' => NULL,
            'detail' => $this->weekWindow($action),
            'calendar_action' => $action,
          ],
          'context' => $this->attentionContext($apiary, NULL, $this->weekWindow($action)),
          'actions' => $this->reportButtons('hivelog.apiary_action_log.add', [
            'apiary' => $apiary->id(),
            'calendar_action' => $action->id(),
          ]),
          'sort' => $timing['sort'],
        ];
      }
    }

    if ($hive_scoped) {
      $hive_ids = $etm->getStorage('hive')->getQuery()
        ->accessCheck(TRUE)
        ->condition('apiary', $apiary_ids, 'IN')
        ->execute();
      /** @var \Drupal\hivelog\Entity\Hive[] $hives */
      $hives = $hive_ids ? array_filter(
        $etm->getStorage('hive')->loadMultiple($hive_ids),
        fn($hive) => $hive->access('view')
      ) : [];

      if ($hives) {
        $logs = $this->indexHiveLogs(array_keys($hives), array_keys($hive_scoped), $year);
        foreach ($hives as $hive) {
          $hive_apiary_id = (int) $hive->get('apiary')->target_id;
          foreach ($hive_scoped as $action) {
            if ((int) $action->get('apiary')->target_id !== $hive_apiary_id) {
              continue;
            }
            $log = $logs[$hive->id()][$action->id()] ?? NULL;
            if ($log && $log->get('status')->value !== 'pending') {
              continue;
            }
            $open_total++;
            if ($current_week > $this->effectiveWeekEnd($action)) {
              $open_overdue++;
            }
            $timing = $this->attentionTiming($action, $current_week);
            if (!$timing) {
              continue;
            }
            $cache->addCacheableDependency($action);
            $cache->addCacheableDependency($hive);
            if ($log) {
              $cache->addCacheableDependency($log);
            }
            $alerts[] = [
              'severity' => $timing['severity'],
              'chip' => $timing['chip'],
              'title' => $action->label(),
              'kind' => 'calendar_action',
              'subject' => [
                'apiary' => $apiaries[$hive_apiary_id],
                'hive' => $hive,
                'detail' => $this->weekWindow($action),
                'calendar_action' => $action,
              ],
              'context' => $this->attentionContext($apiaries[$hive_apiary_id], $hive, $this->weekWindow($action)),
              'actions' => $this->reportButtons('hivelog.hive_action_log.add', [
                'hive' => $hive->id(),
                'calendar_action' => $action->id(),
              ]),
              'sort' => $timing['sort'],
            ];
          }
        }
      }
    }

    return [
      'alerts' => $alerts,
      'open_total' => $open_total,
      'open_overdue' => $open_overdue,
    ];
  }

  /**
   * Collects low-stock inventory alerts across the given apiaries.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row dependencies.
   *
   * @return array[]
   *   Alert row descriptors (see buildAttentionRow()).
   */
  public function collectLowStockAlerts(array $apiaries, CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);

    $ids = $etm->getStorage('inventory_item')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('status', 'active')
      ->exists('low_stock_threshold')
      ->sort('name', 'ASC')
      ->execute();
    if (!$ids) {
      return [];
    }
    /** @var \Drupal\hivelog\Entity\InventoryItem[] $items */
    $items = array_filter(
      $etm->getStorage('inventory_item')->loadMultiple($ids),
      fn($item) => $item->access('view')
    );
    if (!$items) {
      return [];
    }

    // Stock on hand is derived from purchases minus usage, so a purchase
    // or usage save that does not bump the item's own cache tag must still
    // invalidate this panel — mirrors ApiaryController's inventory table.
    $cache->addCacheTags($etm->getDefinition('inventory_item')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('inventory_purchase')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('inventory_usage')->getListCacheTags());

    $alerts = [];
    foreach ($items as $item) {
      if (!$item->isLowStock()) {
        continue;
      }
      $cache->addCacheableDependency($item);
      $apiary = $apiaries[(int) $item->get('apiary')->target_id];
      $stock = $item->getStockOnHand();
      $detail = $this->t('@stock @unit on hand · reorder at @threshold', [
        '@stock' => $this->trimDecimal($stock ?? 0.0),
        '@unit' => (string) $item->get('unit')->value,
        '@threshold' => $this->trimDecimal((float) $item->get('low_stock_threshold')->value),
      ]);
      $alerts[] = [
        'severity' => 'warning',
        'chip' => $this->t('Low stock'),
        'title' => $item->label(),
        'kind' => 'low_stock',
        'subject' => ['apiary' => $apiary, 'hive' => NULL, 'detail' => (string) $detail],
        'context' => $this->attentionContext($apiary, NULL, (string) $detail),
        'action_label' => $this->t('Add purchase'),
        'action_url' => Url::fromRoute('hivelog.inventory_purchase.add', ['apiary' => $apiary->id()])->toString(),
        'sort' => [2, mb_strtolower((string) $item->label())],
      ];
    }

    return $alerts;
  }

  /**
   * Collects sensor-driven alert rows contributed by optional submodules.
   *
   * `hivelog` core has no sensor entities of its own — this is a thin
   * wrapper around hook_hivelog_needs_attention_alerts() (see
   * hivelog.api.php and
   * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md),
   * so `nanoprobe`'s device-offline/weight-drop/temperature rules can
   * feed this queue without core depending on that module. Returns an
   * empty array when no module implements the hook (e.g. `nanoprobe`
   * isn't installed).
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Every apiary the current user may view, keyed by entity id.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheability collector, passed through to implementations.
   *
   * @return array[]
   *   Alert rows, shaped exactly like collectLowStockAlerts()'s own rows.
   */
  public function collectSensorAlerts(array $apiaries, CacheableMetadata $cache): array {
    return $this->moduleHandler->invokeAll('hivelog_needs_attention_alerts', [$apiaries, $cache]);
  }

  /**
   * Loads apiary action logs for the given apiaries/year, indexed by action.
   *
   * @param int[] $apiary_ids
   *   Apiary ids to match.
   * @param int[] $action_ids
   *   Calendar-action ids to match.
   * @param int $year
   *   The reporting year.
   *
   * @return \Drupal\hivelog\Entity\ApiaryActionLog[]
   *   The most-recently-changed log per calendar-action id.
   */
  protected function indexApiaryLogs(array $apiary_ids, array $action_ids, int $year): array {
    $storage = $this->entityTypeManager->getStorage('apiary_action_log');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('calendar_action', $action_ids, 'IN')
      ->condition('year', $year)
      ->sort('changed', 'ASC')
      ->execute();

    $out = [];
    // Ascending by `changed`, so later iterations (more recent) win.
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $log) {
      /** @var \Drupal\hivelog\Entity\ApiaryActionLog $log */
      $out[$log->get('calendar_action')->target_id] = $log;
    }
    return $out;
  }

  /**
   * Loads hive action logs, indexed by hive id then calendar-action id.
   *
   * @param int[] $hive_ids
   *   Hive ids to match.
   * @param int[] $action_ids
   *   Calendar-action ids to match.
   * @param int $year
   *   The reporting year.
   *
   * @return array<int|string, \Drupal\hivelog\Entity\HiveActionLog[]>
   *   $logs[hive_id][calendar_action_id] = most-recently-changed log.
   */
  public function indexHiveLogs(array $hive_ids, array $action_ids, int $year): array {
    $storage = $this->entityTypeManager->getStorage('hive_action_log');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive_ids, 'IN')
      ->condition('calendar_action', $action_ids, 'IN')
      ->condition('year', $year)
      ->sort('changed', 'ASC')
      ->execute();

    $out = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $log) {
      /** @var \Drupal\hivelog\Entity\HiveActionLog $log */
      $out[$log->get('hive')->target_id][$log->get('calendar_action')->target_id] = $log;
    }
    return $out;
  }

  /**
   * Classifies an unreported action's timing against the current week.
   *
   * Only "overdue" and "due this week" count as needing attention;
   * "upcoming" returns NULL. `CalendarAction` windows never wrap the year
   * boundary, so plain integer comparison is enough.
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $action
   *   The calendar action.
   * @param int $current_week
   *   The current ISO week number.
   *
   * @return array{severity: string, chip: \Drupal\Core\StringTranslation\TranslatableMarkup, sort: int[]}|null
   *   The alert's severity, status chip and sort key, or NULL if upcoming.
   */
  protected function attentionTiming(CalendarAction $action, int $current_week): ?array {
    $week_start = (int) $action->get('week_start')->value;
    $week_end = $this->effectiveWeekEnd($action);

    if ($current_week > $week_end) {
      $over = $current_week - $week_end;
      return [
        'severity' => 'critical',
        'chip' => $this->formatPlural($over, 'Overdue 1 wk', 'Overdue @count wk'),
        'sort' => [0, -$over],
      ];
    }
    if ($current_week >= $week_start) {
      return [
        'severity' => 'warning',
        'chip' => $this->t('Due wk @week', ['@week' => $current_week]),
        'sort' => [1, $week_start],
      ];
    }
    return NULL;
  }

  /**
   * Formats a calendar action's planned week window, e.g. "wk 33–37".
   */
  protected function weekWindow(CalendarAction $action): string {
    $start = (int) $action->get('week_start')->value;
    $end = $this->effectiveWeekEnd($action);
    return $end !== $start
      ? (string) $this->t('wk @start–@end', ['@start' => $start, '@end' => $end])
      : (string) $this->t('wk @start', ['@start' => $start]);
  }

  /**
   * A calendar action's end week, falling back to its start week.
   */
  protected function effectiveWeekEnd(CalendarAction $action): int {
    $raw = $action->get('week_end')->value;
    return ($raw !== NULL && $raw !== '') ? (int) $raw : (int) $action->get('week_start')->value;
  }

  /**
   * The Done / Ignored button pair for an unreported calendar action.
   *
   * @param string $route
   *   The action-log add route (apiary- or hive-scoped).
   * @param array $params
   *   Route parameters: the parent (apiary or hive) plus calendar_action.
   *
   * @return array[]
   *   Two hivelog:button descriptors — "Done" (primary) and "Ignored".
   */
  protected function reportButtons(string $route, array $params): array {
    return [
      [
        'label' => (string) $this->t('Done'),
        'url' => Url::fromRoute($route, $params, ['query' => ['status' => 'done']])->toString(),
        'variant' => 'primary',
      ],
      [
        'label' => (string) $this->t('Ignored'),
        'url' => Url::fromRoute($route, $params, ['query' => ['status' => 'ignored']])->toString(),
      ],
    ];
  }

  /**
   * Builds a row's context line: apiary (and hive) links plus a detail string.
   */
  protected function attentionContext(Apiary $apiary, ?Hive $hive, string $detail): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<span class="hivelog-attention__ctx">{{ apiary }}{% if hive %} › {{ hive }}{% endif %} · {{ detail }}</span>',
      '#context' => [
        'apiary' => $apiary->toLink()->toRenderable(),
        'hive' => $hive ? $hive->toLink()->toRenderable() : NULL,
        'detail' => $detail,
      ],
    ];
  }

  /**
   * Trims trailing zeros from a decimal, e.g. 2.000 → "2", 1.500 → "1.5".
   */
  protected function trimDecimal(float $value): string {
    return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
  }

}
