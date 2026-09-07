<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The HiveLog dashboard — the module's landing page at /hivelog.
 *
 * See ADR-0057 and task 0056. Criterion 2 builds the shell: the header
 * strip (current ISO week + the CBR summary line, moved here from
 * ApiaryListBuilder) and the first-run welcome state. The operational
 * widgets — needs-attention queue, stat tiles, upcoming, recent activity,
 * apiaries summary — are added by criteria 3–6; the stat-tile SDC and the
 * hivelog/dashboard CSS library land here so those criteria only add
 * markup.
 */
class DashboardController extends ControllerBase {

  /**
   * Constructs a DashboardController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
  ) {
    // $entityTypeManager / $currentUser are untyped properties inherited
    // from ControllerBase; assign them rather than redeclaring them.
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Renders the dashboard landing page.
   */
  public function view(): array {
    $cache = new CacheableMetadata();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard']],
      '#attached' => ['library' => ['hivelog/dashboard']],
      'header' => $this->buildHeader($cache) + ['#weight' => -10],
    ];

    $apiaries = $this->viewableApiaries();
    $cache->addCacheTags($this->entityTypeManager->getDefinition('apiary')->getListCacheTags());

    if (!$apiaries) {
      // No visible apiaries → the whole dashboard is the welcome card.
      $build['welcome'] = $this->buildWelcome() + ['#weight' => 0];
    }
    else {
      $build['needs_attention'] = $this->buildNeedsAttention($cache, $apiaries) + ['#weight' => 0];
      // Interim placeholder for the widgets still to come (criteria 4–6).
      $build['body'] = $this->buildInterimBody() + ['#weight' => 100];
    }

    // - user: the CBR line is per-user (not per-permission), and it also
    //   covers the per-entity access filtering the needs-attention roll-up
    //   does.
    // - max-age: the header prints the current ISO week and the
    //   needs-attention chips are week-relative, so the render must not
    //   outlive the week boundary — matches ApiaryController /
    //   HiveController's calendar sections.
    $cache
      ->addCacheContexts(['user'])
      ->setCacheMaxAge($this->secondsUntilNextIsoWeek());
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Loads every apiary the current user may view, keyed by id.
   *
   * @return \Drupal\hivelog\Entity\Apiary[]
   *   Viewable apiaries keyed by entity id.
   */
  protected function viewableApiaries(): array {
    $storage = $this->entityTypeManager->getStorage('apiary');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $apiaries = $ids ? $storage->loadMultiple($ids) : [];
    return array_filter(
      $apiaries,
      fn($apiary) => $apiary->access('view', $this->currentUser)
    );
  }

