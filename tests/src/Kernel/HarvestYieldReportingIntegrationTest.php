<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Form\HiveActionLogForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests HarvestYieldFormTrait's integration into HiveActionLogForm.
 *
 * Mirrors InventoryUsageReportingIntegrationTest's style exactly, one
 * level removed (outputs instead of inputs). Uses `administer hivelog`
 * for the test user so these tests focus on the sync logic itself, not
 * apiary-membership permission plumbing, which is covered separately by
 * HarvestYieldAccessTest.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HarvestYieldReportingIntegrationTest extends KernelTestBase {

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
   * A test hive.
   */
  protected Hive $hive;

  /**
   * A test calendar action.
   */
  protected CalendarAction $calendarAction;

  /**
   * A test product.
   */
  protected Product $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('hive_reminder');
    $this->installEntityType('hive_reminder');
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest Summer Honey',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $this->calendarAction->save();

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->product->save();

    CalendarActionProductYield::create([
      'calendar_action' => $this->calendarAction->id(),
      'product' => $this->product->id(),
      'quantity' => 20,
    ])->save();
  }

  /**
   * Builds a HiveActionLogForm instance with the given entity attached.
   */
  protected function buildFormObject(HiveActionLog $log): HiveActionLogForm {
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    return $form_object;
  }

  /**
   * Tests that the built form pre-fills the field from the recipe quantity.
   */
  public function testFormPreFillsFieldFromRecipe(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'add');

    $field_name = 'harvest_yield_' . $this->product->id();
    $this->assertArrayHasKey($field_name, $build);
    $this->assertEquals(20, $build[$field_name]['#default_value']);
    $this->assertEquals('kg', $build[$field_name]['#field_suffix']);
  }

  /**
   * Tests that saving a done report creates a yield row from the quantity.
   */
  public function testDoneReportCreatesYieldRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 20);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $yield_ids = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $yield_ids);
    $yield = \Drupal::entityTypeManager()->getStorage('harvest_yield')->load(reset($yield_ids));
    $this->assertEquals($this->product->id(), $yield->get('product')->target_id);
    $this->assertEquals(20, $yield->get('quantity')->value);
  }

  /**
   * Tests that a zero submitted quantity never creates a yield record.
   */
  public function testZeroQuantityCreatesNoYieldRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 0);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $yield_count = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $yield_count);
  }

  /**
   * Tests that a pending report never creates a yield record.
   *
   * Holds true even if a quantity is somehow submitted.
   */
  public function testPendingReportNeverCreatesYieldRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'pending',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 20);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $yield_count = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $yield_count);
  }

  /**
   * Tests that re-saving a done report updates yield in place, not twice.
   */
  public function testResavingUpdatesYieldInPlace(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 20);
    $form_object->save([], $form_state);
    $saved_log = $form_object->getEntity();

    // Re-edit with a different quantity.
    $edit_form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'edit');
    $edit_form_object->setEntity(HiveActionLog::load($saved_log->id()));
    $edit_form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 25);
    $edit_form_object->save([], $edit_form_state);

    $yield_ids = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $yield_ids);
    $yield = \Drupal::entityTypeManager()->getStorage('harvest_yield')->load(reset($yield_ids));
    $this->assertEquals(25, $yield->get('quantity')->value);
  }

  /**
   * Tests that changing status away from done removes recorded yield.
   */
  public function testChangingStatusAwayFromDoneRemovesYield(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 20);
    $form_object->save([], $form_state);
    $saved_log = $form_object->getEntity();

    // Re-edit, changing status to ignored.
    $reloaded = HiveActionLog::load($saved_log->id());
    $reloaded->set('status', 'ignored');
    $edit_form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'edit');
    $edit_form_object->setEntity($reloaded);
    $edit_form_state = new FormState();
    $edit_form_object->save([], $edit_form_state);

    $yield_count = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $yield_count);
  }

  /**
   * Tests that an ApiaryActionLog links yield via apiary_action_log.
   *
   * Rather than hive_action_log, since this is the apiary-scoped log type.
   */
  public function testApiaryActionLogDoneReportCreatesYieldRecord(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('apiary_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue('harvest_yield_' . $this->product->id(), 30);
    $form_object->save([], $form_state);

    $saved_log = $form_object->getEntity();
    $yield_ids = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $yield_ids);
    $yield = \Drupal::entityTypeManager()->getStorage('harvest_yield')->load(reset($yield_ids));
    $this->assertEquals(30, $yield->get('quantity')->value);
    $this->assertTrue($yield->get('hive_action_log')->isEmpty());
  }

  /**
   * Tests that inventory-usage and yield fields both appear on the same form.
   *
   * A harvest action can need items (jars) and yield products (honey) at
   * once — both InventoryUsageFormTrait's and HarvestYieldFormTrait's
   * fields must coexist without collision.
   */
  public function testInventoryUsageAndYieldFieldsBothAppearOnSameForm(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => '500g Honey Jars',
      'unit' => 'jar',
      'item_type' => 'consumable',
    ]);
    $item->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $item->id(),
      'quantity' => 40,
    ])->save();

    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'add');

    $this->assertArrayHasKey('inventory_usage_' . $item->id(), $build);
    $this->assertArrayHasKey('harvest_yield_' . $this->product->id(), $build);
    $this->assertEquals(40, $build['inventory_usage_' . $item->id()]['#default_value']);
    $this->assertEquals(20, $build['harvest_yield_' . $this->product->id()]['#default_value']);
  }

  /**
   * Tests that a single "done" save creates both usage and yield records.
   */
  public function testDoneReportCreatesBothUsageAndYieldRecords(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => '500g Honey Jars',
      'unit' => 'jar',
      'item_type' => 'consumable',
    ]);
    $item->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $item->id(),
      'quantity' => 40,
    ])->save();

    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())
      ->setValue('inventory_usage_' . $item->id(), 40)
      ->setValue('harvest_yield_' . $this->product->id(), 20);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());

    $usage_count = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(1, $usage_count);

    $yield_count = \Drupal::entityTypeManager()->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(1, $yield_count);
  }

  /**
   * Loads the harvests of a log, keyed by product id.
   *
   * @return \Drupal\hivelog\Entity\HarvestYield[]
   *   The harvests.
   */
  protected function harvestsOf(HiveActionLog $log): array {
    $by_product = [];
    foreach (HarvestYield::loadMultiple() as $harvest) {
      if ((int) $harvest->get('hive_action_log')->target_id === (int) $log->id()) {
        $by_product[(int) $harvest->get('product')->target_id] = $harvest;
      }
    }
    return $by_product;
  }

  /**
   * Creates another product in the test apiary.
   */
  protected function otherProduct(string $name = 'Propolis', string $unit = 'g', array $values = []): Product {
    $product = Product::create($values + ['apiary' => $this->apiary->id(), 'name' => $name, 'unit' => $unit]);
    $product->save();
    return $product;
  }

  /**
   * A new done log for the test action.
   */
  protected function newLog(?CalendarAction $action = NULL): HiveActionLog {
    return HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => ($action ?? $this->calendarAction)->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ]);
  }

  /**
   * The block has a date, and offers the apiary's other active products.
   *
   * Task 0287.
   */
  public function testBlockHasDateAndOffersOtherProducts(): void {
    $propolis = $this->otherProduct();
    $retired = $this->otherProduct('Old mead', 'bottle', ['status' => 'discontinued']);
    $elsewhere = Apiary::create(['name' => 'Other Apiary']);
    $elsewhere->save();
    $foreign = Product::create(['apiary' => $elsewhere->id(), 'name' => 'Their honey', 'unit' => 'kg']);
    $foreign->save();

    $build = \Drupal::service('entity.form_builder')->getForm($this->newLog(), 'add');

    $this->assertSame(date('Y-m-d'), $build['harvest_yield_date']['#default_value']);
    $options = $build['harvest_yield_new_product']['#options'];
    $this->assertArrayHasKey($propolis->id(), $options);
    $this->assertSame('Propolis (g)', (string) $options[$propolis->id()]);
    // Not the product already expected, a discontinued one, or another
    // apiary's.
    $this->assertArrayNotHasKey($this->product->id(), $options);
    $this->assertArrayNotHasKey($retired->id(), $options);
    $this->assertArrayNotHasKey($foreign->id(), $options);
    $this->assertArrayHasKey('harvest_yield_new_quantity', $build);
  }

  /**
   * A product the action did not plan for can be recorded, with the date.
   */
  public function testUnplannedProductCanBeRecorded(): void {
    $propolis = $this->otherProduct();
    $form_object = $this->buildFormObject($this->newLog());
    $form_state = (new FormState())
      ->setValue('harvest_yield_' . $this->product->id(), 18)
      ->setValue('harvest_yield_new_product', $propolis->id())
      ->setValue('harvest_yield_new_quantity', 250)
      ->setValue('harvest_yield_date', date('Y') . '-08-03');
    $form_object->save([], $form_state);

    $log = HiveActionLog::load($form_object->getEntity()->id());
    $harvests = $this->harvestsOf($log);
    $this->assertCount(2, $harvests);
    $this->assertEquals(250, (float) $harvests[(int) $propolis->id()]->get('quantity')->value);
    foreach ($harvests as $harvest) {
      $this->assertSame(date('Y') . '-08-03', $harvest->get('harvest_date')->value);
      $this->assertEquals($this->hive->id(), $harvest->get('hive')->target_id);
      $this->assertEquals($this->apiary->id(), $harvest->get('apiary')->target_id);
    }

    // On the edit form the unplanned product has a field of its own, with
    // what was recorded, and the block keeps the harvest's date.
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'edit');
    $this->assertEquals(250, (float) $build['harvest_yield_' . $propolis->id()]['#default_value']);
    $this->assertSame(date('Y') . '-08-03', $build['harvest_yield_date']['#default_value']);
    $this->assertArrayNotHasKey($propolis->id(), $build['harvest_yield_new_product']['#options'] ?? []);

    // Changing the date moves every row; a zero removes the unplanned one.
    $edit_form = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'edit');
    $edit_form->setEntity($log);
    $edit_form->save([], (new FormState())
      ->setValue('harvest_yield_' . $this->product->id(), 18)
      ->setValue('harvest_yield_' . $propolis->id(), 0)
      ->setValue('harvest_yield_date', date('Y') . '-08-10'));
    \Drupal::entityTypeManager()->getStorage('harvest_yield')->resetCache();
    $harvests = $this->harvestsOf($log);
    $this->assertSame([(int) $this->product->id()], array_keys($harvests));
    $this->assertSame(date('Y') . '-08-10', $harvests[(int) $this->product->id()]->get('harvest_date')->value);
  }

  /**
   * A product that is not one of the apiary's active products is ignored.
   */
  public function testProductFromElsewhereIsIgnored(): void {
    $elsewhere = Apiary::create(['name' => 'Other Apiary']);
    $elsewhere->save();
    $foreign = Product::create(['apiary' => $elsewhere->id(), 'name' => 'Their honey', 'unit' => 'kg']);
    $foreign->save();

    $form_object = $this->buildFormObject($this->newLog());
    $form_object->save([], (new FormState())
      ->setValue('harvest_yield_' . $this->product->id(), 0)
      ->setValue('harvest_yield_new_product', $foreign->id())
      ->setValue('harvest_yield_new_quantity', 5));

    $this->assertSame([], $this->harvestsOf(HiveActionLog::load($form_object->getEntity()->id())));
  }

  /**
   * A harvest action with no expected harvest still offers the block.
   *
   * An action that is not a harvest and expects none does not.
   */
  public function testBlockNeedsNoExpectedHarvestOnHarvestAction(): void {
    $harvest_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Take the ivy honey',
      'week_start' => 42,
      'category' => 'harvest_summer',
    ]);
    $harvest_action->save();
    $other_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Mouse guards',
      'week_start' => 42,
      'category' => 'winter_prep',
    ]);
    $other_action->save();

    $build = \Drupal::service('entity.form_builder')->getForm($this->newLog($harvest_action), 'add');
    $this->assertArrayHasKey('harvest_yield_fieldset', $build);
    $this->assertArrayHasKey($this->product->id(), $build['harvest_yield_new_product']['#options']);

    $form_object = $this->buildFormObject($this->newLog($harvest_action));
    $form_object->save([], (new FormState())
      ->setValue('harvest_yield_new_product', $this->product->id())
      ->setValue('harvest_yield_new_quantity', 9));
    $harvests = $this->harvestsOf(HiveActionLog::load($form_object->getEntity()->id()));
    $this->assertEquals(9, (float) $harvests[(int) $this->product->id()]->get('quantity')->value);

    $build = \Drupal::service('entity.form_builder')->getForm($this->newLog($other_action), 'add');
    $this->assertArrayNotHasKey('harvest_yield_fieldset', $build);
  }

}
