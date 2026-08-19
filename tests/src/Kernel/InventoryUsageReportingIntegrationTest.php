<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Form\HiveActionLogForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests InventoryUsageFormTrait's integration into HiveActionLogForm.
 *
 * Mirrors HiveActionLogInspectionLinkTest's style — the "create related
 * records as a side effect of saving a done report" pattern that
 * InventoryUsageFormTrait reuses. Uses `administer hivelog` for the test
 * user so these tests focus on the sync logic itself (create vs. update
 * vs. delete quantities), not apiary-membership permission plumbing,
 * which is covered separately by InventoryUsageAccessTest.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryUsageReportingIntegrationTest extends KernelTestBase {

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
   * A test consumable inventory item.
   */
  protected InventoryItem $item;

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
      'title' => 'Varroa Treatment (Spring)',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $this->calendarAction->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Apivar Strips',
      'unit' => 'strip',
      'item_type' => 'consumable',
    ]);
    $this->item->save();

    CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $this->item->id(),
      'quantity' => 2,
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

    $field_name = 'inventory_usage_' . $this->item->id();
    $this->assertArrayHasKey($field_name, $build);
    $this->assertEquals(2, $build[$field_name]['#default_value']);
    $this->assertEquals('strip', $build[$field_name]['#field_suffix']);
  }

  /**
   * Tests that a durable item's requirement never gets a usage field.
   */
  public function testDurableItemRequirementHasNoUsageField(): void {
    $durable = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $durable->save();
    CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $durable->id(),
      'quantity' => 10,
    ])->save();

    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log, 'add');

    $this->assertArrayNotHasKey('inventory_usage_' . $durable->id(), $build);
  }

  /**
   * Tests that saving a done report creates a usage row from the quantity.
   */
  public function testDoneReportCreatesUsageRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 2);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $usage_ids = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $usage_ids);
    $usage = \Drupal::entityTypeManager()->getStorage('inventory_usage')->load(reset($usage_ids));
    $this->assertEquals($this->item->id(), $usage->get('item')->target_id);
    $this->assertEquals(2, $usage->get('quantity')->value);
  }

  /**
   * Tests that a zero submitted quantity never creates a usage record.
   */
  public function testZeroQuantityCreatesNoUsageRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 0);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $usage_count = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $usage_count);
  }

  /**
   * Tests that a pending report never creates a usage record.
   *
   * Holds true even if a quantity is somehow submitted.
   */
  public function testPendingReportNeverCreatesUsageRecord(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'pending',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 2);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $usage_count = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $usage_count);
  }

  /**
   * Tests that re-saving a done report updates usage in place, not twice.
   */
  public function testResavingUpdatesUsageInPlace(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 2);
    $form_object->save([], $form_state);
    $saved_log = $form_object->getEntity();

    // Re-edit with a different quantity.
    $edit_form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'edit');
    $edit_form_object->setEntity(HiveActionLog::load($saved_log->id()));
    $edit_form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 5);
    $edit_form_object->save([], $edit_form_state);

    $usage_ids = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $usage_ids);
    $usage = \Drupal::entityTypeManager()->getStorage('inventory_usage')->load(reset($usage_ids));
    $this->assertEquals(5, $usage->get('quantity')->value);
  }

  /**
   * Tests that changing status away from done removes recorded usage.
   */
  public function testChangingStatusAwayFromDoneRemovesUsage(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 2);
    $form_object->save([], $form_state);
    $saved_log = $form_object->getEntity();

    // Re-edit, changing status to ignored.
    $reloaded = HiveActionLog::load($saved_log->id());
    $reloaded->set('status', 'ignored');
    $edit_form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'edit');
    $edit_form_object->setEntity($reloaded);
    $edit_form_state = new FormState();
    $edit_form_object->save([], $edit_form_state);

    $usage_count = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive_action_log', $saved_log->id())
      ->count()
      ->execute();
    $this->assertEquals(0, $usage_count);
  }

  /**
   * Tests that an ApiaryActionLog links usage via apiary_action_log.
   *
   * Rather than hive_action_log, since this is the apiary-scoped log type.
   */
  public function testApiaryActionLogDoneReportCreatesUsageRecord(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('apiary_action_log', 'add');
    $form_object->setEntity($log);
    $form_state = (new FormState())->setValue('inventory_usage_' . $this->item->id(), 4);
    $form_object->save([], $form_state);

    $saved_log = $form_object->getEntity();
    $usage_ids = \Drupal::entityTypeManager()->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary_action_log', $saved_log->id())
      ->execute();
    $this->assertCount(1, $usage_ids);
    $usage = \Drupal::entityTypeManager()->getStorage('inventory_usage')->load(reset($usage_ids));
    $this->assertEquals(4, $usage->get('quantity')->value);
    $this->assertTrue($usage->get('hive_action_log')->isEmpty());
  }

}
