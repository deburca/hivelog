<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\InventoryUsage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Inventory Usage entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryUsageTest extends KernelTestBase {

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
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * A test calendar action, belonging to `$apiary`.
   */
  protected CalendarAction $calendarAction;

  /**
   * A test hive action log against `$calendarAction`.
   */
  protected HiveActionLog $hiveActionLog;

  /**
   * A test consumable inventory item, belonging to `$apiary`.
   */
  protected InventoryItem $item;

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
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installSchema('file', ['file_usage']);

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

    $this->hiveActionLog = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $this->hiveActionLog->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Apivar Strips',
      'unit' => 'strip',
      'item_type' => 'consumable',
    ]);
    $this->item->save();
  }

  /**
   * Tests creating, updating and deleting a usage record.
   */
  public function testCrud(): void {
    $usage = InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 2,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $usage->save();

    $loaded = InventoryUsage::load($usage->id());
    $this->assertEquals($this->item->id(), $loaded->get('item')->target_id);
    $this->assertEquals(2, $loaded->get('quantity')->value);
    $this->assertEquals($this->hiveActionLog->id(), $loaded->get('hive_action_log')->target_id);
    $this->assertTrue($loaded->get('apiary_action_log')->isEmpty());
    $this->assertStringContainsString('Apivar Strips', (string) $loaded->label());
    $this->assertStringContainsString('2', (string) $loaded->label());
    $this->assertStringContainsString('strip', (string) $loaded->label());

    // Update.
    $usage->set('quantity', 3);
    $usage->save();
    $this->assertEquals(3, InventoryUsage::load($usage->id())->get('quantity')->value);

    // Delete.
    $id = $usage->id();
    $usage->delete();
    $this->assertNull(InventoryUsage::load($id));
  }

  /**
   * Tests that exactly one of hive_action_log/apiary_action_log is required.
   */
  public function testExactlyOneLogReferenceRequired(): void {
    $neither = InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 2,
    ]);
    $this->expectException(\Exception::class);
    $neither->save();
  }

  /**
   * Tests that setting both log references is rejected.
   */
  public function testBothLogReferencesRejected(): void {
    $apiary_action_log = \Drupal::entityTypeManager()->getStorage('apiary_action_log')->create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $apiary_action_log->save();

    $both = InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 2,
      'hive_action_log' => $this->hiveActionLog->id(),
      'apiary_action_log' => $apiary_action_log->id(),
    ]);
    $this->expectException(\Exception::class);
    $both->save();
  }

  /**
   * Tests that a durable item is rejected — only consumables can be used.
   */
  public function testDurableItemRejected(): void {
    $durable = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $durable->save();

    $usage = InventoryUsage::create([
      'item' => $durable->id(),
      'quantity' => 2,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $this->expectException(\Exception::class);
    $usage->save();
  }

  /**
   * Tests that `unit_cost_snapshot` is a weighted average of purchases.
   */
  public function testUnitCostSnapshotIsWeightedAverage(): void {
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 1.0,
    ])->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-02-01',
      'quantity' => 30,
      'unit_price' => 2.0,
    ])->save();
    // Weighted average: (10*1.0 + 30*2.0) / (10+30) = 70/40 = 1.75.

    $usage = InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 2,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $usage->save();

    $this->assertEquals(1.75, (float) InventoryUsage::load($usage->id())->get('unit_cost_snapshot')->value);
  }

  /**
   * Tests that the cost snapshot survives a later edit and new purchases.
   */
  public function testUnitCostSnapshotIsImmutableAfterCreation(): void {
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 1.0,
    ])->save();

    $usage = InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 2,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $usage->save();
    $this->assertEquals(1.0, (float) InventoryUsage::load($usage->id())->get('unit_cost_snapshot')->value);

    // A new, much more expensive purchase should not retroactively change
    // the already-recorded snapshot.
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 10,
      'unit_price' => 100.0,
    ])->save();

    $usage->set('quantity', 5);
    $usage->save();
    $this->assertEquals(1.0, (float) InventoryUsage::load($usage->id())->get('unit_cost_snapshot')->value);
  }

  /**
   * Tests that `InventoryItem::getStockOnHand()` reflects recorded usage.
   */
  public function testStockOnHandReflectsUsage(): void {
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 100,
      'unit_price' => 1.0,
    ])->save();

    InventoryUsage::create([
      'item' => $this->item->id(),
      'quantity' => 12,
      'hive_action_log' => $this->hiveActionLog->id(),
    ])->save();

    $this->assertEquals(88.0, InventoryItem::load($this->item->id())->getStockOnHand());
  }

}
