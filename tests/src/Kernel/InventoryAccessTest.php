<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for InventoryItem and InventoryPurchase.
 *
 * Mirrors ApiaryScopedAccessTest's style, scoped to the two entities added
 * by task 0029: InventoryItem (owner-only delete, mirroring
 * CalendarAction — foundational apiary structure) and InventoryPurchase
 * (owner-or-creator delete, mirroring HiveActionLog — a per-transaction
 * log).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryAccessTest extends KernelTestBase {

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
   * The apiary owner user.
   */
  protected User $owner;

  /**
   * A beekeeper member of the apiary.
   */
  protected User $beekeeper;

  /**
   * A user with no access to the apiary.
   */
  protected User $outsider;

  /**
   * The test apiary (private).
   */
  protected Apiary $apiary;

  /**
   * A test inventory item on the test apiary.
   */
  protected InventoryItem $item;

  /**
   * A test purchase, created by the beekeeper.
   */
  protected InventoryPurchase $purchase;

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

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own inventory item');
    $role->grantPermission('edit own inventory item');
    $role->grantPermission('delete own inventory item');
    $role->grantPermission('add inventory item');
    $role->grantPermission('view own inventory purchase');
    $role->grantPermission('edit own inventory purchase');
    $role->grantPermission('delete own inventory purchase');
    $role->grantPermission('add inventory purchase');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $this->beekeeper->addRole('beekeeper');
    $this->beekeeper->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$this->beekeeper->id()],
    ]);
    $this->apiary->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $this->item->save();

    $this->purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 10,
      'unit_price' => 2,
      'uid' => $this->beekeeper->id(),
    ]);
    $this->purchase->save();
  }

  // -----------------------------------------------------------------------
  // InventoryItem access follows apiary scope (inventory_item → apiary
  // directly). Delete is owner-only, mirroring CalendarAction — a catalog
  // entry is foundational apiary structure, not a per-transaction log.
  // -----------------------------------------------------------------------

  /**
   * Tests that the owner can view an inventory item.
   */
  public function testOwnerCanViewInventoryItem(): void {
    $this->assertTrue($this->item->access('view', $this->owner));
  }

  /**
   * Tests that the owner can edit an inventory item.
   */
  public function testOwnerCanEditInventoryItem(): void {
    $this->assertTrue($this->item->access('update', $this->owner));
  }

  /**
   * Tests that the owner can delete an inventory item.
   */
  public function testOwnerCanDeleteInventoryItem(): void {
    $this->assertTrue($this->item->access('delete', $this->owner));
  }

  /**
   * Tests that a beekeeper can view an inventory item.
   */
  public function testBeekeeperCanViewInventoryItem(): void {
    $this->assertTrue($this->item->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit an inventory item.
   */
  public function testBeekeeperCanEditInventoryItem(): void {
    $this->assertTrue($this->item->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper cannot delete an inventory item (owner-only).
   */
  public function testBeekeeperCannotDeleteInventoryItem(): void {
    $this->assertFalse($this->item->access('delete', $this->beekeeper));
  }

  /**
   * Tests that an outsider cannot view a private apiary's inventory item.
   */
  public function testOutsiderCannotViewPrivateInventoryItem(): void {
    $this->assertFalse($this->item->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's inventory item.
   */
  public function testOutsiderCanViewPublicInventoryItem(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->item->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider cannot edit a public apiary's inventory item.
   */
  public function testOutsiderCannotEditPublicInventoryItem(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertFalse($this->item->access('update', $this->outsider));
  }

  // -----------------------------------------------------------------------
  // InventoryPurchase access follows apiary scope (inventory_purchase →
  // apiary directly). Delete is owner-or-creator, mirroring
  // HiveActionLog — a purchase is a per-transaction log.
  // -----------------------------------------------------------------------

  /**
   * Tests that the owner can view a purchase.
   */
  public function testOwnerCanViewPurchase(): void {
    $this->assertTrue($this->purchase->access('view', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a purchase.
   */
  public function testBeekeeperCanViewPurchase(): void {
    $this->assertTrue($this->purchase->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a purchase.
   */
  public function testBeekeeperCanEditPurchase(): void {
    $this->assertTrue($this->purchase->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can delete their own purchase.
   */
  public function testBeekeeperCanDeleteOwnPurchase(): void {
    // $this->purchase was created with uid = beekeeper.
    $this->assertTrue($this->purchase->access('delete', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper cannot delete another user's purchase.
   */
  public function testBeekeeperCannotDeleteOthersPurchase(): void {
    $owner_purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->item->id(),
      'purchase_date' => '2026-03-02',
      'quantity' => 5,
      'unit_price' => 2,
      'uid' => $this->owner->id(),
    ]);
    $owner_purchase->save();
    $this->assertFalse($owner_purchase->access('delete', $this->beekeeper));
  }

  /**
   * Tests that the apiary owner can delete any purchase.
   */
  public function testOwnerCanDeleteAnyPurchase(): void {
    $this->assertTrue($this->purchase->access('delete', $this->owner));
  }

  /**
   * Tests that an outsider cannot view a private apiary's purchase.
   */
  public function testOutsiderCannotViewPrivatePurchase(): void {
    $this->assertFalse($this->purchase->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's purchase.
   */
  public function testOutsiderCanViewPublicPurchase(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->purchase->access('view', $this->outsider));
  }

}
