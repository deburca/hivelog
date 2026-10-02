<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the full list pages page over the *filtered* set (task 0170).
 *
 * `HivelogListBuilder::load()` filters and access-checks before slicing to
 * the current page, and the pager links must keep the active filter. Each
 * list's own filter test covers the filter itself; before this class only
 * the action-log lists (task 0168) had a pager test, so a regression where
 * a list paginated the unfiltered set, or where page 2 dropped the filter,
 * would have gone unnoticed on every other list.
 *
 * Every test makes five rows, three matching the filter and two not, and
 * sets the page size to two: page one must hold two matching rows, page two
 * the remaining one (never a non-matching row), and the rendered pager must
 * carry the filter.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ListFilterPaginationTest extends KernelTestBase {

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
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
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
   * Pushes a request onto the stack, routed through the real router.
   */
  protected function pushRoutedRequest(string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Asserts a list pages over its filtered set and keeps the filter in links.
   *
   * @param string $entity_type
   *   The entity type whose list builder is rendered.
   * @param string $path
   *   The collection path.
   * @param array $query
   *   The filter query; its first pair must appear in the pager links.
   * @param int $matching
   *   How many rows match the filter (page size is two).
   */
  protected function assertPagesOverFilteredSet(string $entity_type, string $path, array $query, int $matching): void {
    $list_builder = \Drupal::entityTypeManager()->getListBuilder($entity_type);
    (new \ReflectionProperty($list_builder, 'limit'))->setValue($list_builder, 2);

    $this->pushRoutedRequest($path, $query);
    $build = $list_builder->render();
    $this->assertCount(2, $build['table']['#props']['rows'], 'Page one holds a full page of matching rows.');
    $this->assertSame('pager', $build['pager']['#type']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['pager']);
    $key = array_key_first($query);
    $this->assertStringContainsString($key . '=' . $query[$key], $html, 'Pager links must carry the active filter.');

    $this->pushRoutedRequest($path, $query + ['page' => '1']);
    $build = $list_builder->render();
    $this->assertCount($matching - 2, $build['table']['#props']['rows'], 'Page two holds only the remaining matching rows.');
  }

  /**
   * Tests the Apiaries list.
   */
  public function testApiaries(): void {
    foreach ([1, 2, 3] as $i) {
      Apiary::create(['name' => "Public $i", 'visibility' => 'public'])->save();
    }
    foreach ([1, 2] as $i) {
      Apiary::create(['name' => "Private $i", 'visibility' => 'private'])->save();
    }
    $this->assertPagesOverFilteredSet('apiary', '/hivelog/apiaries', ['visibility' => 'public'], 3);
  }

  /**
   * Tests the Hives list.
   */
  public function testHives(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    foreach ([1, 2, 3] as $i) {
      Hive::create(['name' => "Active $i", 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    }
    foreach ([1, 2] as $i) {
      Hive::create(['name' => "Inactive $i", 'apiary' => $apiary->id(), 'status' => 'inactive'])->save();
    }
    $this->assertPagesOverFilteredSet('hive', '/hivelog/hives', ['status' => 'active'], 3);
  }

  /**
   * Tests the Inspections list.
   */
  public function testInspections(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $date) {
      HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => $date])->save();
    }
    foreach (['2026-01-01', '2026-01-02'] as $date) {
      HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => $date])->save();
    }
    $this->assertPagesOverFilteredSet('hive_inspection', '/hivelog/inspections', ['date_from' => '2026-05-01'], 3);
  }

  /**
   * Tests the Queens list.
   *
   * Filters by breed, not status: saving an active queen demotes any other
   * active queen on the same hive, which would reshuffle the fixture.
   */
  public function testQueens(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    foreach ([1, 2, 3] as $i) {
      Queen::create([
        'name' => "Buck $i",
        'hive' => $hive->id(),
        'queen_year' => 2020 + $i,
        'breed' => 'buckfast',
        'status' => 'inactive',
      ])->save();
    }
    foreach ([1, 2] as $i) {
      Queen::create([
        'name' => "Carni $i",
        'hive' => $hive->id(),
        'queen_year' => 2020 + $i,
        'breed' => 'carniolan',
        'status' => 'inactive',
      ])->save();
    }
    $this->assertPagesOverFilteredSet('queen', '/hivelog/queens', ['breed' => 'buckfast'], 3);
  }

  /**
   * Tests the Inventory Items list.
   */
  public function testInventoryItems(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    foreach ([1, 2, 3] as $i) {
      InventoryItem::create([
        'apiary' => $apiary->id(),
        'name' => "Tool $i",
        'unit' => 'each',
        'item_type' => 'durable',
        'useful_life_years' => 5,
      ])->save();
    }
    foreach ([1, 2] as $i) {
      InventoryItem::create([
        'apiary' => $apiary->id(),
        'name' => "Feed $i",
        'unit' => 'kg',
        'item_type' => 'consumable',
      ])->save();
    }
    $this->assertPagesOverFilteredSet('inventory_item', '/hivelog/inventory-items', ['item_type' => 'durable'], 3);
  }

  /**
   * Tests the Inventory Purchases list.
   */
  public function testInventoryPurchases(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-01-01', '2026-01-02'] as $date) {
      InventoryPurchase::create([
        'apiary' => $apiary->id(),
        'item' => $item->id(),
        'purchase_date' => $date,
        'quantity' => 1,
        'unit_price' => 1,
      ])->save();
    }
    $this->assertPagesOverFilteredSet('inventory_purchase', '/hivelog/inventory-purchases', ['date_from' => '2026-05-01'], 3);
  }

  /**
   * Tests the Products list.
   */
  public function testProducts(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    foreach ([1, 2, 3] as $i) {
      Product::create(['apiary' => $apiary->id(), 'name' => "Live $i", 'unit' => 'kg', 'status' => 'active'])->save();
    }
    foreach ([1, 2] as $i) {
      Product::create(['apiary' => $apiary->id(), 'name' => "Old $i", 'unit' => 'kg', 'status' => 'discontinued'])->save();
    }
    $this->assertPagesOverFilteredSet('product', '/hivelog/products', ['status' => 'active'], 3);
  }

}
