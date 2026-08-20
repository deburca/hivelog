<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
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
    $this->assertStringNotContainsString('No inventory cost or depreciation recorded', $html);
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

    $this->assertStringContainsString('No inventory cost or depreciation recorded', $html);
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
   * Pushes a GET request with the given query string onto the request stack.
   */
  protected function pushRequestWithQuery(array $query): void {
    $request = Request::create('/hivelog/apiary/' . $this->apiary->id() . '/inventory/cost-report', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

}
