<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
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
 * See ADR-0057 and task 0056. view() renders, in order: the header strip
 * (current ISO week + the CBR summary line, moved here from
 * ApiaryListBuilder), and then either the first-run welcome card (no
 * visible apiaries) or the operational widgets — the "Needs attention"
 * queue, the six stat tiles, the "Upcoming" / "Recent activity" split,
 * and the closing "Apiaries" summary. One seasonal pass
 * (collectSeasonalAlerts()) feeds the queue, the "Open seasonal tasks"
 * tile and the apiaries section; low stock (collectLowStockAlerts())
 * likewise.
 */
class DashboardController extends ControllerBase {

  /**
   * The class resolver, used to build InventoryReportController on demand.
   */
  protected ClassResolverInterface $classResolver;

  /**
   * The date formatter, for the "Recent activity" timestamps.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a DashboardController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    ClassResolverInterface $class_resolver,
    DateFormatterInterface $date_formatter,
  ) {
    // $entityTypeManager / $currentUser are untyped properties inherited
    // from ControllerBase; assign them rather than redeclaring them.
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->classResolver = $class_resolver;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('class_resolver'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Page title: a hexagon mark plus the "HiveLog" wordmark.
   *
   * Returned as safe markup so the SVG survives into the theme's <h1>;
   * the "Apiary & hive logbook" subtitle is a separate element rendered
   * by view() just below it.
   */
  public function title(): Markup {
    return Markup::create(
      '<span class="hivelog-masthead__mark" aria-hidden="true">'
      . '<svg viewBox="0 0 24 24" width="24" height="24">'
      . '<path fill="currentColor" opacity=".16" d="M12 2.6l8.15 4.7v9.4L12 21.4 3.85 16.7V7.3z"/>'
      . '<path fill="none" stroke="currentColor" stroke-width="1.6" d="M12 4.1l6.85 3.95v7.9L12 19.9l-6.85-3.95v-7.9z"/>'
      . '</svg></span>'
      . '<span class="hivelog-masthead__word">' . $this->t('HiveLog') . '</span>'
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
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['hivelog-masthead__sub']],
        '#value' => $this->t('Apiary and hive logbook'),
        '#weight' => -30,
      ],
      'header' => $this->buildHeader($cache) + ['#weight' => -10],
    ];

    $apiaries = $this->viewableApiaries();
    $cache->addCacheTags($this->entityTypeManager->getDefinition('apiary')->getListCacheTags());

    if (!$apiaries) {
      // No visible apiaries → the whole dashboard is the welcome card.
      $build['welcome'] = $this->buildWelcome() + ['#weight' => 0];
    }
    else {
      $current_week = (int) date('W');
      $year = (int) date('Y');

      // One seasonal pass feeds both the needs-attention queue (overdue /
      // due rows) and the "Open seasonal tasks" tile (the open tally).
      $seasonal = $this->collectSeasonalAlerts($apiaries, $year, $current_week, $cache);
      $low_stock = $this->collectLowStockAlerts($apiaries, $cache);

      $build['needs_attention'] = $this->buildNeedsAttention(
        array_merge($seasonal['alerts'], $low_stock['alerts']),
        $current_week,
      ) + ['#weight' => 0];
      $build['stat_tiles'] = $this->buildStatTiles($cache, $apiaries, $seasonal, count($low_stock['alerts']), $year) + ['#weight' => 10];
      $build['activity'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-dashboard__split']],
        '#weight' => 20,
        'upcoming' => $this->buildUpcoming($cache, $apiaries, $year, $current_week),
        'recent' => $this->buildRecentActivity($cache),
      ];
      $build['apiaries_section'] = $this->buildApiariesSection(
        $cache,
        $apiaries,
        $seasonal['by_apiary'],
        $low_stock['by_apiary'],
      ) + ['#weight' => 40];
    }

    // - user: the CBR line is per-user (not per-permission), and it also
    //   covers the per-entity access filtering the roll-up does.
    // - max-age: the header prints the current ISO week, the
    //   needs-attention chips are week-relative, and "Inspections this
    //   month" is date-relative — so the render must not outlive the
    //   sooner of the next ISO-week boundary and the next midnight.
    $cache
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(min($this->secondsUntilNextIsoWeek(), $this->secondsUntilTomorrow()));
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
   * Builds the closing "Apiaries" section: one compact row per apiary.
   *
   * Columns: name (linked), hive count, open seasonal tasks (with an
   * overdue callout), low-stock item count, and the date of the most
   * recent inspection or action log. Plus an "Add Apiary" action.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row dependencies.
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param array<int, array{open: int, overdue: int}> $open_by_apiary
   *   Open seasonal-task tallies from collectSeasonalAlerts().
   * @param array<int, int> $low_stock_by_apiary
   *   Low-stock counts from collectLowStockAlerts().
   */
  protected function buildApiariesSection(CacheableMetadata $cache, array $apiaries, array $open_by_apiary, array $low_stock_by_apiary): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);

    $cache->addCacheTags($etm->getDefinition('hive')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive_inspection')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('apiary_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive_action_log')->getListCacheTags());

    $hive_counts = array_fill_keys($apiary_ids, 0);
    $hive_ids = $etm->getStorage('hive')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->execute();
    $hive_apiary = [];
    foreach ($hive_ids ? $etm->getStorage('hive')->loadMultiple($hive_ids) : [] as $hive) {
      $aid = (int) $hive->get('apiary')->target_id;
      $hive_counts[$aid]++;
      $hive_apiary[(int) $hive->id()] = $aid;
    }

    $last_activity = $this->lastActivityByApiary($apiary_ids, array_keys($hive_apiary), $hive_apiary);

    $rows = [];
    foreach ($apiaries as $id => $apiary) {
      $cache->addCacheableDependency($apiary);
      $open = $open_by_apiary[$id]['open'] ?? 0;
      $overdue = $open_by_apiary[$id]['overdue'] ?? 0;
      $low = $low_stock_by_apiary[$id] ?? 0;

      $rows[] = [
        'cells' => [
          $apiary->toLink()->toString(),
          (string) ($hive_counts[$id] ?? 0),
          $overdue
            ? (string) $this->t('@open (@overdue overdue)', ['@open' => $open, '@overdue' => $overdue])
            : (string) $open,
          $low ? (string) $low : '—',
          isset($last_activity[$id])
            ? $this->dateFormatter->format($last_activity[$id], 'custom', 'j M')
            : '—',
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard__section']],
      'head' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-dashboard__section-head']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['hivelog-dashboard__block-heading']],
          '#value' => $this->t('Apiaries'),
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Apiary'),
            'url' => Url::fromRoute('entity.apiary.add_form')->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => [
            (string) $this->t('Apiary'),
            (string) $this->t('Hives'),
            (string) $this->t('Open tasks'),
            (string) $this->t('Low stock'),
            (string) $this->t('Last activity'),
          ],
          'rows' => $rows,
          'empty_message' => (string) $this->t('No apiaries yet.'),
        ],
      ],
    ];
  }

  /**
   * Most-recent-activity timestamp per apiary (inspection date or log time).
   *
   * @param int[] $apiary_ids
   *   Apiary ids to cover.
   * @param int[] $hive_ids
   *   Every visible hive id across those apiaries.
   * @param array<int, int> $hive_apiary
   *   Map of hive id → apiary id.
   *
   * @return array<int, int>
   *   Apiary id → the newest activity timestamp (only apiaries with any).
   */
  protected function lastActivityByApiary(array $apiary_ids, array $hive_ids, array $hive_apiary): array {
    $etm = $this->entityTypeManager;
    $limit = max(20, count($apiary_ids) * 10);
    $last = [];

    $note = static function (array &$last, int $apiary_id, int $ts): void {
      if (!isset($last[$apiary_id]) || $ts > $last[$apiary_id]) {
        $last[$apiary_id] = $ts;
      }
    };

    if ($hive_ids) {
      $ins_ids = $etm->getStorage('hive_inspection')->getQuery()
        ->accessCheck(TRUE)
        ->condition('hive', $hive_ids, 'IN')
        ->sort('inspection_date', 'DESC')
        ->range(0, $limit)
        ->execute();
      foreach ($ins_ids ? $etm->getStorage('hive_inspection')->loadMultiple($ins_ids) : [] as $inspection) {
        $aid = $hive_apiary[(int) $inspection->get('hive')->target_id] ?? NULL;
        $date = (string) $inspection->get('inspection_date')->value;
        if ($aid !== NULL && $date !== '') {
          $note($last, $aid, (int) strtotime($date));
        }
      }

      $hlog_ids = $etm->getStorage('hive_action_log')->getQuery()
        ->accessCheck(TRUE)
        ->condition('hive', $hive_ids, 'IN')
        ->sort('created', 'DESC')
        ->range(0, $limit)
        ->execute();
      foreach ($hlog_ids ? $etm->getStorage('hive_action_log')->loadMultiple($hlog_ids) : [] as $log) {
        $aid = $hive_apiary[(int) $log->get('hive')->target_id] ?? NULL;
        if ($aid !== NULL) {
          $note($last, $aid, (int) $log->get('created')->value);
        }
      }
    }

    $alog_ids = $etm->getStorage('apiary_action_log')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    foreach ($alog_ids ? $etm->getStorage('apiary_action_log')->loadMultiple($alog_ids) : [] as $log) {
      $note($last, (int) $log->get('apiary')->target_id, (int) $log->get('created')->value);
    }

    return $last;
  }

  /**
   * Builds the "Needs attention" panel from pre-collected alerts.
   *
   * The alerts come from collectSeasonalAlerts() (overdue / due-this-week
   * unreported CalendarActions across every visible apiary and hive) and
   * collectLowStockAlerts() (inventory at or below its threshold). Rows
   * are ordered overdue → due-this-week → low stock.
   *
   * @param array[] $alerts
   *   Merged, unsorted alert descriptors (see buildAttentionRow()).
   * @param int $current_week
   *   The current ISO week number, for the empty-state message.
   */
  protected function buildNeedsAttention(array $alerts, int $current_week): array {
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
      '#attributes' => [
        'class' => [
          'hivelog-attention__count',
          $overdue ? 'hivelog-attention__count--danger' : 'hivelog-attention__count--warning',
        ],
      ],
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
   * @return array{alerts: array[], open_total: int, open_overdue: int, by_apiary: array<int, array{open: int, overdue: int}>}
   *   `alerts` are the overdue / due row descriptors (see
   *   buildAttentionRow()); `open_total` / `open_overdue` feed the
   *   "Open seasonal tasks" stat tile; `by_apiary` (keyed by apiary id)
   *   feeds the closing "Apiaries" section.
   */
  protected function collectSeasonalAlerts(array $apiaries, int $year, int $current_week, CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);
    $by_apiary = array_fill_keys($apiary_ids, ['open' => 0, 'overdue' => 0]);
    $empty = ['alerts' => [], 'open_total' => 0, 'open_overdue' => 0, 'by_apiary' => $by_apiary];

    $action_ids = $etm->getStorage('calendar_action')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('enabled', TRUE)
      ->sort('week_start', 'ASC')
      ->execute();
    if (!$action_ids) {
      return $empty;
    }
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
        $apiary_id = (int) $action->get('apiary')->target_id;
        $open_total++;
        $by_apiary[$apiary_id]['open']++;
        if ($current_week > $this->effectiveWeekEnd($action)) {
          $open_overdue++;
          $by_apiary[$apiary_id]['overdue']++;
        }
        $timing = $this->attentionTiming($action, $current_week);
        if (!$timing) {
          continue;
        }
        $cache->addCacheableDependency($action);
        if ($log) {
          $cache->addCacheableDependency($log);
        }
        $apiary = $apiaries[$apiary_id];
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
            $open_total++;
            $by_apiary[$hive_apiary_id]['open']++;
            if ($current_week > $this->effectiveWeekEnd($action)) {
              $open_overdue++;
              $by_apiary[$hive_apiary_id]['overdue']++;
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

    return [
      'alerts' => $alerts,
      'open_total' => $open_total,
      'open_overdue' => $open_overdue,
      'by_apiary' => $by_apiary,
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
   * @return array{alerts: array[], by_apiary: array<int, int>}
   *   `alerts` are the row descriptors (see buildAttentionRow());
   *   `by_apiary` is the low-stock count keyed by apiary id, for the
   *   closing "Apiaries" section.
   */
  protected function collectLowStockAlerts(array $apiaries, CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);
    $by_apiary = array_fill_keys($apiary_ids, 0);
    $empty = ['alerts' => [], 'by_apiary' => $by_apiary];

    $ids = $etm->getStorage('inventory_item')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('status', 'active')
      ->exists('low_stock_threshold')
      ->sort('name', 'ASC')
      ->execute();
    if (!$ids) {
      return $empty;
    }
    $items = array_filter(
      $etm->getStorage('inventory_item')->loadMultiple($ids),
      fn($item) => $item->access('view')
    );
    if (!$items) {
      return $empty;
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
      $apiary_id = (int) $item->get('apiary')->target_id;
      $by_apiary[$apiary_id]++;
      $apiary = $apiaries[$apiary_id];
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

    return ['alerts' => $alerts, 'by_apiary' => $by_apiary];
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

  /**
   * Seconds remaining until the next local midnight.
   *
   * Bounds the cache max-age for date-relative figures ("Inspections this
   * month") so a cached render is refreshed at least once a day.
   *
   * @return int
   *   Seconds until tomorrow, 00:00.
   */
  protected function secondsUntilTomorrow(): int {
    $now = new \DateTimeImmutable('now');
    $tomorrow = new \DateTimeImmutable('tomorrow');
    return max(0, $tomorrow->getTimestamp() - $now->getTimestamp());
  }

  /**
   * Builds the six stat tiles.
   *
   * All figures are `->count()` queries except low stock (needs
   * isLowStock(), from the caller's earlier pass) and Net YTD (a sum of
   * InventoryReportController::computeApiaryYearTotals() — no new
   * financial logic). The Net YTD tile is omitted for users without
   * inventory-view access.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the list cache tags and per-row dependencies the tiles read.
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param array{alerts: array[], open_total: int, open_overdue: int} $seasonal
   *   The seasonal pass result from collectSeasonalAlerts().
   * @param int $low_stock_count
   *   Number of low-stock items (count of collectLowStockAlerts()).
   * @param int $year
   *   The current year, for Net YTD.
   */
  protected function buildStatTiles(CacheableMetadata $cache, array $apiaries, array $seasonal, int $low_stock_count, int $year): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);

    foreach ([
      'hive',
      'hive_inspection',
      'calendar_action',
      'apiary_action_log',
      'hive_action_log',
      'inventory_item',
      'inventory_purchase',
      'inventory_usage',
      'harvest_yield',
      'product',
    ] as $type) {
      $cache->addCacheTags($etm->getDefinition($type)->getListCacheTags());
    }

    $active_hives = (int) $etm->getStorage('hive')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('status', 'active')
      ->count()
      ->execute();

    $hive_ids = $etm->getStorage('hive')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->execute();
    $inspections_this_month = 0;
    if ($hive_ids) {
      $inspections_this_month = (int) $etm->getStorage('hive_inspection')->getQuery()
        ->accessCheck(TRUE)
        ->condition('hive', array_values($hive_ids), 'IN')
        ->condition('inspection_date', date('Y-m-01'), '>=')
        ->condition('inspection_date', date('Y-m-01', strtotime('first day of next month')), '<')
        ->count()
        ->execute();
    }

    $tiles = [
      $this->statTile((string) count($apiaries), $this->t('Apiaries'), Url::fromRoute('entity.apiary.collection')),
      $this->statTile((string) $active_hives, $this->t('Active hives'), Url::fromRoute('entity.hive.collection')),
      $this->statTile((string) $inspections_this_month, $this->t('Inspections this month'), Url::fromRoute('entity.hive_inspection.collection')),
      $this->statTile(
        (string) $seasonal['open_total'],
        $this->t('Open seasonal tasks'),
        Url::fromRoute('entity.calendar_action.collection'),
        $seasonal['open_overdue'] ? $this->t('@n overdue', ['@n' => $seasonal['open_overdue']]) : '',
        $seasonal['open_overdue'] ? 'critical' : 'default',
      ),
      $this->statTile(
        (string) $low_stock_count,
        $this->t('Low-stock items'),
        Url::fromRoute('entity.inventory_item.collection'),
        '',
        $low_stock_count ? 'warning' : 'default',
      ),
    ];

    if ($this->canSeeFinances()) {
      $net_url = count($apiaries) === 1
        ? Url::fromRoute('hivelog.apiary.inventory_cost_report', ['apiary' => (int) array_key_first($apiaries)])
        : Url::fromRoute('entity.apiary.collection');
      $tiles[] = $this->statTile(
        number_format($this->sumNetYtd($apiaries, $year, $cache), 0),
        $this->t('Net YTD'),
        $net_url,
      );
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-stat-tiles']],
    ];
    foreach ($tiles as $i => $tile) {
      $build['tile_' . $i] = $tile;
    }
    return $build;
  }

  /**
   * Builds one hivelog:stat-tile component render array.
   *
   * @param string $value
   *   The pre-formatted figure.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The tile label.
   * @param \Drupal\Core\Url $url
   *   Destination for the whole-tile link.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $sublabel
   *   Optional sub-line.
   * @param string $variant
   *   Sub-line variant: default, critical or warning.
   */
  protected function statTile(string $value, $label, Url $url, $sublabel = '', string $variant = 'default'): array {
    return [
      '#type' => 'component',
      '#component' => 'hivelog:stat-tile',
      '#props' => [
        'value' => $value,
        'label' => (string) $label,
        'url' => $url->toString(),
        'sublabel' => (string) $sublabel,
        'sublabel_variant' => $variant,
      ],
    ];
  }

  /**
   * Whether the current user may see the (money) Net YTD tile.
   *
   * Mirrors the permission OR-set on the apiary financial report route.
   */
  protected function canSeeFinances(): bool {
    return $this->currentUser->hasPermission('view any inventory item')
      || $this->currentUser->hasPermission('view own inventory item')
      || $this->currentUser->hasPermission('administer hivelog');
  }

  /**
   * Sums this year's net position across the given apiaries.
   *
   * A straight sum of InventoryReportController::computeApiaryYearTotals()
   * — no new financial logic. Folds every item / product the totals
   * touched into the render's cache metadata.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param int $year
   *   The year to total.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the item / product dependencies.
   */
  protected function sumNetYtd(array $apiaries, int $year, CacheableMetadata $cache): float {
    $report = $this->classResolver->getInstanceFromDefinition(InventoryReportController::class);
    $net = 0.0;
    foreach ($apiaries as $apiary) {
      $totals = $report->computeApiaryYearTotals($apiary, $year);
      $net += $totals['net'];
      foreach ($totals['consumables'] as $row) {
        $cache->addCacheableDependency($row['item']);
      }
      foreach ($totals['depreciation'] as $row) {
        $cache->addCacheableDependency($row['item']);
      }
      foreach ($totals['yields'] as $row) {
        $cache->addCacheableDependency($row['product']);
      }
    }
    return $net;
  }

  /**
   * Builds the read-only "Upcoming" look-ahead (next four weeks).
   *
   * Lists enabled CalendarActions whose start week falls in
   * [current_week + 1, current_week + 4], one row per action (hive-scoped
   * actions are not fanned out — this is a forward plan, not a checklist).
   * Apiary-scoped actions already reported done / ignored for the year are
   * dropped. No wraparound past week 53.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row dependencies.
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Viewable apiaries keyed by id.
   * @param int $year
   *   The current year, for the reported-status check.
   * @param int $current_week
   *   The current ISO week number.
   */
  protected function buildUpcoming(CacheableMetadata $cache, array $apiaries, int $year, int $current_week): array {
    $etm = $this->entityTypeManager;
    $apiary_ids = array_keys($apiaries);

    $cache->addCacheTags($etm->getDefinition('calendar_action')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('apiary_action_log')->getListCacheTags());

    $block = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-upcoming']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['class' => ['hivelog-dashboard__block-heading']],
        '#value' => $this->t('Upcoming'),
      ],
    ];

    $from = $current_week + 1;
    $to = min($current_week + 4, 53);
    $rows = [];

    if ($from <= 53) {
      $action_ids = $etm->getStorage('calendar_action')->getQuery()
        ->accessCheck(TRUE)
        ->condition('apiary', $apiary_ids, 'IN')
        ->condition('enabled', TRUE)
        ->condition('week_start', $from, '>=')
        ->condition('week_start', $to, '<=')
        ->sort('week_start', 'ASC')
        ->execute();
      $actions = $action_ids ? array_filter(
        $etm->getStorage('calendar_action')->loadMultiple($action_ids),
        fn($action) => $action->access('view')
      ) : [];

      $reported = $this->reportedApiaryActionIds($apiary_ids, $actions, $year);

      foreach ($actions as $action) {
        if ($action->get('scope')->value === 'apiary' && isset($reported[$action->id()])) {
          continue;
        }
        $cache->addCacheableDependency($action);
        $apiary = $apiaries[(int) $action->get('apiary')->target_id];
        $rows[] = [
          '#type' => 'inline_template',
          '#template' => '<div class="hivelog-upcoming__row"><span class="hivelog-upcoming__wk">{{ wk }}</span><span class="hivelog-upcoming__text">{{ title }} <span class="hivelog-upcoming__where">· {{ apiary }}</span></span></div>',
          '#context' => [
            'wk' => $this->t('Wk @n', ['@n' => (int) $action->get('week_start')->value]),
            'title' => $action->toLink()->toRenderable(),
            'apiary' => $apiary->label(),
          ],
        ];
      }
    }

    if (!$rows) {
      $block['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['hivelog-dashboard__block-empty']],
        '#value' => $this->t('Nothing scheduled for the next four weeks.'),
      ];
      return $block;
    }
    foreach ($rows as $i => $row) {
      $block['row_' . $i] = $row;
    }
    return $block;
  }

  /**
   * Returns the ids of apiary-scoped actions reported done / ignored for a year.
   *
   * @param int[] $apiary_ids
   *   Apiary ids to match.
   * @param \Drupal\hivelog\Entity\CalendarAction[] $actions
   *   The candidate actions (only the apiary-scoped ones are checked).
   * @param int $year
   *   The reporting year.
   *
   * @return array<int|string, true>
   *   A set keyed by calendar-action id.
   */
  protected function reportedApiaryActionIds(array $apiary_ids, array $actions, int $year): array {
    $scoped_ids = [];
    foreach ($actions as $action) {
      if ($action->get('scope')->value === 'apiary') {
        $scoped_ids[] = $action->id();
      }
    }
    if (!$scoped_ids) {
      return [];
    }

    $log_ids = $this->entityTypeManager->getStorage('apiary_action_log')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('calendar_action', $scoped_ids, 'IN')
      ->condition('year', $year)
      ->condition('status', ['done', 'ignored'], 'IN')
      ->execute();

    $reported = [];
    foreach ($log_ids ? $this->entityTypeManager->getStorage('apiary_action_log')->loadMultiple($log_ids) : [] as $log) {
      /** @var \Drupal\hivelog\Entity\ApiaryActionLog $log */
      $reported[$log->get('calendar_action')->target_id] = TRUE;
    }
    return $reported;
  }

  /**
   * Builds the "Recent activity" list.
   *
   * Merges the most recent rows (by `created`) of six record types —
   * inspections, queen observations, hive and apiary action logs,
   * inventory purchases and harvest yields (project decision 5) — capped
   * at ten per type, then sliced to the ten newest overall. Each row
   * links to its canonical page (harvest yields, which have none, link to
   * the action log they belong to).
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects list cache tags and per-row dependencies.
   */
  protected function buildRecentActivity(CacheableMetadata $cache): array {
    $etm = $this->entityTypeManager;
    $cap = 10;

    $nouns = [
      'hive_inspection' => $this->t('Inspection'),
      'queen_observation' => $this->t('Queen observation'),
      'hive_action_log' => $this->t('Action log'),
      'apiary_action_log' => $this->t('Action log'),
      'inventory_purchase' => $this->t('Purchase'),
      'harvest_yield' => $this->t('Harvest yield'),
    ];

    $entries = [];
    foreach ($nouns as $type => $noun) {
      $cache->addCacheTags($etm->getDefinition($type)->getListCacheTags());
      $ids = $etm->getStorage($type)->getQuery()
        ->accessCheck(TRUE)
        ->sort('created', 'DESC')
        ->range(0, $cap)
        ->execute();
      if (!$ids) {
        continue;
      }
      foreach ($etm->getStorage($type)->loadMultiple($ids) as $entity) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        if (!$entity->access('view')) {
          continue;
        }
        $cache->addCacheableDependency($entity);
        $entries[] = [
          'created' => (int) $entity->get('created')->value,
          'noun' => $noun,
          'label' => $entity->label(),
          'url' => $this->recentActivityUrl($entity),
        ];
      }
    }

    usort($entries, fn($a, $b) => $b['created'] <=> $a['created']);
    $entries = array_slice($entries, 0, 10);

    $block = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-recent']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['class' => ['hivelog-dashboard__block-heading']],
        '#value' => $this->t('Recent activity'),
      ],
    ];

    if (!$entries) {
      $block['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['hivelog-dashboard__block-empty']],
        '#value' => $this->t('No activity recorded yet.'),
      ];
      return $block;
    }

    foreach ($entries as $i => $entry) {
      $block['row_' . $i] = [
        '#type' => 'inline_template',
        '#template' => '<div class="hivelog-recent__row"><span class="hivelog-recent__when">{{ when }}</span><span class="hivelog-recent__text">{{ noun }} — {% if link %}{{ link }}{% else %}{{ label }}{% endif %}</span></div>',
        '#context' => [
          'when' => $this->dateFormatter->format($entry['created'], 'custom', 'j M'),
          'noun' => $entry['noun'],
          'link' => $entry['url'] ? ['#type' => 'link', '#title' => $entry['label'], '#url' => $entry['url']] : NULL,
          'label' => $entry['label'],
        ],
      ];
    }
    return $block;
  }

  /**
   * Resolves a recent-activity row's link.
   *
   * Most types have a canonical route; harvest yields do not, so they
   * link to the action log they belong to.
   */
  protected function recentActivityUrl(ContentEntityInterface $entity): ?Url {
    if ($entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical');
    }
    if ($entity->hasField('hive_action_log')) {
      $log = $entity->get('hive_action_log')->entity ?? $entity->get('apiary_action_log')->entity;
      if ($log) {
        return $log->toUrl('canonical');
      }
    }
    return NULL;
  }

}
