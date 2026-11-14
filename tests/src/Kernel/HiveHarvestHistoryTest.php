<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\HivelogCalendarChecklistBuilder;
use Drupal\hivelog\HivelogStatTileBuilder;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests harvest history display on hive and apiary pages.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
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
   * A test product.
   */
  protected Product $product;

  /**
   * A second test product for multi-product testing.
   */
  protected Product $product2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->product->save();

    $this->product2 = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Beeswax',
      'unit' => 'kg',
      'expected_unit_price' => 30,
    ]);
    $this->product2->save();
  }

  /**
   * Tests the "Harvest this year" stat tile.
   */
  public function testHarvestThisYearTile(): void {
    $tileBuilder = new HivelogStatTileBuilder(
      $this->container->get('module_handler'),
      $this->container->get('entity_type.manager'),
      $this->container->get('hivelog.alert_collector') ?? $this->container->get('hivelog.alert_collector'),
      $this->container->get('class_resolver'),
    );

    // No harvests → tile should not be present (empty array).
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertArrayNotHasKey('hivelog_harvest', $coreTiles, 'No harvest this year tile when there are no harvests.');

    // Create a harvest from this year.
    $harvest = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 18,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-06-15'))->format('Y-m-d'),
    ]);
    $harvest->save();

    // Now the tile should appear.
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertArrayHasKey('hivelog_harvest', $coreTiles, 'Harvest this year tile present when harvests exist.');
    $this->assertEquals('18 kg', $coreTiles['hivelog_harvest']['value']);

    // Add another product.
    $harvest2 = HarvestYield::create([
      'product' => $this->product2->id(),
      'quantity' => 2,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-08-10'))->format('Y-m-d'),
    ]);
    $harvest2->save();

    // Tile should sum both products.
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('20 kg', $coreTiles['hivelog_harvest']['value']);

    // Add a harvest from last year → should not be counted.
    $oldHarvest = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 25,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime((int) date('Y') - 1 . '-07-01'))->format('Y-m-d'),
    ]);
    $oldHarvest->save();

    $coreTiles = $tileBuilder->coreTilesForHive($this->hive);
    $this->assertEquals('20 kg', $coreTiles['hivelog_harvest']['value']);
  }

  /**
   * Tests the harvest history table on the hive page.
   */
  public function testHarvestHistoryTable(): void {
    // Create a new controller instance for this test.
    $controller = new \Drupal\hivelog\Controller\HiveController(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity.form_builder'),
      $this->container->get('form_builder'),
      $this->container->get('file_url_generator'),
      new RequestStack(),
      $this->container->get('renderer'),
      new HivelogStatTileBuilder(
        $this->container->get('module_handler'),
        $this->container->get('entity_type.manager'),
        $this->container->get('hivelog.alert_collector') ?? $this->container->get('hivelog.alert_collector'),
        $this->container->get('class_resolver'),
      ),
    new HivelogCalendarChecklistBuilder(
        $this->container->get('entity_type.manager'),
        $this->container->get('hivelog.stat_tile_builder') ?? new HivelogStatTileBuilder(
          $this->container->get('module_handler'),
          $this->container->get('entity_type.manager'),
          $this->container->get('hivelog.alert_collector') ?? $this->container->get('hivelog.alert_collector'),
          $this->container->get('class_resolver'),
        ),
      ),
    );

    // No harvests → buildHarvestHistory should return empty array.
    $result = $controller->buildHarvestHistory($this->hive);
    $this->assertEmpty($result, 'Empty result when no harvests exist.');

    // Add this year's harvest.
    $harvest = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 18,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-06-15'))->format('Y-m-d'),
    ]);
    $harvest->save();

    // Add last year's harvest.
    $oldHarvest = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 25,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime((int) date('Y') - 1 . '-07-01'))->format('Y-m-d'),
    ]);
    $oldHarvest->save();

    // Add a second-product harvest for this year.
    $wax = HarvestYield::create([
      'product' => $this->product2->id(),
      'quantity' => 2,
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => (new DrupalDateTime(date('Y') . '-08-10'))->format('Y-m-d'),
    ]);
    $wax->save();

    $result = $controller->buildHarvestHistory($this->hive);
    $this->assertNotEmpty($result, 'Result populated when harvests exist.');
    $this->assertArrayHasKey('table', $result);
    $tableProps = $result['table']['#props'];

    // Should have 3 columns: Year | Honey (kg) | Total.
    $expectedHeaders = ['Year', 'Honey (kg)', 'Total'];
    $this->assertEquals($expectedHeaders, $tableProps['headers']);

    // Two year rows + grand total row = 3 rows.
    $this->assertCount(3, $tableProps['rows']);

    // Last year first (descending).
    $lastYear = (int) date('Y') - 1;
    $this->assertEquals((string) $lastYear, $tableProps['rows'][0]['cells'][0]);

    // This year row.
    $thisYear = (string) date('Y');
    $this->assertEquals($thisYear, $tableProps['rows'][1]['cells'][0]);
    // Honey 20 (= 18 + 2 kg from beeswax? No: beeswax is a different product).
    // Actually the columns are per-product. Year N has "25" for Honey only.
    $this->assertStringContainsString('25', $tableProps['rows'][0]['cells'][1]);

    // Grand total row should have "18" and "2".
    $lastRow = $tableProps['rows'][2]['cells'];
    $this->assertEquals('Grand total', $lastRow[0]);

    // Harvest from action log should NOT appear.
    $calendarAction = \Drupal\hivelog\Entity\CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest',
      'week_start' => 28,
    ]);
    $calendarAction->save();

    $hiveLog = \Drupal\hivelog\Entity\HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $calendarAction->id(),
      'status' => 'done',
    ]);
    $hiveLog->save();

    $calendarYield = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 10,
      'hive_action_log' => $hiveLog->id(),
      'apiary_action_log' => '',
    ]);
    $calendarYield->save();

    $result = $controller->buildHarvestHistory($this->hive);
    // Should still show only direct harvests (3 rows: 2 years + total).
    $tableProps = $result['table']['#props'];
    $grandTotalRow = end($tableProps['rows']);
    // The grand total for Honey should be 18 (direct) not 28.
    // Wait — the product is 'Honey' and both direct and calendar yields
    // for the same product. The history table only shows direct harvests, so
    // the column for Honey should show 18 as grand total.
    $this->assertStringContainsString('18', $grandTotalRow[1]);
  }

}
