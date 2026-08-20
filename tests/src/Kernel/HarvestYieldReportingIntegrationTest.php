<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
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
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
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

}