  /**
   * Builds the header strip: the ISO-week badge and the CBR summary line.
   */
  protected function buildHeader(CacheableMetadata $cache): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard__header']],
      'week' => [
        '#type' => 'inline_template',
        '#template' => '<p class="hivelog-dashboard__week">{% trans %}Week <strong>{{ week }}</strong> · {{ year }}{% endtrans %}</p>',
        '#context' => [
          'week' => (int) date('W'),
          'year' => (int) date('Y'),
        ],
      ],
      'cbr' => $this->buildCbrSummary($cache),
    ];
  }

  /**
   * Builds the current user's CBR summary line (moved from ApiaryListBuilder).
   */
  protected function buildCbrSummary(CacheableMetadata $cache): array {
    $user = NULL;
    if ($this->currentUser->isAuthenticated()) {
      /** @var \Drupal\user\UserInterface|null $user */
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    }
    if ($user) {
      $cache->addCacheableDependency($user);
    }

    $cbr = $this->extractCbr($user);
    if ($cbr !== '') {
      $message = ['#markup' => $this->t('Your CBR number: @cbr', ['@cbr' => $cbr])];
    }
    elseif ($user) {
      $message = [
        '#type' => 'inline_template',
        '#template' => '{% trans %}You have not set a CBR number yet. <a href="{{ url }}">Update your profile</a> to add one.{% endtrans %}',
        '#context' => ['url' => $user->toUrl('edit-form')->toString()],
      ];
    }
    else {
      $message = ['#markup' => $this->t('Sign in to record your CBR number.')];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-cbr-summary']],
      'message' => $message,
    ];
  }

  /**
   * Builds the first-run welcome card, shown when the user has no apiaries.
   */
  protected function buildWelcome(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard__welcome']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Welcome to HiveLog'),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Start by adding an apiary — a location where you keep hives. Creating one seeds a 31-entry seasonal calendar you can adjust, then you can add hives, queens and inspections under it.'),
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-dashboard__welcome-actions']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add your first apiary'),
            'url' => Url::fromRoute('entity.apiary.add_form')->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the interim body shown until the real widgets land (criteria 3–6).
   */
  protected function buildInterimBody(): array {
    return [
      '#type' => 'container',
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Stat tiles, an upcoming view and recent activity are coming next. For now, the rest lives in the menu.'),
      ],
      'apiaries' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Go to Apiaries'),
          'url' => Url::fromRoute('entity.apiary.collection')->toString(),
        ],
      ],
    ];
  }

  /**
   * Builds the "Needs attention" panel: overdue / due actions + low stock.
   *
   * Generalises ApiaryController::buildApiaryCalendarChecklist() and
   * HiveController::buildCalendarChecklist() across every visible apiary
   * and hive: unreported enabled CalendarActions for the current year
   * whose timing is overdue or due-this-week, plus low-stock inventory
   * items. Rows are ordered overdue → due-this-week → low stock.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row entity dependencies.
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   The viewable apiaries, keyed by id.
   */
  protected function buildNeedsAttention(CacheableMetadata $cache, array $apiaries): array {
    $current_week = (int) date('W');
    $year = (int) date('Y');

    $alerts = array_merge(
      $this->collectSeasonalAlerts($apiaries, $year, $current_week, $cache),
      $this->collectLowStockAlerts($apiaries, $cache),
    );
    usort($alerts, fn($a, $b) => $a['sort'] <=> $b['sort']);

    $overdue = count(array_filter($alerts, fn($a) => $a['severity'] === 'critical'));

    $panel = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-attention']],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-attention__header']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['hivelog-attention__heading']],
          '#value' => $this->t('Needs attention'),
        ],
      ],
    ];

    if (!$alerts) {
      $panel['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['hivelog-attention__empty']],
        '#value' => $this->t('All caught up for week @week.', ['@week' => $current_week]),
      ];
      return $panel;
    }

    $panel['header']['count'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['class' => ['hivelog-attention__count']],
      '#value' => $overdue
        ? $this->t('@total to review · @overdue overdue', ['@total' => count($alerts), '@overdue' => $overdue])
        : $this->formatPlural(count($alerts), '@count item to review', '@count items to review'),
    ];

    foreach (array_values($alerts) as $i => $alert) {
      $panel['row_' . $i] = $this->buildAttentionRow($alert);
    }

    return $panel;
  }

  /**
   * Builds one "Needs attention" row from a collected alert.
   */
  protected function buildAttentionRow(array $alert): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'hivelog-attention__row',
          'hivelog-attention__row--' . $alert['severity'],
        ],
      ],
      'chip' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => [
            'hivelog-attention__chip',
            'hivelog-attention__chip--' . $alert['severity'],
          ],
        ],
        '#value' => $alert['chip'],
      ],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-attention__body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['hivelog-attention__row-title']],
          '#value' => $alert['title'],
        ],
        'context' => $alert['context'],
      ],
      'action' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $alert['action_label'],
          'url' => $alert['action_url'],
          'variant' => 'primary',
          'extra_classes' => 'hivelog-attention__action',
        ],
      ],
    ];
  }

  /**
   * Collects overdue / due-this-week seasonal-calendar alerts.
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
   * @return array[]
   *   Alert descriptors (see buildAttentionRow()).
   */
  protected function collectSeasonalAlerts(array $apiaries, int $year, int $current_week, CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);

    $action_ids = $etm->getStorage('calendar_action')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('enabled', TRUE)
      ->sort('week_start', 'ASC')
      ->execute();
    if (!$action_ids) {
      return [];
    }
    $actions = array_filter(
      $etm->getStorage('calendar_action')->loadMultiple($action_ids),
      fn($action) => $action->access('view')
    );
    if (!$actions) {
      return [];
    }

    $cache->addCacheTags($etm->getDefinition('calendar_action')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('apiary_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive')->getListCacheTags());

    $apiary_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'apiary');
    $hive_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'hive');

    $alerts = [];

    if ($apiary_scoped) {
      $logs = $this->indexApiaryLogs($apiary_ids, array_keys($apiary_scoped), $year);
      foreach ($apiary_scoped as $action) {
        $log = $logs[$action->id()] ?? NULL;
        if ($log && $log->get('status')->value !== 'pending') {
          continue;
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
          'context' => $this->attentionContext($apiary, NULL, $this->weekWindow($action)),
          'action_label' => $this->t('Report done'),
          'action_url' => Url::fromRoute('hivelog.apiary_action_log.add', [
            'apiary' => $apiary->id(),
            'calendar_action' => $action->id(),
          ], ['query' => ['status' => 'done']])->toString(),
          'sort' => $timing['sort'],
        ];
      }
    }

    if ($hive_scoped) {
      $hive_ids = $etm->getStorage('hive')->getQuery()
        ->accessCheck(TRUE)
        ->condition('apiary', $apiary_ids, 'IN')
        ->execute();
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
              'context' => $this->attentionContext($apiaries[$hive_apiary_id], $hive, $this->weekWindow($action)),
              'action_label' => $this->t('Report done'),
              'action_url' => Url::fromRoute('hivelog.hive_action_log.add', [
                'hive' => $hive->id(),
                'calendar_action' => $action->id(),
              ], ['query' => ['status' => 'done']])->toString(),
              'sort' => $timing['sort'],
            ];
          }
        }
      }
    }

    return $alerts;
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
   *   Alert descriptors (see buildAttentionRow()).
   */
  protected function collectLowStockAlerts(array $apiaries, CacheableMetadata $cache): array {
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
        'context' => $this->attentionContext($apiary, NULL, (string) $detail),
        'action_label' => $this->t('Add purchase'),
        'action_url' => Url::fromRoute('hivelog.inventory_purchase.add', ['apiary' => $apiary->id()])->toString(),
        'sort' => [2, mb_strtolower((string) $item->label())],
      ];
    }

    return $alerts;
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
  protected function indexHiveLogs(array $hive_ids, array $action_ids, int $year): array {
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
    $week_end_raw = $action->get('week_end')->value;
    $week_end = ($week_end_raw !== NULL && $week_end_raw !== '') ? (int) $week_end_raw : $week_start;

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
    $end_raw = $action->get('week_end')->value;
    $end = ($end_raw !== NULL && $end_raw !== '') ? (int) $end_raw : $start;
    return $end !== $start
      ? (string) $this->t('wk @start–@end', ['@start' => $start, '@end' => $end])
      : (string) $this->t('wk @start', ['@start' => $start]);
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

  /**
   * Extracts a trimmed CBR number from a user, if any.
   */
  protected function extractCbr(?UserInterface $user): string {
    if (!$user || !$user->hasField('cbr_number')) {
      return '';
    }
    return trim((string) $user->get('cbr_number')->value);
  }

  /**
   * Seconds remaining until the ISO week changes (next Monday, midnight).
   *
   * Bounds the cache max-age for the header's current-week badge so a
   * cached render never shows a stale week after the boundary passes.
   * Mirrors ApiaryController / HiveController.
   *
   * @return int
   *   Seconds until the next ISO week boundary.
   */
  protected function secondsUntilNextIsoWeek(): int {
    $now = new \DateTimeImmutable('now');
    $next_boundary = new \DateTimeImmutable('next monday midnight');
    return max(0, $next_boundary->getTimestamp() - $now->getTimestamp());
  }

}
