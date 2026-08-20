<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests InventoryItem::getAnnualDepreciation() and the cost report page.
 *
 * See docs/project-management/tasks/0032-inventory-cost-and-depreciation-report.md
 * and ADR-0027's depreciation formula: a durable purchase costing `C`,
 * bought in year `Y0`, with `useful_life_years = N`, contributes `C / N`
 * to each year from `Y0` through `Y0 + N − 1`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryCostReportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'hivelog',
  ];

  /**
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Report Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests that a purchase depreciates only within its useful-life window.
   */
  public function testDepreciationWindowBoundaries(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 3,
    ]);
    $item->save();

    // Bought in 2024, useful_life_years = 3: window is 2024-2026.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2024-06-01',
      'quantity' => 10,
      'unit_price' => 3,
    ])->save();

    $this->assertEquals(0.0, $item->getAnnualDepreciation(2023));
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2024));
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2025));
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2026));
    $this->assertEquals(0.0, $item->getAnnualDepreciation(2027));
  }

  /**
   * Tests that a disposed purchase stops depreciating after its disposal year.
   *
   * See docs/project-management/tasks/0049-asset-disposal-and-write-off-tracking.md.
   * Contrasted with testDepreciationWindowBoundaries()'s undisposed,
   * full-window case: here the useful-life window (2024-2026) is cut
   * short by a disposal in 2025, so 2026 — still inside the original
   * window — must show zero, not the usual per-year contribution.
   */
  public function testDisposalStopsDepreciationAfterDisposalYear(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 3,
    ]);
    $item->save();

    // Bought in 2024, useful_life_years = 3: window would be 2024-2026,
    // but disposed mid-way through 2025.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2024-06-01',
      'quantity' => 10,
      'unit_price' => 3,
      'disposal_date' => '2025-09-01',
    ])->save();

    $this->assertEquals(0.0, $item->getAnnualDepreciation(2023));
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2024));
    // The disposal year itself still counts (whole-year accounting, no
    // partial-year proration).
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2025));
    // Still inside the original 2024-2026 useful-life window, but the
    // disposal cuts it short.
    $this->assertEquals(0.0, $item->getAnnualDepreciation(2026));
  }

  /**
   * Tests that multiple purchases of the same item are summed independently.
   */
  public function testMultiplePurchasesSummedIndependently(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Hive Boxes',
      'unit' => 'box',
      'item_type' => 'durable',
      'useful_life_years' => 2,
    ]);
    $item->save();

    // Window 2023-2024, contributes 20/2 = 10/year.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2023-01-01',
      'quantity' => 4,
      'unit_price' => 5,
    ])->save();

    // Window 2024-2025, contributes 30/2 = 15/year.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2024-03-01',
      'quantity' => 6,
      'unit_price' => 5,
    ])->save();

    // 2023: only first purchase active.
    $this->assertEquals(10.0, $item->getAnnualDepreciation(2023));
    // 2024: both windows overlap.
    $this->assertEquals(25.0, $item->getAnnualDepreciation(2024));
    // 2025: only second purchase active.
    $this->assertEquals(15.0, $item->getAnnualDepreciation(2025));
    // 2026: both windows closed.
    $this->assertEquals(0.0, $item->getAnnualDepreciation(2026));
  }

  /**
   * Tests that a consumable item never depreciates.
   */
  public function testConsumableItemHasNoDepreciation(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 2,
    ])->save();

    $this->assertEquals(0.0, $item->getAnnualDepreciation(2026));
  }

  /**
   * Tests the report's consumable cost aggregation against a hand-computed total.
   */
  public function testReportAggregatesConsumableCostMatchingHandComputedTotal(): void {
    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create(['name' => 'Report Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();

    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar Syrup',
      'unit' => 'litre',
      'item_type' => 'consumable',
    ]);
    $item->save();

    // Weighted-average unit cost: (10*1.0 + 10*3.0) / 20 = 2.0.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 1,
    ])->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-02-01',
      'quantity' => 10,
      'unit_price' => 3,
    ])->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $calendar_action->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 3,
    ])->save();

    // Report a done log for the current year, using 5 units. Expected
    // cost: 5 * 2.0 (weighted-average snapshot at creation) = 10.0.
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $item->id(), 5);
    $form_object->save([], $form_state);

    $current_year = (int) date('Y');
    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Sugar Syrup', $html);
    $this->assertStringContainsString('10.00', $html);
    $this->assertStringNotContainsString('No inventory cost, depreciation, or yield recorded', $html);
  }

  /**
   * Tests the report's durable-item depreciation breakdown.
   */
  public function testReportIncludesDurableItemDepreciation(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Extractor',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $item->save();

    $current_year = (int) date('Y');
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => $current_year . '-01-01',
      'quantity' => 1,
      'unit_price' => 500,
    ])->save();

    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Extractor', $html);
    // 500 / 5 years = 100.00.
    $this->assertStringContainsString('100.00', $html);
  }

  /**
   * Tests the report's empty state for an apiary with no inventory activity.
   */
  public function testReportEmptyStateWhenNoActivity(): void {
    $current_year = (int) date('Y');
    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('No inventory cost, depreciation, or yield recorded', $html);
    $this->assertStringContainsString('0.00', $html);
  }

  /**
   * Tests that an out-of-range year query parameter falls back to the current year.
   */
  public function testOutOfRangeYearFallsBackToCurrentYear(): void {
    $current_year = (int) date('Y');
    $this->pushRequestWithQuery(['year' => $current_year + 50]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString((string) $current_year, $html);
  }

  /**
   * Tests the report's potential-income aggregation against a hand-computed total.
   */
  public function testReportAggregatesPotentialIncomeMatchingHandComputedTotal(): void {
    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create(['name' => 'Report Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();

    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $product->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest Summer Honey',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();

    CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $product->id(),
      'quantity' => 20,
    ])->save();

    // Report a done log for the current year, producing 15 kg. Expected
    // income: 15 * 12 (expected_unit_price snapshot at creation) = 180.0.
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $product->id(), 15);
    $form_object->save([], $form_state);

    $current_year = (int) date('Y');
    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Honey', $html);
    $this->assertStringContainsString('180.00', $html);
    $this->assertStringNotContainsString('No inventory cost, depreciation, or yield recorded', $html);
  }

  /**
   * Tests that the net figure is a signed negative number for a loss year.
   *
   * Cost exceeds income, so net must render as a negative number rather
   * than being hidden or floored at zero — hiding a loss would be
   * misleading for a profitability report.
   */
  public function testNetFigureIsSignedNegativeForLossYear(): void {
    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $current_year = (int) date('Y');

    // Durable item depreciating 200/year, no yield recorded at all —
    // cost > income (0), so net must be -200.00.
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Extractor',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => $current_year . '-01-01',
      'quantity' => 1,
      'unit_price' => 1000,
    ])->save();

    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // 1000 / 5 years = 200.00 depreciation, 0.00 income, net = -200.00.
    $this->assertStringContainsString('200.00', $html);
    $this->assertStringContainsString('-200.00', $html);
  }

  /**
   * Tests the 5-year trend table across three years of durable-item activity.
   *
   * See docs/project-management/tasks/0044-multi-year-cost-and-income-trend-view.md.
   * Uses durable-item depreciation (rather than usage/yield, which need a
   * full action-log flow) since each item's `useful_life_years = 1`
   * isolates its depreciation to exactly one year, giving deterministic
   * per-year expected totals without extra setup.
   */
  public function testTrendTableShowsThreeYearsOfActivityWithCorrectTotals(): void {
    $current_year = (int) date('Y');

    $item_a = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Trend Item A',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 1,
    ]);
    $item_a->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item_a->id(),
      'purchase_date' => ($current_year - 4) . '-01-01',
      'quantity' => 1,
      'unit_price' => 100,
    ])->save();

    $item_b = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Trend Item B',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 1,
    ]);
    $item_b->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item_b->id(),
      'purchase_date' => ($current_year - 2) . '-01-01',
      'quantity' => 1,
      'unit_price' => 50,
    ])->save();

    $item_c = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Trend Item C',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 1,
    ]);
    $item_c->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item_c->id(),
      'purchase_date' => $current_year . '-01-01',
      'quantity' => 1,
      'unit_price' => 30,
    ])->save();

    $this->pushRequestWithQuery(['year' => $current_year]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->costReport($this->apiary);

    $trend_rows = $build['trend']['table']['#rows'];
    $this->assertCount(6, $trend_rows);

    $by_year = [];
    foreach ($trend_rows as $row) {
      $by_year[$row[0]] = $row;
    }

    // Column order: Year, consumable cost, depreciation, total cost, income, net.
    $this->assertEquals('100.00', $by_year[(string) ($current_year - 4)][2]);
    $this->assertEquals('-100.00', $by_year[(string) ($current_year - 4)][5]);
    $this->assertEquals('50.00', $by_year[(string) ($current_year - 2)][2]);
    $this->assertEquals('-50.00', $by_year[(string) ($current_year - 2)][5]);
    $this->assertEquals('30.00', $by_year[(string) $current_year][2]);
    $this->assertEquals('-30.00', $by_year[(string) $current_year][5]);

    // A year with genuinely no activity still gets a zeroed row, not a
    // skipped one.
    $this->assertEquals('0.00', $by_year[(string) ($current_year - 3)][2]);
    $this->assertEquals('0.00', $by_year[(string) ($current_year - 3)][5]);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('5-Year Trend', $html);
    // Trend Item C's depreciation window covers the selected report year
    // (the current year), so it also appears in the per-item breakdown.
    $this->assertStringContainsString('Trend Item C', $html);
  }

  /**
   * Pushes a GET request with the given query string onto the request stack.
   */
  protected function pushRequestWithQuery(array $query): void {
    $request = Request::create('/hivelog/apiary/' . $this->apiary->id() . '/inventory/cost-report', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

}
