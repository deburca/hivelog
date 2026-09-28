<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
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
 * Tests filters on the Inventory Items, Purchases and Products lists.
 *
 * Task 0156: `/hivelog/inventory-items`, `/hivelog/inventory-purchases`
 * and `/hivelog/products` (previously unfiltered) now carry
 * `HivelogInventoryItemFilterForm` / `HivelogInventoryPurchaseFilterForm`
 * / `HivelogProductFilterForm` — all full-page-only, mirroring task
 * 0132/0155's own established shape.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryProductFilterTest extends KernelTestBase {

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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request onto the stack, routed as `$route_name`.
   *
   * Matches the request through the real router first, so
   * `current_route_match` (and therefore `Url::fromRoute('<current>')`,
   * which Reset relies on) resolves to this route — a bare
   * `Request::create()` alone leaves no route attributes set.
   */
  protected function pushRoutedRequest(string $route_name, string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests the Inventory Items list filters by item type and Reset clears it.
   */
  public function testInventoryItemListTypeFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ])->save();
    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Extractor',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ])->save();

    $this->pushRoutedRequest('entity.inventory_item.collection', '/hivelog/inventory-items', ['item_type' => 'durable']);
    $build = \Drupal::entityTypeManager()->getListBuilder('inventory_item')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Extractor', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/inventory-items', $reset_url);
    $this->assertStringNotContainsString('item_type', $reset_url);
  }

  /**
   * Tests the Inventory Items list's empty state distinguishes filtered.
   */
  public function testInventoryItemListEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ])->save();

    $this->pushRoutedRequest('entity.inventory_item.collection', '/hivelog/inventory-items', ['name' => 'Nonexistent']);
    $build = \Drupal::entityTypeManager()->getListBuilder('inventory_item')->render();

    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
  }

  /**
   * Tests the Inventory Purchases list filters by date range and Reset.
   */
  public function testInventoryPurchaseListDateFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-15',
      'quantity' => 10,
      'unit_price' => 1.5,
      'supplier' => 'Early Supplier',
    ])->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-06-15',
      'quantity' => 20,
      'unit_price' => 1.5,
      'supplier' => 'Late Supplier',
    ])->save();

    $this->pushRoutedRequest('entity.inventory_purchase.collection', '/hivelog/inventory-purchases', ['date_from' => '2026-05-01']);
    $build = \Drupal::entityTypeManager()->getListBuilder('inventory_purchase')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/inventory-purchases', $reset_url);
    $this->assertStringNotContainsString('date_from', $reset_url);
  }

  /**
   * Tests the Inventory Purchases list filters by supplier.
   */
  public function testInventoryPurchaseListSupplierFilter(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-15',
      'quantity' => 10,
      'unit_price' => 1.5,
      'supplier' => 'Acme Feed Co',
    ])->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-16',
      'quantity' => 10,
      'unit_price' => 1.5,
      'supplier' => 'Other Supplier',
    ])->save();

    $this->pushRoutedRequest('entity.inventory_purchase.collection', '/hivelog/inventory-purchases', ['supplier' => 'Acme']);
    $build = \Drupal::entityTypeManager()->getListBuilder('inventory_purchase')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
  }

  /**
   * Tests the Products list filters by status and Reset clears it.
   */
  public function testProductListStatusFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'status' => 'active',
    ])->save();
    Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Old Wax',
      'unit' => 'kg',
      'status' => 'discontinued',
    ])->save();

    $this->pushRoutedRequest('entity.product.collection', '/hivelog/products', ['status' => 'active']);
    $build = \Drupal::entityTypeManager()->getListBuilder('product')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Honey', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/products', $reset_url);
    $this->assertStringNotContainsString('status', $reset_url);
  }

  /**
   * Extracts the rendered Reset button's URL from a list builder's build.
   */
  protected function resetUrl(array $build): string {
    $reset = $build['filter']['filter_actions']['reset']['#props']['url'] ?? NULL;
    $this->assertNotNull($reset, 'Reset button was not rendered.');
    return $reset;
  }

}
