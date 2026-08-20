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
 * End-to-end integration backstop for the yield/income feature (task 0039).
 *
 * Threads scenarios across every task in
 * [[honey-wax-propolis-yield-and-potential-income]] — plan (recipe)
 * through actual (yield) through the read-side report — the kind of
 * coverage gap that only shows up at the integration boundary between
 * tasks 0035-0038, rather than from any single task's own kernel tests
 * in isolation. Mirrors InventoryEndToEndTest's own framing exactly.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class YieldEndToEndTest extends KernelTestBase {

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
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('harvest_yield');
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
   * Tests that yield, and the cost report's income total, agree on a pre-filled report.
   *
   * Declares a recipe expecting 20 kg of honey, reads the form's own
   * pre-filled default (rather than hardcoding it, so this genuinely
   * exercises "accepted as-is"), reports the log `done` submitting
   * exactly that default, and asserts the resulting `HarvestYield`
   * row's quantity and income agree with the cost report's potential
   * income total.
   */
  public function testYieldAndIncomeReportAgreeOnPreFilledQuantity(): void {
    $apiary = Apiary::create(['name' => 'Yield End To End Apiary']);
    $apiary->save();

    $hive = Hive::create(['name' => 'Yield End To End Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $product->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
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

    // Read the form's own pre-filled default rather than hardcoding it —
    // this is what makes the scenario "accepted as-is".
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'add');
    $field_name = 'harvest_yield_' . $product->id();
    $prefilled_quantity = $build[$field_name]['#default_value'];
    $this->assertEquals(20, $prefilled_quantity);

    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue($field_name, $prefilled_quantity);
    $form_object->save([], $form_state);
    $saved_log = HiveActionLog::load($form_object->getEntity()->id());

    // Fact 1: the HarvestYield row.
    $yield_ids = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $yield_ids);
    $yield = \Drupal::entityTypeManager()->getStorage('harvest_yield')->load(reset($yield_ids));
    $this->assertEquals(20, $yield->get('quantity')->value);
    $this->assertEquals(12.0, (float) $yield->get('unit_price_snapshot')->value);
    $yield_income = (float) $yield->get('quantity')->value * (float) $yield->get('unit_price_snapshot')->value;
    $this->assertEquals(240.0, $yield_income);

    // Fact 2: the cost report's potential-income total, via the real
    // controller.
    $current_year = (int) date('Y');
    $request = Request::create('/hivelog/apiary/' . $apiary->id() . '/inventory/cost-report', 'GET', ['year' => $current_year]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $report_build = $controller->costReport($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($report_build);

    $this->assertStringContainsString(number_format($yield_income, 2), $html);
  }

  /**
   * Tests that a combined jars+honey "done" report updates stock and income together.
   *
   * The one scenario no single task 0035-0038 tests end-to-end on its
   * own: a calendar action with both a `CalendarActionItemRequirement`
   * (jars needed) and a `CalendarActionProductYield` (honey produced),
   * reported `done` together in one save. Asserts both an
   * `InventoryUsage` row and a `HarvestYield` row are created from the
   * same submission, and that `InventoryItem::getStockOnHand()` and the
   * cost report's income figure both reflect it.
   */
  public function testUsageAndYieldTogetherReflectInStockOnHandAndIncome(): void {
    $apiary = Apiary::create(['name' => 'Combined End To End Apiary']);
    $apiary->save();

    $hive = Hive::create(['name' => 'Combined End To End Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $jars = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => '500g Honey Jars',
      'unit' => 'jar',
      'item_type' => 'consumable',
    ]);
    $jars->save();

    $honey = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $honey->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Harvest Summer Honey',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $jars->id(),
      'quantity' => 40,
    ])->save();
    CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $honey->id(),
      'quantity' => 20,
    ])->save();

    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())
      ->setValue('inventory_usage_' . $jars->id(), 40)
      ->setValue('harvest_yield_' . $honey->id(), 20);
    $form_object->save([], $form_state);
    $saved_log = HiveActionLog::load($form_object->getEntity()->id());

    // Both records exist, from the one submission.
    $usage_ids = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $usage_ids);

    $yield_ids = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $yield_ids);
    $yield = \Drupal::entityTypeManager()->getStorage('harvest_yield')->load(reset($yield_ids));
    $yield_income = (float) $yield->get('quantity')->value * (float) $yield->get('unit_price_snapshot')->value;
    $this->assertEquals(240.0, $yield_income);

    // Stock on hand reflects the jars consumed — no purchases were ever
    // recorded, so it's negative, but that's the correct arithmetic
    // (Σ purchases − Σ usage) regardless of sign.
    $this->assertEquals(-40.0, InventoryItem::load($jars->id())->getStockOnHand());

    // The cost report reflects the honey income figure.
    $current_year = (int) date('Y');
    $request = Request::create('/hivelog/apiary/' . $apiary->id() . '/inventory/cost-report', 'GET', ['year' => $current_year]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $report_build = $controller->costReport($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($report_build);

    $this->assertStringContainsString(number_format($yield_income, 2), $html);
    $this->assertStringContainsString('Honey', $html);
    $this->assertStringContainsString('500g Honey Jars', $html);
  }

}
