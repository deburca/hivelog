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
  protected Hive $hiveRecent;

  /**
   * Test hive (active, overdue inspection).
   */
  protected Hive $hiveOverdue;

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
    $this->hiveRecent = Hive::create([
      'name' => 'Recent Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hiveRecent->save();

    // Hive with an overdue inspection.
    $this->hiveOverdue = Hive::create([
      'name' => 'Overdue Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hiveOverdue->save();

    // Insert a recent inspection (5 days ago).
    $recent_date = (new DrupalDateTime())->modify('-5 days')->format('Y-m-d');
    HiveInspection::create([
      'hive' => $this->hiveRecent->id(),
      'inspection_date' => $recent_date,
    ])->save();

    // Insert an overdue inspection (20 days ago).
    $overdue_date = (new DrupalDateTime())->modify('-20 days')->format('Y-m-d');
    HiveInspection::create([
      'hive' => $this->hiveOverdue->id(),
      'inspection_date' => $overdue_date,
    ])->save();
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

    // The tile is shown whenever the hive has an inspection, in or out of
    // season; only the alert is seasonal.
    $coreTiles = $tileBuilder->coreTilesForHive($this->hiveRecent);
    $this->assertArrayHasKey('hivelog_last_inspected', $coreTiles,
      'Last inspected tile present for a hive with an inspection.');
    $this->assertArrayNotHasKey('sublabel_variant', $coreTiles['hivelog_last_inspected'],
      'An inspection 5 days ago is not overdue.');
    // The value should be the inspection date.
    $this->assertNotEmpty($coreTiles['hivelog_last_inspected']['value']);

    // Overdue hive — tile should have a warning sub-line.
    $overdueTiles = $tileBuilder->coreTilesForHive($this->hiveOverdue);
    $this->assertArrayHasKey('hivelog_last_inspected', $overdueTiles,
      'Last inspected tile present for the overdue hive.');
    // 20 days ago > 14-day threshold → warning sub-line.
    $this->assertArrayHasKey('sublabel_variant', $overdueTiles['hivelog_last_inspected'],
      'Overdue hive has a warning variant on the last inspected tile.');
    $this->assertEquals('warning', $overdueTiles['hivelog_last_inspected']['sublabel_variant']);
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

    // Week 27 is inside the season (14 to 40). The collector takes the week
    // as an argument; the age of an inspection is measured from today, and
    // the fixtures are dated relative to today.
    $collected = $collector->collect($apiaries, 2026, 27, $cache);
    $alerts = $collected['alerts'];

    // Should find at least one inspection-lapse alert.
    $inspection_alerts = array_filter($alerts, fn($a) => ($a['kind'] ?? '') === 'inspection_lapse');
    $this->assertNotEmpty($inspection_alerts,
      'At least one inspection-lapse alert present during season');

    // The overdue hive should generate an alert.
    $overdue_found = FALSE;
    foreach ($inspection_alerts as $alert) {
      if ($alert['subject']['hive']->id() === $this->hiveOverdue->id()) {
        $overdue_found = TRUE;
        break;
      }
    }
    $this->assertTrue($overdue_found, 'Overdue hive generates an inspection-lapse alert.');

    // The recent hive (5 days ago) should NOT generate an alert.
    foreach ($inspection_alerts as $alert) {
      if ($alert['subject']['hive']->id() === $this->hiveRecent->id()) {
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
