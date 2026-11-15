<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\user\Entity\User;
use Drupal\hivelog\HivelogAlertCollector;
use Drupal\hivelog\HivelogStatTileBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests inspection-lapse alerts and the "Last inspected" tile (#0273).
 */
#[RunTestsInSeparateProcesses]
class HiveInspectionAlertTest extends KernelTestBase {

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
   * Test apiary.
   */
  protected Apiary $apiary;

  /**
   * Test hive (active, with recent inspection).
   */
  protected Hive $hive_recent;

  /**
   * Test hive (active, overdue inspection).
   */
  protected Hive $hive_overdue;

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
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('product');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('hive_component');
    $this->installSchema('file', ['file_usage']);

    // Admin user for access checks.
    $admin = User::create([
      'name' => 'test-admin',
      'mail' => 'admin@test.com',
      'status' => 1,
    ]);
    $admin->save();
    $admin->addRole('administrator');
    \Drupal::currentUser()->setAccount($admin);

    // Apiary during the inspection season (weeks 14-40).
    $this->apiary = Apiary::create(['name' => 'TestApiary']);
    $this->apiary->save();

    // Hive with a recent inspection.
    $this->hive_recent = Hive::create([
      'name' => 'Recent Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive_recent->save();

    // Hive with an overdue inspection.
    $this->hive_overdue = Hive::create([
      'name' => 'Overdue Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive_overdue->save();

    // Insert a recent inspection (5 days ago).
    $recent_date = (new DrupalDateTime())->modify('-5 days')->format('Y-m-d');
    HiveInspection::create([
      'hive' => $this->hive_recent->id(),
      'inspection_date' => $recent_date,
    ])->save();

    // Insert an overdue inspection (20 days ago).
    $overdue_date = (new DrupalDateTime())->modify('-20 days')->format('Y-m-d');
    HiveInspection::create([
      'hive' => $this->hive_overdue->id(),
      'inspection_date' => $overdue_date,
    ])->save();

    // Inject the current date/week into this test class so we can control it.
    // We override time() in PHP via @runInSeparateProcess won't work;
    // instead we use reflection to inject a fake date into DrupalDateTime.
    // For simplicity, store the expected "now" timestamp.
    $this->fake_now = (new DrupalDateTime('2026-07-01'))->getTimestamp();
  }

  /**
   * Fake current timestamp for controlling "current week" logic.
   */
  protected int $fake_now = 0;

  /**
   * Cleans up test data.
   */
  protected function tearDown(): void {
    if (isset($this->hive_recent)) {
      // Delete inspections first.
      $storage = \Drupal::entityTypeManager()->getStorage('hive_inspection');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('hive', $this->hive_recent->id())->execute();
      if ($ids) { $storage->delete($storage->loadMultiple($ids)); }
    }
    if (isset($this->hive_overdue)) {
      $storage = \Drupal::entityTypeManager()->getStorage('hive_inspection');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('hive', $this->hive_overdue->id())->execute();
      if ($ids) { $storage->delete($storage->loadMultiple($ids)); }
    }
    if (isset($this->hive_recent)) { $this->hive_recent->delete(); }
    if (isset($this->hive_overdue)) { $this->hive_overdue->delete(); }
    if (isset($this->apiary)) { $this->apiary->delete(); }
    parent::tearDown();
  }

  /**
   * Tests the "Last inspected" tile on the hive page.
   */
  public function testLastInspectedTile(): void {
    $tileBuilder = new HivelogStatTileBuilder(
      $this->container->get('module_handler'),
      $this->container->get('entity_type.manager'),
      \Drupal::service('hivelog.alert_collector'),
      $this->container->get('class_resolver'),
    );

    // Collect core tiles for the recent hive, during week 27 (July, in season).
    $coreTiles = $tileBuilder->coreTilesForHive($this->hive_recent);

    // The tile should be present because we're running within weeks 14-40.
    $this->assertArrayHasKey('hivelog_last_inspected', $coreTiles,
      'Last inspected tile present for active hive during season.');
    // The value should be the inspection date.
    $this->assertNotEmpty($coreTiles['hivelog_last_inspected']['value']);

    // Overdue hive — tile should have a warning sub-line.
    $coreTiles_overdue = $tileBuilder->coreTilesForHive($this->hive_overdue);
    $this->assertArrayHasKey('hivelog_last_inspected', $coreTiles_overdue,
      'Last inspected tile present for overdue hive during season.');
    // 20 days ago > 14-day threshold → warning sub-line.
    $this->assertArrayHasKey('sublabel_variant', $coreTiles_overdue['hivelog_last_inspected'],
      'Overdue hive has a warning variant on the last inspected tile.');
    $this->assertEquals('warning', $coreTiles_overdue['hivelog_last_inspected']['sublabel_variant']);
  }

  /**
   * Tests inspection-lapse alerts in the alert collector.
   */
  public function testInspectionAlerts(): void {
    // Collect alerts for our apiary during season (week ~27).
    $collector = new HivelogAlertCollector(
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      \Drupal::service('string_translation'),
    );
    $cache = new CacheableMetadata();
    $apiaries = [$this->apiary->id() => $this->apiary];

    // Use reflection to inject our fake "now" for controlling current_week.
    // We can't easily change PHP's time(), but the collector uses (int) date('W').
    // July 1 2026 = ISO week 27, which is inside the season (14-40).

    $collected = $collector->collect($apiaries, 2026, 27, $cache);
    $alerts = $collected['alerts'];

    // Should find at least one inspection-lapse alert.
    $inspection_alerts = array_filter($alerts, fn($a) => ($a['kind'] ?? '') === 'inspection_lapse');
    $this->assertNotEmpty($inspection_alerts,
      'At least one inspection-lapse alert present during season');

    // The overdue hive should generate an alert.
    $overdue_found = FALSE;
    foreach ($inspection_alerts as $alert) {
      if (($alert['subject']['hive'] ?? NULL)->id() === $this->hive_overdue->id()) {
        $overdue_found = TRUE;
        break;
      }
    }
    $this->assertTrue($overdue_found, 'Overdue hive generates an inspection-lapse alert.');

    // The recent hive (5 days ago) should NOT generate an alert.
    foreach ($inspection_alerts as $alert) {
      if (($alert['subject']['hive'] ?? NULL) === $this->hive_recent) {
        $this->fail('Recent hive should not generate an inspection-lapse alert.');
      }
    }

    // Each inspection-lapse row should have kind and subject.
    foreach ($inspection_alerts as $alert) {
      $this->assertEquals('inspection_lapse', $alert['kind']);
      $this->assertArrayHasKey('hive', $alert['subject']);
      $this->assertArrayHasKey('apiary', $alert['subject']);
    }
  }

  /**
   * Tests that no inspection-lapse alert is generated outside the season.
   */
  public function testInspectionAlertsOutsideSeason(): void {
    $collector = new HivelogAlertCollector(
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      \Drupal::service('string_translation'),
    );
    $cache = new CacheableMetadata();
    $apiaries = [$this->apiary->id() => $this->apiary];

    // Week 5 (early February) — outside the season.
    $collected = $collector->collect($apiaries, 2026, 5, $cache);
    $alerts = $collected['alerts'];

    $inspection_alerts = array_filter($alerts, fn($a) => ($a['kind'] ?? '') === 'inspection_lapse');
    $this->assertEmpty($inspection_alerts,
      'No inspection-lapse alerts outside the inspection season.');
  }

}
