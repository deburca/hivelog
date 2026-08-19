<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the per-apiary, per-year inventory cost & depreciation report.
 *
 * Sums, for a selected year: the cost of consumables actually used
 * (`Σ InventoryUsage.quantity × unit_cost_snapshot`) plus the active
 * depreciation of durable assets (`InventoryItem::getAnnualDepreciation()`).
 * Purely a read-side aggregation over entities the rest of the inventory
 * feature already maintains — see
 * docs/project-management/decisions/0027-inventory-tracking-and-depreciation.md.
 */
class InventoryReportController extends ControllerBase {

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs an InventoryReportController.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    // $entityTypeManager is an untyped property inherited from
    // ControllerBase; assign rather than redeclare it.
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Builds the cost & depreciation report for one apiary and year.
   */
  public function costReport(Apiary $apiary) {
    $year = $this->extractReportYear();

    $consumables = $this->consumableCostBreakdown($apiary, $year);
    $depreciation = $this->depreciationBreakdown($apiary, $year);

    $consumable_total = array_reduce($consumables, fn($carry, $row) => $carry + $row['cost'], 0.0);
    $depreciation_total = array_reduce($depreciation, fn($carry, $row) => $carry + $row['depreciation'], 0.0);

    $build = [];

    // No self-built heading here — unlike ApiaryController::fullCalendar()
    // and similar pages, this page's inline heading would have had no
    // buttons of its own (the year selector is its own row below), so it
    // would have done nothing but restate the route's own page title
    // (costReportTitle()) as a second, redundant heading.
    $build['year_selector'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#weight' => 1,
      '#props' => ['buttons' => $this->buildYearSelectorButtons($apiary, $year)],
    ];

    $build['summary'] = [
      '#type' => 'table',
      '#weight' => 2,
      '#header' => [$this->t('Total consumable cost'), $this->t('Total active depreciation'), $this->t('Total')],
      '#rows' => [
        [
          number_format($consumable_total, 2),
          number_format($depreciation_total, 2),
          number_format($consumable_total + $depreciation_total, 2),
        ],
      ],
      '#attributes' => ['class' => ['hivelog-inventory-report-table']],
      '#attached' => ['library' => ['hivelog/tables']],
    ];

    $breakdown_rows = [];
    foreach ($consumables as $row) {
      $breakdown_rows[] = [
        $row['item']->toLink()->toString(),
        $this->t('Consumable'),
        rtrim(rtrim(number_format($row['quantity'], 3, '.', ''), '0'), '.') . ' ' . $row['item']->get('unit')->value,
        number_format($row['cost'], 2),
      ];
    }
    foreach ($depreciation as $row) {
      $breakdown_rows[] = [
        $row['item']->toLink()->toString(),
        $this->t('Durable'),
        '',
        number_format($row['depreciation'], 2),
      ];
    }

    $build['breakdown'] = [
      '#type' => 'container',
      '#weight' => 3,
      '#attributes' => ['class' => ['hivelog-inventory-report-breakdown']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Breakdown by item'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Item'), $this->t('Type'), $this->t('Quantity used'), $this->t('Cost')],
        '#rows' => $breakdown_rows,
        '#empty' => $this->t('No inventory cost or depreciation recorded for @year.', ['@year' => $year]),
        '#attributes' => ['class' => ['hivelog-inventory-report-table']],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];

