<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests sensor-driven "Needs attention" alert rules (task 0081).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorAlertCollectorTest extends KernelTestBase {

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
    'nanoprobe',
  ];

  /**
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('product');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('sensor_device');
    $this->installEntitySchema('sensor_reading');
    $this->installSchema('file', ['file_usage']);

    // The first user created in a kernel test is uid 1 (the superuser),
    // matching hivelog core's own DashboardTest::makeCurrentUser()
    // convention — bypasses per-entity access filtering so tests focus
    // on the alert *rules*, not access parity (not required by this
    // task's own acceptance criteria; SensorPanelBuilderTest already
    // covers access parity for the sibling Sensors panel).
    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $user->id()]);
    $this->apiary->save();
    // Apiary::postSave() seeds 31 calendar actions — irrelevant noise
    // for these sensor-only tests.
    $calendar_action_storage = \Drupal::entityTypeManager()->getStorage('calendar_action');
    if ($all = $calendar_action_storage->loadMultiple()) {
      $calendar_action_storage->delete($all);
    }

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $user->id(),
    ]);
    $this->hive->save();
  }

  /**
   * Creates a sensor device attached to `$this->hive`.
   */
  protected function createDevice(array $overrides = []): SensorDevice {
    $device = SensorDevice::create($overrides + [
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $device->save();
    return $device;
  }

  /**
   * Creates a reading at $seconds_ago before now.
   */
  protected function createReading(SensorDevice $device, string $metric, float $value, int $seconds_ago): SensorReading {
    $reading = SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => $metric,
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime() - $seconds_ago,
    ]);
    $reading->save();
    return $reading;
  }

  /**
   * Runs the collector against `$this->apiary` and returns its alerts.
   */
  protected function collect(): array {
    $collector = \Drupal::service('nanoprobe.sensor_alert_collector');
    return $collector->collectAlerts([$this->apiary->id() => $this->apiary], new CacheableMetadata());
  }

  /**
   * Tests a stale `last_seen` fires a warning "Device offline" alert.
   */
  public function testDeviceOfflineFires(): void {
    $device = $this->createDevice([
      'last_seen' => \Drupal::time()->getRequestTime() - (25 * 3600),
    ]);

    $alerts = $this->collect();
    $offline = array_values(array_filter($alerts, fn($a) => (string) $a['chip'] === 'Device offline'));

    $this->assertCount(1, $offline);
    $this->assertEquals('warning', $offline[0]['severity']);
    $this->assertStringContainsString($device->label(), (string) $offline[0]['title']);
  }

  /**
   * Tests a recently-seen device does not fire the offline alert.
   */
  public function testDeviceOfflineDoesNotFireWhenRecent(): void {
    $this->createDevice([
      'last_seen' => \Drupal::time()->getRequestTime() - 3600,
    ]);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests a device that has never reported does not fire the offline alert.
   *
   * A never-reported device is "not yet provisioned," not "offline" —
   * alerting on it would false-alarm every freshly-registered device.
   */
  public function testDeviceThatNeverReportedDoesNotFireOffline(): void {
    $this->createDevice();

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests a same-day weight drop past the threshold fires a critical alert.
   */
  public function testWeightDropFires(): void {
    $device = $this->createDevice();
    $this->createReading($device, 'weight_kg', 42.0, 3600);
    $this->createReading($device, 'weight_kg', 40.0, 60);

    $alerts = $this->collect();
    $drop = array_values(array_filter($alerts, fn($a) => (string) $a['chip'] === 'Possible swarm'));

    $this->assertCount(1, $drop);
    $this->assertEquals('critical', $drop[0]['severity']);
  }

  /**
   * Tests a small same-day drop under the threshold does not fire.
   */
  public function testSmallWeightDropDoesNotFire(): void {
    $device = $this->createDevice();
    $this->createReading($device, 'weight_kg', 42.0, 3600);
    $this->createReading($device, 'weight_kg', 41.5, 60);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests a large drop spanning two different calendar days does not fire.
   *
   * The rule is explicitly a *same-day* drop — a gradual decline over
   * several days is a different (and not yet built) concern.
   */
  public function testWeightDropAcrossDifferentDaysDoesNotFire(): void {
    $device = $this->createDevice();
    // 2 days ago and now — comfortably different calendar dates.
    $this->createReading($device, 'weight_kg', 42.0, 2 * 86400);
    $this->createReading($device, 'weight_kg', 39.0, 0);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests two consecutive out-of-range readings fire a warning alert.
   */
  public function testSustainedTemperatureOutOfRangeFires(): void {
    $device = $this->createDevice(['device_type' => 'temperature_humidity']);
    $this->createReading($device, 'temp_internal_c', 30.0, 3600);
    $this->createReading($device, 'temp_internal_c', 29.5, 60);

    $alerts = $this->collect();
    $temp = array_values(array_filter($alerts, fn($a) => (string) $a['chip'] === 'Temperature out of range'));

    $this->assertCount(1, $temp);
    $this->assertEquals('warning', $temp[0]['severity']);
  }

  /**
   * Tests a single out-of-range reading (not sustained) does not fire.
   */
  public function testSingleOutOfRangeReadingDoesNotFire(): void {
    $device = $this->createDevice(['device_type' => 'temperature_humidity']);
    // Back in range, then one stray low reading — not sustained.
    $this->createReading($device, 'temp_internal_c', 34.5, 3600);
    $this->createReading($device, 'temp_internal_c', 30.0, 60);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests readings within the healthy range never fire.
   */
  public function testTemperatureInRangeDoesNotFire(): void {
    $device = $this->createDevice(['device_type' => 'temperature_humidity']);
    $this->createReading($device, 'temp_internal_c', 34.0, 3600);
    $this->createReading($device, 'temp_internal_c', 34.5, 60);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests a disabled device contributes no alerts of any kind.
   */
  public function testDisabledDeviceExcluded(): void {
    $device = $this->createDevice([
      'enabled' => FALSE,
      'last_seen' => \Drupal::time()->getRequestTime() - (48 * 3600),
    ]);
    $this->createReading($device, 'weight_kg', 42.0, 3600);
    $this->createReading($device, 'weight_kg', 39.0, 60);

    $alerts = $this->collect();
    $this->assertEmpty($alerts);
  }

  /**
   * Tests the hivelog_needs_attention_alerts hook is actually dispatched.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    $this->createDevice([
      'last_seen' => \Drupal::time()->getRequestTime() - (25 * 3600),
    ]);

    $alerts = \Drupal::moduleHandler()->invokeAll(
      'hivelog_needs_attention_alerts',
      [[$this->apiary->id() => $this->apiary], new CacheableMetadata()]
    );
    $this->assertNotEmpty($alerts);
  }

  /**
   * Tests a sensor alert appears in DashboardController's real merged queue.
   *
   * Alongside an existing alert source (low stock).
   */
  public function testSensorAlertAppearsInMergedDashboardQueue(): void {
    $this->createDevice([
      'last_seen' => \Drupal::time()->getRequestTime() - (25 * 3600),
    ]);
    InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Formic acid pads',
      'unit' => 'pad',
      'low_stock_threshold' => 10,
    ])->save();

    /** @var \Drupal\hivelog\Controller\DashboardController $controller */
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($controller->view());

    $this->assertStringContainsString('Device offline', $html);
    $this->assertStringContainsString('Formic acid pads', $html);
  }

}
