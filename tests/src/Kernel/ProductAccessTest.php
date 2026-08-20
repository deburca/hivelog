<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for Product.
 *
 * Mirrors InventoryAccessTest's InventoryItem-half exactly: delete is
 * owner-only, mirroring InventoryItem/CalendarAction — a catalog entry is
 * foundational apiary structure, not a per-transaction log.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ProductAccessTest extends KernelTestBase {

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
   * A test product on the test apiary.
   */
  protected Product $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own product');
    $role->grantPermission('edit own product');
    $role->grantPermission('delete own product');
    $role->grantPermission('add product');
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

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $this->owner->id(),
    ]);
    $this->product->save();
  }

  /**
   * Tests that the owner can view a product.
   */
  public function testOwnerCanViewProduct(): void {
    $this->assertTrue($this->product->access('view', $this->owner));
  }

  /**
   * Tests that the owner can edit a product.
   */
  public function testOwnerCanEditProduct(): void {
    $this->assertTrue($this->product->access('update', $this->owner));
  }

  /**
   * Tests that the owner can delete a product.
   */
  public function testOwnerCanDeleteProduct(): void {
    $this->assertTrue($this->product->access('delete', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a product.
   */
  public function testBeekeeperCanViewProduct(): void {
    $this->assertTrue($this->product->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a product.
   */
  public function testBeekeeperCanEditProduct(): void {
    $this->assertTrue($this->product->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper cannot delete a product (owner-only).
   */
  public function testBeekeeperCannotDeleteProduct(): void {
    $this->assertFalse($this->product->access('delete', $this->beekeeper));
  }

  /**
   * Tests that an outsider cannot view a private apiary's product.
   */
  public function testOutsiderCannotViewPrivateProduct(): void {
    $this->assertFalse($this->product->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's product.
   */
  public function testOutsiderCanViewPublicProduct(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->product->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider cannot edit a public apiary's product.
   */
  public function testOutsiderCannotEditPublicProduct(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertFalse($this->product->access('update', $this->outsider));
  }

}
