<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Inventory Item entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryItemTest extends KernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests creating, updating and deleting an inventory item.
   */
  public function testCrud(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Granulated Sugar',
      'category' => 'feed',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $loaded = InventoryItem::load($item->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals('Granulated Sugar', $loaded->label());
    $this->assertEquals('feed', $loaded->get('category')->value);
    $this->assertEquals('kg', $loaded->get('unit')->value);
    $this->assertEquals('consumable', $loaded->get('item_type')->value);

    // Update.
    $item->set('name', 'Granulated Sugar (updated)');
    $item->save();
    $reloaded = InventoryItem::load($item->id());
    $this->assertEquals('Granulated Sugar (updated)', $reloaded->label());

    // Delete.
    $id = $item->id();
    $item->delete();
    $this->assertNull(InventoryItem::load($id));
  }

  /**
   * Tests that `item_type` defaults to `consumable` and `status` to `active`.
   */
  public function testDefaults(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Defaults Test',
      'unit' => 'each',
    ]);
    $this->assertEquals('consumable', $item->get('item_type')->value);
    $this->assertEquals('active', $item->get('status')->value);
    $item->save();
    $loaded = InventoryItem::load($item->id());
    $this->assertEquals('consumable', $loaded->get('item_type')->value);
    $this->assertEquals('active', $loaded->get('status')->value);
  }

  /**
   * Tests that a durable item without a useful life is rejected on save.
   */
  public function testDurableItemRequiresUsefulLife(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
    ]);
    // save() wraps the preSave() InvalidArgumentException in an
    // EntityStorageException, matching CalendarActionTest::
    // testWeekEndBeforeWeekStartRejected's expectation style.
    $this->expectException(\Exception::class);
    $item->save();
  }

  /**
   * Tests that a durable item with a useful life saves successfully.
   */
  public function testDurableItemWithUsefulLifeSaves(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $item->save();
    $this->assertNotNull($item->id());
    $this->assertEquals(5, InventoryItem::load($item->id())->get('useful_life_years')->value);
  }

  /**
   * Tests that a consumable item never requires a useful life.
   */
  public function testConsumableItemDoesNotRequireUsefulLife(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    $this->assertNotNull($item->id());
  }

  /**
   * Tests that `apiary`, `name` and `unit` are required.
   */
  public function testRequiredFields(): void {
    $missing_apiary = InventoryItem::create([
      'name' => 'No Apiary',
      'unit' => 'kg',
    ]);
    $this->assertViolationOnProperty($missing_apiary->validate(), 'apiary');

    $missing_name = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'unit' => 'kg',
    ]);
    $this->assertViolationOnProperty($missing_name->validate(), 'name');

    $missing_unit = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'No Unit',
    ]);
    $this->assertViolationOnProperty($missing_unit->validate(), 'unit');
  }

  /**
   * Tests that `item_type` and `status` only accept real allowed values.
   */
  public function testAllowedValues(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Bad Values',
      'unit' => 'kg',
      'item_type' => 'not_a_real_type',
      'status' => 'not_a_real_status',
    ]);
    $violations = $item->validate();
    $this->assertViolationOnProperty($violations, 'item_type');
    $this->assertViolationOnProperty($violations, 'status');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Valid Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $this->assertCount(0, $item->validate());
  }

  /**
   * Tests that stock on hand sums purchases for a consumable item.
   */
  public function testGetStockOnHandSumsPurchases(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $this->assertEquals(0.0, $item->getStockOnHand());

    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ])->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-06-01',
      'quantity' => 10,
      'unit_price' => 1.6,
    ])->save();

    $this->assertEquals(35.0, InventoryItem::load($item->id())->getStockOnHand());
  }

  /**
   * Tests that stock on hand is NULL for durable items.
   */
  public function testGetStockOnHandNullForDurable(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $item->save();

    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 20,
      'unit_price' => 3,
    ])->save();

    $this->assertNull(InventoryItem::load($item->id())->getStockOnHand());
  }

  /**
   * Tests that stock on hand is NULL for an unsaved item.
   */
  public function testGetStockOnHandNullForUnsavedItem(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Unsaved',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $this->assertNull($item->getStockOnHand());
  }

  /**
   * Tests that the collection page uses the hivelog:entity-table component.
   *
   * Also checks for the self-built "Add Inventory Item" heading.
   */
  public function testCollectionUsesEntityTableWithHeading(): void {
    InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ])->save();

    $build = \Drupal::entityTypeManager()->getListBuilder('inventory_item')->render();

    $this->assertEquals('component', $build['table']['#type']);
    $this->assertEquals('hivelog:entity-table', $build['table']['#component']);
    $this->assertCount(1, $build['table']['#props']['rows']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('hivelog-entity-table', $html);
    $this->assertStringContainsString('Add Inventory Item', $html);
    $this->assertStringContainsString('View Purchases', $html);
  }

  /**
   * Asserts that a constraint violation list has a violation on a property.
   */
  protected function assertViolationOnProperty($violations, string $property_prefix): void {
    $found = FALSE;
    foreach ($violations as $violation) {
      if (str_starts_with((string) $violation->getPropertyPath(), $property_prefix)) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, sprintf('Expected a validation violation on "%s".', $property_prefix));
  }

}
