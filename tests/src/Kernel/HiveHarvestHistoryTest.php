<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Product;
use Drupal\user\Entity\User;
use Drupal\hivelog\HivelogStatTileBuilder;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\HivelogHarvestTotals;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests harvest history display on hive and apiary pages.
 */
#[Group('hivelog')]
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class HiveHarvestHistoryTest extends KernelTestBase {

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
   * Test honey product entity.
   */
  protected Product $honeyProduct;

  /**
   * Test beeswax product entity.
   */
  protected Product $beeswaxProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_reminder');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('hive_component');
    $this->installEntitySchema('calendar_action');
    // The hive's tiles read its inspections (task 0273), and the hive page,
    // rendered whole below, reads the rest.
    foreach ([
      'hive_inspection', 'inventory_item', 'inventory_purchase', 'inventory_usage',
      'calendar_action_item_requirement', 'calendar_action_product_yield', 'apiary_action_log',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // Create and set an admin user.
    $admin = User::create([
      'name' => 'test-admin',
      'mail' => 'admin@test.com',
      'status' => 1,
    ]);
    $admin->save();
    $admin->addRole('administrator');
    \Drupal::currentUser()->setAccount($admin);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    // Honey first, then Beeswax.
    $this->honeyProduct = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->honeyProduct->save();

    $this->beeswaxProduct = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Beeswax',
      'unit' => 'kg',
      'expected_unit_price' => 30,
    ]);
    $this->beeswaxProduct->save();
  }

  /**
   * Cleans up test data left by this setUp.
   */
  protected function tearDown(): void {
    // Delete entities created in this setUp.
    if (isset($this->honeyProduct)) {
      $this->honeyProduct->delete();
    }
    if (isset($this->beeswaxProduct)) {
      $this->beeswaxProduct->delete();
    }

    // Delete all harvest yields on our apiary.
    if (isset($this->apiary) && $this->apiary->id()) {
      $storage = \Drupal::entityTypeManager()->getStorage('harvest_yield');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('apiary', $this->apiary->id())->execute();
      if ($ids) {
        $entities = $storage->loadMultiple($ids);
        $storage->delete($entities);
      }

      // Delete hive and apiary.
      if (isset($this->hive)) {
        $this->hive->delete();
      }
      $this->apiary->delete();
    }

    parent::tearDown();
  }

  /**
   * Tests the "Harvest this year" stat tile.
   */
  public function testHarvestThisYearTile(): void {
    $tileBuilder = new HivelogStatTileBuilder(
      $this->container->get('module_handler'),
      $this->container->get('entity_type.manager'),
      \Drupal::service('hivelog.alert_collector'),
      $this->container->get('class_resolver'),
    );

    // No harvests → tile absent.
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertArrayNotHasKey('hivelog_harvest', $coreTiles,
      'No harvest this year tile when there are no harvests.');

    // One harvest this year.
    HarvestYield::create([
      'product' => $this->honeyProduct->id(),
      'quantity' => 18,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-06-15'))->format('Y-m-d'),
    ])->save();

    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertArrayHasKey('hivelog_harvest', $coreTiles,
      'Harvest this year tile present when harvests exist.');
    $this->assertEquals('18 kg', $coreTiles['hivelog_harvest']['value']);

    // Second product.
    HarvestYield::create([
      'product' => $this->beeswaxProduct->id(),
      'quantity' => 2,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-08-10'))->format('Y-m-d'),
    ])->save();

    // Honey and beeswax are not added together, although both are in kg
    // (task 0284): the tile shows the main product and names it.
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('18 kg', $coreTiles['hivelog_harvest']['value']);
    $this->assertEquals('Honey · +1 other product', (string) $coreTiles['hivelog_harvest']['sublabel']);

    // Last year harvest (should NOT be counted).
    HarvestYield::create([
      'product' => $this->honeyProduct->id(),
      'quantity' => 25,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime((int) date('Y') - 1 . '-07-01'))->format('Y-m-d'),
    ])->save();

    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('18 kg', $coreTiles['hivelog_harvest']['value']);
  }

  /**
   * Records a direct harvest on the test hive.
   */
  protected function harvest(Product $product, float $quantity, string $date): void {
    HarvestYield::create([
      'product' => $product->id(),
      'quantity' => $quantity,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => $date,
    ])->save();
  }

  /**
   * A single product is named in the tile's sub-line.
   */
  public function testTileNamesItsProduct(): void {
    $this->harvest($this->honeyProduct, 7.5, date('Y') . '-06-15');
    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($this->hive)['hivelog_harvest'];

    $this->assertSame('7.5 kg', $tile['value']);
    $this->assertSame('Honey', (string) $tile['sublabel']);
  }

  /**
   * Products in different units are never added together (task 0284).
   */
  public function testProductsInDifferentUnitsAreNotSummed(): void {
    $comb = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Cut comb',
      'unit' => 'jar',
      'expected_unit_price' => 8,
    ]);
    $comb->save();
    $year = (int) date('Y');
    // Honey: 10 kg at 12 = 120. Comb: 4 jars at 8 = 32. Honey is the main
    // product; "14 kg" would be nonsense.
    $this->harvest($this->honeyProduct, 10, $year . '-06-15');
    $this->harvest($comb, 4, $year . '-07-01');
    $this->harvest($comb, 2, ($year - 1) . '-07-01');

    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($this->hive)['hivelog_harvest'];
    $this->assertSame('10 kg', $tile['value']);
    $this->assertSame('Honey · +1 other product', (string) $tile['sublabel']);

    $totals = HivelogHarvestTotals::perProduct(\Drupal::entityTypeManager(), $this->hive);
    $this->assertSame(['honey|kg', 'cut comb|jar'], array_keys($totals));
    $this->assertSame(10.0, $totals['honey|kg']['quantity']);
    $this->assertSame(6.0, $totals['cut comb|jar']['quantity']);
    $this->assertSame([(string) $year => 4.0, (string) ($year - 1) => 2.0], $totals['cut comb|jar']['by_year']);
  }

  /**
   * The history table has a column per product and no cross-product total.
   */
  public function testHistoryTableHasNoCrossProductTotal(): void {
    $comb = Product::create(['apiary' => $this->apiary->id(), 'name' => 'Cut comb', 'unit' => 'jar']);
    $comb->save();
    $year = (int) date('Y');
    $this->harvest($this->honeyProduct, 10, $year . '-06-15');
    $this->harvest($this->honeyProduct, 5, ($year - 1) . '-06-15');
    $this->harvest($comb, 4, $year . '-07-01');

    $request = Request::create('/hivelog/hive/' . $this->hive->id());
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $build = \Drupal::service('class_resolver')->getInstanceFromDefinition(HiveController::class)->view($this->hive);
    $table = $build['harvest_history']['table']['#props'];

    $this->assertSame(['Year', 'Honey (kg)', 'Cut comb (jar)'], $table['headers']);
    $this->assertSame([
      [(string) $year, '10 kg', '4 jar'],
      [(string) ($year - 1), '5 kg', ''],
      ['All years', '15 kg', '4 jar'],
    ], array_column($table['rows'], 'cells'));
  }

  /**
   * Two products of the same name and unit are one column.
   */
  public function testSameNameAndUnitIsOneProduct(): void {
    $other = Product::create(['apiary' => $this->apiary->id(), 'name' => 'honey', 'unit' => 'kg']);
    $other->save();
    $this->harvest($this->honeyProduct, 10, date('Y') . '-06-15');
    $this->harvest($other, 3, date('Y') . '-07-15');

    $totals = HivelogHarvestTotals::perProduct(\Drupal::entityTypeManager(), $this->hive);
    $this->assertCount(1, $totals);
    $this->assertSame(13.0, reset($totals)['quantity']);
  }

}
