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

    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('20 kg', $coreTiles['hivelog_harvest']['value']);

    // Last year harvest (should NOT be counted).
    HarvestYield::create([
      'product' => $this->honeyProduct->id(),
      'quantity' => 25,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime((int) date('Y') - 1 . '-07-01'))->format('Y-m-d'),
    ])->save();

    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('20 kg', $coreTiles['hivelog_harvest']['value']);
  }

}
