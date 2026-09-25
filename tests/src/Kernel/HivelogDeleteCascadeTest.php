<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryUsage;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hivelog core's CASCADE rows (task 0142).
 *
 * Row #7/#8/#13/#27/#28 (submodule) are covered by nanoprobe's and
 * nexus's own kernel tests instead — this class covers only the rows
 * `HivelogDeleteDependencyRegistry` declares itself: #2, #15, #16,
 * #19a/#19b, #20a/#20b.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogDeleteCascadeTest extends KernelTestBase {

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
  }

  /**
   * A fresh apiary.
   */
  protected function createApiary(): Apiary {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    return $apiary;
  }

  /**
   * A fresh calendar action on `$apiary`.
   */
  protected function createCalendarAction(Apiary $apiary): CalendarAction {
    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Test Calendar Action',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $calendar_action->save();
    return $calendar_action;
  }

  // -------------------------------------------------------------------------
  // Row #2: Apiary -> CalendarAction.
  // -------------------------------------------------------------------------

  /**
   * Deleting an apiary cascades its calendar actions.
   */
  public function testApiaryDeleteCascadesCalendarActions(): void {
    $apiary = $this->createApiary();
    $action_a = $this->createCalendarAction($apiary);
    $action_b = $this->createCalendarAction($apiary);

    $apiary->delete();

    $this->assertNull(CalendarAction::load($action_a->id()));
    $this->assertNull(CalendarAction::load($action_b->id()));
  }

  /**
   * Deleting one apiary leaves another apiary's calendar actions alone.
   */
  public function testCascadeLeavesUnrelatedRecordsUntouched(): void {
    $apiary_a = $this->createApiary();
    $apiary_b = $this->createApiary();
    $action_a = $this->createCalendarAction($apiary_a);
    $action_b = $this->createCalendarAction($apiary_b);

    $apiary_a->delete();

    $this->assertNull(CalendarAction::load($action_a->id()));
    $this->assertNotNull(CalendarAction::load($action_b->id()), "Another apiary's calendar action must survive.");
  }

  // -------------------------------------------------------------------------
  // Rows #15/#16: CalendarAction -> CalendarActionItemRequirement/
  // CalendarActionProductYield, and chaining from #2.
  // -------------------------------------------------------------------------

  /**
   * Deleting a calendar action cascades its requirements and yields.
   */
  public function testCalendarActionDeleteCascadesRequirementsAndYields(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $product->save();
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 2,
    ]);
    $requirement->save();
    $yield = CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $product->id(),
      'quantity' => 10,
    ]);
    $yield->save();

    $calendar_action->delete();

    $this->assertNull(CalendarActionItemRequirement::load($requirement->id()));
    $this->assertNull(CalendarActionProductYield::load($yield->id()));
    // Neither is itself CASCADE-registered against anything — the item
    // and product they reference are a different registry row entirely
    // and must survive.
    $this->assertNotNull(InventoryItem::load($item->id()));
    $this->assertNotNull(Product::load($product->id()));
  }

  /**
   * Deleting an apiary chains through to a calendar action's own cascade.
   */
  public function testApiaryDeleteChainsThroughCalendarActionToRequirementsAndYields(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 2,
    ]);
    $requirement->save();

    $apiary->delete();

    $this->assertNull(CalendarAction::load($calendar_action->id()));
    $this->assertNull(
      CalendarActionItemRequirement::load($requirement->id()),
      'Deleting the apiary must chain through the cascaded calendar action to its own cascaded requirement.'
    );
  }

  // -------------------------------------------------------------------------
  // Rows #19a/#19b, #20a/#20b: {Hive,Apiary}ActionLog -> InventoryUsage/
  // HarvestYield.
  // -------------------------------------------------------------------------

  /**
   * Deleting a hive action log cascades its usage and yield records.
   */
  public function testHiveActionLogDeleteCascadesUsageAndYield(): void {
    $apiary = $this->createApiary();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $calendar_action = $this->createCalendarAction($apiary);
    $log = HiveActionLog::create(['hive' => $hive->id(), 'calendar_action' => $calendar_action->id()]);
    $log->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $product->save();
    $usage = InventoryUsage::create(['item' => $item->id(), 'quantity' => 2, 'hive_action_log' => $log->id()]);
    $usage->save();
    $yield = HarvestYield::create(['product' => $product->id(), 'quantity' => 5, 'hive_action_log' => $log->id()]);
    $yield->save();

    $log->delete();

    $this->assertNull(InventoryUsage::load($usage->id()));
    $this->assertNull(HarvestYield::load($yield->id()));
  }

  /**
   * Deleting an apiary action log cascades its usage and yield records.
   */
  public function testApiaryActionLogDeleteCascadesUsageAndYield(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $log = ApiaryActionLog::create(['apiary' => $apiary->id(), 'calendar_action' => $calendar_action->id()]);
    $log->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $product->save();
    $usage = InventoryUsage::create(['item' => $item->id(), 'quantity' => 2, 'apiary_action_log' => $log->id()]);
    $usage->save();
    $yield = HarvestYield::create(['product' => $product->id(), 'quantity' => 5, 'apiary_action_log' => $log->id()]);
    $yield->save();

    $log->delete();

    $this->assertNull(InventoryUsage::load($usage->id()));
    $this->assertNull(HarvestYield::load($yield->id()));
  }

  // -------------------------------------------------------------------------
  // Safety invariant.
  // -------------------------------------------------------------------------

  /**
   * A cascade never touches a BLOCK row's own child type.
   *
   * Deletes an apiary directly via the entity API — bypassing the form/
   * route access check task 0141 relies on to keep a BLOCK row's
   * children (here, the apiary's own Hive) from ever coexisting with a
   * completed delete — to prove the invariant holds structurally, not
   * just because the normal path happens to prevent it: CASCADE only
   * ever processes rows registered as CASCADE, so a BLOCK-registered
   * child type is never in its dispatch at all.
   */
  public function testCascadeNeverTouchesBlockRegisteredChildren(): void {
    $apiary = $this->createApiary();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $calendar_action = $this->createCalendarAction($apiary);

    $apiary->delete();

    $this->assertNull(CalendarAction::load($calendar_action->id()), 'The CASCADE row must still run.');
    $this->assertNotNull(
      Hive::load($hive->id()),
      "The BLOCK row's own child type (Hive) must never be touched by cascade, even on a delete that bypassed BLOCK access."
    );
  }

}
