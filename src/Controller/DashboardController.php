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
use Drupal\hivelog\HivelogAlertCollector;
use Drupal\hivelog\HivelogCalendarChecklistBuilder;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The HiveLog dashboard — the module's landing page at /hivelog.
 *
 * See ADR-0057 and task 0056. view() renders, in order: the header strip
 * (current ISO week + the CBR summary line, moved here from
 * ApiaryListBuilder), and then either the first-run welcome card (no
 * visible apiaries) or the operational widgets — the "Needs attention"
 * queue, any optional submodule dashboard sections (task 0093;
 * hook_hivelog_dashboard_sections(), see hivelog.api.php and ADR-0099),
 * the six stat tiles, and the "Upcoming" / "Recent activity" split. One
 * seasonal pass (collectSeasonalAlerts()) feeds both the queue and the
 * "Open seasonal tasks" tile.
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
   * The calendar checklist builder (task 0130).
   */
  protected HivelogCalendarChecklistBuilder $calendarChecklistBuilder;

  /**
   * The alert collector shared with the mobile API (task 0202).
   */
  protected HivelogAlertCollector $alertCollector;

  /**
   * Constructs a DashboardController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    ClassResolverInterface $class_resolver,
    DateFormatterInterface $date_formatter,
    HivelogCalendarChecklistBuilder $calendar_checklist_builder,
    HivelogAlertCollector $alert_collector,
  ) {
    // $entityTypeManager / $currentUser are untyped properties inherited
    // from ControllerBase; assign them rather than redeclaring them.
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->classResolver = $class_resolver;
    $this->dateFormatter = $date_formatter;
    $this->calendarChecklistBuilder = $calendar_checklist_builder;
    $this->alertCollector = $alert_collector;
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
      $container->get('hivelog.calendar_checklist_builder'),
      $container->get('hivelog.alert_collector'),
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
      . '<svg viewBox="0 0 24 24" width="56" height="56">'
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

    $apiaries = $this->calendarChecklistBuilder->viewableApiaries($this->currentUser);
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
      $collected = $this->alertCollector->collect($apiaries, $year, $current_week, $cache);
      $seasonal = $collected['seasonal'];
      $low_stock = $collected['low_stock'];

      $build['needs_attention'] = $this->buildNeedsAttention($collected['alerts'], $current_week) + ['#weight' => 0];

      // Optional submodules (e.g. nexus's "AI Insights" section)
      // contribute whole new dashboard sections here without hivelog
      // core depending on them — see hivelog.api.php's
      // hook_hivelog_dashboard_sections() and ADR-0099. Each section
      // carries its own #weight, so invocation order here doesn't
      // determine page position.
      foreach ($this->moduleHandler()->invokeAll('hivelog_dashboard_sections', [$apiaries, $cache]) as $key => $section) {
        $build[$key] = $section;
      }

      $build['stat_tiles'] = $this->buildStatTiles($cache, $apiaries, $seasonal, count($low_stock), $year, $current_week) + ['#weight' => 10];
      $build['activity'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-dashboard__split']],
        '#weight' => 20,
        'upcoming' => $this->buildUpcoming($cache, $apiaries, $year, $current_week),
        'recent' => $this->buildRecentActivity($cache),
      ];
    }

    // - user: the CBR line is per-user (not per-permission), and it also
    //   covers the per-entity access filtering the roll-up does.
    // - max-age: the header prints the current ISO week, the
    //   needs-attention chips are week-relative, and "Inspections this
    //   month" is date-relative — so the render must not outlive the
    //   sooner of the next ISO-week boundary and the next midnight.
    $cache
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(min($this->calendarChecklistBuilder->secondsUntilNextIsoWeek(), $this->secondsUntilTomorrow()));
    $cache->applyTo($build);

    return $build;
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
      'action' => $this->buildAttentionAction($alert),
    ];
  }

  /**
   * Builds a "Needs attention" row's action control.
   *
   * Seasonal rows carry an `actions` list (Done / Ignored) and render as a
   * hivelog:button-group, matching the apiary and hive calendar
   * checklists; low-stock rows keep their single "Add purchase" button.
   * Every target is a safe GET link to an add form (with a ?status=
   * default for the report links) — the write only happens through that
   * form's own CSRF-protected POST (ADR-0018).
   */
  protected function buildAttentionAction(array $alert): array {
    if (!empty($alert['actions'])) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-attention__action']],
        'group' => [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => ['buttons' => $alert['actions']],
        ],
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $alert['action_label'],
        'url' => $alert['action_url'],
        'variant' => 'primary',
        'extra_classes' => 'hivelog-attention__action',
      ],
    ];
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
   * @param int $current_week
   *   The current ISO week number, for the "Open seasonal tasks" tile's
   *   link (task 0073).
   */
  protected function buildStatTiles(CacheableMetadata $cache, array $apiaries, array $seasonal, int $low_stock_count, int $year, int $current_week): array {
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

    // With exactly one apiary the tile jumps straight to it; otherwise to
    // the list.
    $apiaries_url = count($apiaries) === 1
      ? Url::fromRoute('entity.apiary.canonical', ['apiary' => (int) array_key_first($apiaries)])
      : Url::fromRoute('entity.apiary.collection');

    $tiles = [
      $this->statTile((string) count($apiaries), $this->t('Apiaries'), $apiaries_url),
      $this->statTile((string) $active_hives, $this->t('Active hives'), Url::fromRoute('entity.hive.collection')),
      $this->statTile((string) $inspections_this_month, $this->t('Inspections this month'), Url::fromRoute('entity.hive_inspection.collection')),
      $this->statTile(
        (string) $seasonal['open_total'],
        $this->t('Open seasonal tasks'),
        // Defaults the collection page's week filter to "this week through
        // the end of the year" — the open tally counts every unreported
        // action regardless of week, so this is the closest the filtered
        // table can get to matching it (see that page's own footnote).
        Url::fromRoute('entity.calendar_action.collection', [], [
          'query' => ['week_from' => (string) $current_week, 'week_to' => '53'],
        ]),
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
      // One apiary: straight to its financial report. More than one:
      // the combined all-apiaries report (task 0059).
      $net_url = count($apiaries) === 1
        ? Url::fromRoute('hivelog.apiary.inventory_cost_report', ['apiary' => (int) array_key_first($apiaries)])
        : Url::fromRoute('hivelog.apiaries.financial_report');
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
   * dropped; hive-scoped actions drop the same way once every hive in the
   * apiary has reported. No wraparound past week 53.
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
    $cache->addCacheTags($etm->getDefinition('hive_action_log')->getListCacheTags());
    $cache->addCacheTags($etm->getDefinition('hive')->getListCacheTags());

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
      $reported_hive = $this->reportedHiveActionIds($apiary_ids, $actions, $year);

      foreach ($actions as $action) {
        $scope = $action->get('scope')->value;
        if ($scope === 'apiary' && isset($reported[$action->id()])) {
          continue;
        }
        if ($scope === 'hive' && isset($reported_hive[$action->id()])) {
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
   * Returns the ids of hive-scoped actions every hive has reported for a year.
   *
   * Unlike the needs-attention / open-tile pass, which tracks each hive's
   * instance of a hive-scoped action separately, "Upcoming" shows one
   * forward-looking row per action — so it only drops once EVERY hive in
   * the action's apiary has a done / ignored log for the year.
   *
   * @param int[] $apiary_ids
   *   Apiary ids to match.
   * @param \Drupal\hivelog\Entity\CalendarAction[] $actions
   *   The candidate actions (only the hive-scoped ones are checked).
   * @param int $year
   *   The reporting year.
   *
   * @return array<int|string, true>
   *   A set keyed by calendar-action id.
   */
  protected function reportedHiveActionIds(array $apiary_ids, array $actions, int $year): array {
    $hive_scoped = array_filter($actions, fn($action) => $action->get('scope')->value === 'hive');
    if (!$hive_scoped) {
      return [];
    }

    $etm = $this->entityTypeManager;
    $hive_ids = $etm->getStorage('hive')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->execute();
    if (!$hive_ids) {
      return [];
    }
    $hives = array_filter(
      $etm->getStorage('hive')->loadMultiple($hive_ids),
      fn($hive) => $hive->access('view')
    );
    if (!$hives) {
      return [];
    }

    $hives_by_apiary = [];
    foreach ($hives as $hive) {
      $hives_by_apiary[(int) $hive->get('apiary')->target_id][] = $hive->id();
    }

    $logs = $this->alertCollector->indexHiveLogs(array_keys($hives), array_keys($hive_scoped), $year);

    $reported = [];
    foreach ($hive_scoped as $action) {
      $action_hive_ids = $hives_by_apiary[(int) $action->get('apiary')->target_id] ?? [];
      if (!$action_hive_ids) {
        continue;
      }
      $all_reported = TRUE;
      foreach ($action_hive_ids as $hive_id) {
        $log = $logs[$hive_id][$action->id()] ?? NULL;
        if (!$log || $log->get('status')->value === 'pending') {
          $all_reported = FALSE;
          break;
        }
      }
      if ($all_reported) {
        $reported[$action->id()] = TRUE;
      }
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
