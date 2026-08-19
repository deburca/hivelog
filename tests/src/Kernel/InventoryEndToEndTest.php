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
 * End-to-end integration backstop for the inventory feature (task 0033).
 *
 * Threads a single scenario across every task in
 * [[inventory-tracking-and-depreciation]] — plan (recipe) through actual
 * (usage) through the read-side report — the kind of coverage gap that
 * only shows up at the integration boundary between tasks 0028-0032,
 * rather than from any single task's own kernel tests in isolation.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryEndToEndTest extends KernelTestBase {

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
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Tests that usage, stock, and the cost report all agree on a pre-filled report.
   *
   * Purchases 20 units of a consumable at two prices (weighted-average
   * unit cost 2.0), declares a recipe requiring 5 units, reads the
   * form's own pre-filled default (rather than hardcoding it, so this
   * genuinely exercises "accepted as-is"), reports the log `done`
   * submitting exactly that default, and asserts three independently
   * computed facts agree: the resulting `InventoryUsage` row's quantity
   * and cost, `InventoryItem::getStockOnHand()`, and the cost report's
   * consumable total.
   */
  public function testUsageStockAndCostReportAgreeOnPreFilledQuantity(): void {
    $apiary = Apiary::create(['name' => 'End To End Apiary']);
    $apiary->save();

    $hive = Hive::create(['name' => 'End To End Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Apivar Strips',
      'unit' => 'strip',
      'item_type' => 'consumable',
    ]);
    $item->save();

    // Weighted-average unit cost: (10*1.5 + 10*2.5) / 20 = 2.0.
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 1.5,
    ])->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-02-01',
      'quantity' => 10,
      'unit_price' => 2.5,
    ])->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Varroa Treatment (Spring)',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $calendar_action->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 5,
    ])->save();

    // Read the form's own pre-filled default rather than hardcoding it —
    // this is what makes the scenario "accepted as-is".
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'add');
    $field_name = 'inventory_usage_' . $item->id();
    $prefilled_quantity = $build[$field_name]['#default_value'];
    $this->assertEquals(5, $prefilled_quantity);

    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue($field_name, $prefilled_quantity);
    $form_object->save([], $form_state);
    $saved_log = HiveActionLog::load($form_object->getEntity()->id());

    // Fact 1: the InventoryUsage row.
    $usage_ids = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $usage_ids);
    $usage = \Drupal::entityTypeManager()->getStorage('inventory_usage')->load(reset($usage_ids));
    $this->assertEquals(5, $usage->get('quantity')->value);
    $this->assertEquals(2.0, (float) $usage->get('unit_cost_snapshot')->value);
    $usage_cost = (float) $usage->get('quantity')->value * (float) $usage->get('unit_cost_snapshot')->value;
    $this->assertEquals(10.0, $usage_cost);

    // Fact 2: stock on hand — 20 purchased minus 5 used.
    $reloaded_item = InventoryItem::load($item->id());
    $this->assertEquals(15.0, $reloaded_item->getStockOnHand());

    // Fact 3: the cost report's consumable total, via the real controller.
    $current_year = (int) date('Y');
    $request = Request::create('/hivelog/apiary/' . $apiary->id() . '/inventory/cost-report', 'GET', ['year' => $current_year]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $report_build = $controller->costReport($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($report_build);

    $this->assertStringContainsString(number_format($usage_cost, 2), $html);
  }

  /**
   * Tests durable-item depreciation across both edges of its useful life.
   *
   * Complements InventoryCostReportTest's boundary coverage with a
   * standalone, purchase-only scenario matching the task's acceptance
   * criterion wording directly: non-zero within the window, exactly zero
   * outside it, at both the start and end boundary.
   */
  public function testDepreciationIsNonZeroWithinLifeAndZeroOutsideAtBothBoundaries(): void {
    $apiary = Apiary::create(['name' => 'Depreciation Apiary']);
    $apiary->save();

    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey Extractor',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 4,
    ]);
    $item->save();

    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2025-06-01',
      'quantity' => 1,
      'unit_price' => 800,
    ])->save();

    // Window is 2025-2028 (4 years). 800 / 4 = 200/year while active.
    $this->assertEquals(0.0, $item->getAnnualDepreciation(2024), 'Year before the window must be zero.');
    $this->assertGreaterThan(0.0, $item->getAnnualDepreciation(2025), 'Start-of-window year must be non-zero.');
    $this->assertEquals(200.0, $item->getAnnualDepreciation(2025));
    $this->assertGreaterThan(0.0, $item->getAnnualDepreciation(2028), 'End-of-window year must still be non-zero.');
    $this->assertEquals(200.0, $item->getAnnualDepreciation(2028));
    $this->assertEquals(0.0, $item->getAnnualDepreciation(2029), 'Year after the window must be zero.');
  }

}