    $cache = (new CacheableMetadata())
      ->addCacheContexts(['url.query_args:year', 'user.permissions'])
      ->addCacheableDependency($apiary)
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_purchase')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_usage')->getListCacheTags());
    foreach ($consumables as $row) {
      $cache->addCacheableDependency($row['item']);
    }
    foreach ($depreciation as $row) {
      $cache->addCacheableDependency($row['item']);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the cost report page.
   */
  public function costReportTitle(Apiary $apiary) {
    return $this->t('Inventory Cost Report: @apiary', ['@apiary' => $apiary->label()]);
  }

  /**
   * Extracts the selected report year from the request, clamped to ±1.
   *
   * Matches HiveController::extractCalendarFilters()'s year-clamping
   * logic exactly, rather than inventing a new pattern: defaults to the
   * current year, and any out-of-range value (including a tampered query
   * string) falls back to the current year too.
   */
  protected function extractReportYear(): int {
    $request = $this->requestStack->getCurrentRequest();
    $current_year = (int) date('Y');
    $year = $request ? (int) $request->query->get('year', (string) $current_year) : $current_year;
    if (!in_array($year, [$current_year - 1, $current_year, $current_year + 1], TRUE)) {
      $year = $current_year;
    }
    return $year;
  }

  /**
   * Builds the previous/current/next year selector buttons.
   */
  protected function buildYearSelectorButtons(Apiary $apiary, int $selected_year): array {
    $current_year = (int) date('Y');
    $buttons = [];
    foreach ([$current_year - 1, $current_year, $current_year + 1] as $year) {
      $buttons[] = [
        'label' => (string) $year,
        'url' => Url::fromRoute('hivelog.apiary.inventory_cost_report', ['apiary' => $apiary->id()], ['query' => ['year' => $year]])->toString(),
        'variant' => $year === $selected_year ? 'primary' : 'default',
      ];
    }
    return $buttons;
  }

  /**
   * Builds the consumable cost breakdown for an apiary/year, keyed by item id.
   *
   * Sums `InventoryUsage` rows whose owning log (hive- or apiary-scoped)
   * belongs to this apiary and matches `$year` — joined via two entity
   * queries (apiary-scoped logs directly, hive-scoped logs via the
   * apiary's hives) since `InventoryUsage` has no direct apiary/year
   * field of its own (see ADR-0027's "computed, never stored" costing
   * decision).
   *
   * @return array<int, array{item: \Drupal\hivelog\Entity\InventoryItem, quantity: float, cost: float}>
   *   Breakdown rows keyed by inventory item id.
   */
  protected function consumableCostBreakdown(Apiary $apiary, int $year): array {
    $apiary_log_ids = $this->entityTypeManager->getStorage('apiary_action_log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->condition('year', $year)
      ->execute();

    $hive_ids = $this->entityTypeManager->getStorage('hive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->execute();
    $hive_log_ids = [];
    if ($hive_ids) {
      $hive_log_ids = $this->entityTypeManager->getStorage('hive_action_log')->getQuery()
        ->accessCheck(FALSE)
        ->condition('hive', array_values($hive_ids), 'IN')
        ->condition('year', $year)
        ->execute();
    }

    if (!$apiary_log_ids && !$hive_log_ids) {
      return [];
    }

    $usage_storage = $this->entityTypeManager->getStorage('inventory_usage');
    $query = $usage_storage->getQuery()->accessCheck(FALSE);
    $or = $query->orConditionGroup();
    if ($apiary_log_ids) {
      $or->condition('apiary_action_log', array_values($apiary_log_ids), 'IN');
    }
    if ($hive_log_ids) {
      $or->condition('hive_action_log', array_values($hive_log_ids), 'IN');
    }
    $usage_ids = $query->condition($or)->execute();
    if (!$usage_ids) {
      return [];
    }

    $breakdown = [];
    foreach ($usage_storage->loadMultiple($usage_ids) as $usage) {
      $item = $usage->get('item')->entity;
      if (!$item) {
        continue;
      }
      $item_id = (int) $item->id();
      if (!isset($breakdown[$item_id])) {
        $breakdown[$item_id] = ['item' => $item, 'quantity' => 0.0, 'cost' => 0.0];
      }
      $quantity = (float) $usage->get('quantity')->value;
      $unit_cost = (float) $usage->get('unit_cost_snapshot')->value;
      $breakdown[$item_id]['quantity'] += $quantity;
      $breakdown[$item_id]['cost'] += $quantity * $unit_cost;
    }

    return $breakdown;
  }

  /**
   * Builds the durable-item depreciation breakdown for an apiary/year.
   *
   * The "helper resolving [depreciation] across every item in an apiary"
   * from the task's acceptance criteria — sums
   * `InventoryItem::getAnnualDepreciation()` across every durable item in
   * the apiary, omitting items with zero active depreciation for the
   * selected year.
   *
   * @return array<int, array{item: \Drupal\hivelog\Entity\InventoryItem, depreciation: float}>
   *   Breakdown rows keyed by inventory item id.
   */
  protected function depreciationBreakdown(Apiary $apiary, int $year): array {
    $item_storage = $this->entityTypeManager->getStorage('inventory_item');
    $item_ids = $item_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->condition('item_type', 'durable')
      ->execute();
    if (!$item_ids) {
      return [];
    }

    $breakdown = [];
    foreach ($item_storage->loadMultiple($item_ids) as $item) {
      $depreciation = $item->getAnnualDepreciation($year);
      if ($depreciation > 0) {
        $breakdown[(int) $item->id()] = ['item' => $item, 'depreciation' => $depreciation];
      }
    }

    return $breakdown;
  }

}
