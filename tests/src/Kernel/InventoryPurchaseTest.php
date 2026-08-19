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
 * Tests the Inventory Purchase entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryPurchaseTest extends KernelTestBase {

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
   * A test inventory item, belonging to `$apiary`.
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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Granulated Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $this->item->save();
  }

  /**
   * Tests creating, updating and deleting an inventory purchase.
   */
  public function testCrud(): void {
    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
      'supplier' => 'Local Feed Store',
    ]);
    $purchase->save();

    $loaded = InventoryPurchase::load($purchase->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals($this->item->id(), $loaded->get('item')->target_id);
    $this->assertEquals('2026-03-01', $loaded->get('purchase_date')->value);
    $this->assertEquals(25, $loaded->get('quantity')->value);
    $this->assertEquals(1.5, $loaded->get('unit_price')->value);
    $this->assertEquals('Local Feed Store', $loaded->get('supplier')->value);
    $this->assertStringContainsString('Granulated Sugar', (string) $loaded->label());

    // Update.
    $purchase->set('quantity', 30);
    $purchase->save();
    $reloaded = InventoryPurchase::load($purchase->id());
    $this->assertEquals(30, $reloaded->get('quantity')->value);

    // Delete.
    $id = $purchase->id();
    $purchase->delete();
    $this->assertNull(InventoryPurchase::load($id));
  }

  /**
   * Tests that `total_cost` is auto-derived as quantity × unit_price.
   */
  public function testTotalCostAutoDerivation(): void {
    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $purchase->save();

    $this->assertEquals(37.5, (float) InventoryPurchase::load($purchase->id())->get('total_cost')->value);

    // Re-derived on update too.
    $purchase->set('quantity', 10);
    $purchase->save();
    $this->assertEquals(15.0, (float) InventoryPurchase::load($purchase->id())->get('total_cost')->value);
  }

  /**
   * Tests that a purchase's item must belong to the same apiary.
   */
  public function testItemMustBelongToSameApiary(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();

    $purchase = InventoryPurchase::create([
      'apiary' => $other_apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $this->expectException(\InvalidArgumentException::class);
    $purchase->save();
  }

  /**
   * Tests that the core required fields are enforced.
   *
   * `apiary`, `item`, `purchase_date`, `quantity` and `unit_price` must
   * all be set.
   */
  public function testRequiredFields(): void {
    $missing_apiary = InventoryPurchase::create([
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $this->assertViolationOnProperty($missing_apiary->validate(), 'apiary');

    $missing_item = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $this->assertViolationOnProperty($missing_item->validate(), 'item');

    $missing_date = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $this->assertViolationOnProperty($missing_date->validate(), 'purchase_date');

    $missing_quantity = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'unit_price' => 1.5,
    ]);
    $this->assertViolationOnProperty($missing_quantity->validate(), 'quantity');

    $missing_price = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
    ]);
    $this->assertViolationOnProperty($missing_price->validate(), 'unit_price');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $this->assertCount(0, $purchase->validate());
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
