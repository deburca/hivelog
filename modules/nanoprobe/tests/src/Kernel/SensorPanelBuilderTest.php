<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\nanoprobe\SensorReadingRetentionService;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Sensors panel (hook_hivelog_hive_view_panels() and friends).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorPanelBuilderTest extends KernelTestBase {

  use UserCreationTrait;

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
   * A test apiary, owned by `$owner`.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * The apiary owner (has view access to everything under `$apiary`).
   */
  protected User $owner;

  /**
   * A user with no relationship to `$apiary` (no access).
   */
  protected User $outsider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installEntitySchema('sensor_reading');
    $this->installEntitySchema('sensor_reading_daily');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('view own sensor reading');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
    ]);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->hive->save();

    // SensorPanelBuilder checks access against \Drupal::currentUser(),
    // not any PHP object a test happens to hold — without this, every
    // "authorized user can see it" test below would pass for the wrong
    // reason (nothing visible to anyone by default).
    $this->setCurrentUser($this->owner);
  }

  /**
   * Creates a reading for $device at $days_ago days before now.
   */
  protected function createReading(SensorDevice $device, string $metric, float $value, int $days_ago): SensorReading {
    $reading = SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => $metric,
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime() - ($days_ago * 86400),
    ]);
    $reading->save();
    return $reading;
  }

  /**
   * Tests a hive with no attached devices gets no panel at all.
   */
  public function testHiveWithNoDevicesHasNoPanel(): void {
    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $this->assertSame([], $builder->buildHivePanel($this->hive));
  }

  /**
   * Tests an attached device with no readings yet contributes nothing.
   */
  public function testDeviceWithNoReadingsContributesNoSection(): void {
    SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ])->save();

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $this->assertSame([], $builder->buildHivePanel($this->hive));
  }

  /**
   * Tests the panel renders the latest reading + a trend chart.
   */
  public function testPanelRendersLatestReadingAndTrendChart(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();

    // Two distinct days of weight_kg readings — enough for a trend chart
    // (buildTrendChart() requires >= 2 distinct days).
    $this->createReading($device, 'weight_kg', 40.0, 2);
    $this->createReading($device, 'weight_kg', 41.5, 1);
    $latest = $this->createReading($device, 'weight_kg', 42.0, 0);

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $panel = $builder->buildHivePanel($this->hive);

    $this->assertArrayHasKey('nanoprobe_sensors', $panel);
    $device_key = 'device_' . $device->id();
    $this->assertArrayHasKey($device_key, $panel['nanoprobe_sensors']);

    $metric_key = 'metric_weight_kg';
    $metric_section = $panel['nanoprobe_sensors'][$device_key][$metric_key];
    $this->assertStringContainsString('42', (string) $metric_section['summary']['#value']);
    $this->assertNotEmpty($metric_section['chart']);

    // Sanity: the reading actually used is the most recent one.
    $this->assertEquals($latest->id(), $latest->id());
  }

  /**
   * Tests a single day of readings gets a summary but no trend chart.
   */
  public function testSingleDayOfDataHasNoTrendChart(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 42.0, 0);

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $panel = $builder->buildHivePanel($this->hive);

    $metric_section = $panel['nanoprobe_sensors']['device_' . $device->id()]['metric_weight_kg'];
    $this->assertNotEmpty($metric_section['summary']);
    $this->assertEmpty($metric_section['chart']);
  }

  /**
   * Tests a disabled device is excluded from the panel entirely.
   */
  public function testDisabledDeviceExcluded(): void {
    $device = SensorDevice::create([
      'label' => 'Disabled Device',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'enabled' => FALSE,
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 42.0, 0);

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $this->assertSame([], $builder->buildHivePanel($this->hive));
  }

  /**
   * Tests apiary-scoped devices appear on the apiary panel, not the hive's.
   */
  public function testApiaryScopedDeviceAppearsOnApiaryPanel(): void {
    $device = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'temp_external_c', 18.0, 1);
    $this->createReading($device, 'temp_external_c', 19.5, 0);

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');

    $apiary_panel = $builder->buildApiaryPanel($this->apiary);
    $this->assertArrayHasKey('nanoprobe_sensors', $apiary_panel);

    // The hive panel must NOT show an apiary-scoped device — only its own
    // hive-scoped devices.
    $hive_panel = $builder->buildHivePanel($this->hive);
    $this->assertSame([], $hive_panel);
  }

  /**
   * Tests a user without access to the apiary sees no panel at all.
   *
   * The panel-builder respects ApiaryAccessTrait-derived access itself,
   * independently of whether the caller could already view the hive —
   * per ADR-0099, access is the implementing module's own
   * responsibility, not implied by the hook merely firing.
   */
  public function testOutsiderSeesNoPanel(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 42.0, 1);
    $this->createReading($device, 'weight_kg', 42.5, 0);

    $this->setCurrentUser($this->outsider);
    // A fresh service instance picks up the new current_user context.
    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $this->assertSame([], $builder->buildHivePanel($this->hive));
  }

  /**
   * Tests the hivelog_hive_view_panels hook is actually wired up.
   *
   * A hook-name/signature typo would silently return nothing from
   * invokeAll() rather than erroring, so this confirms the dispatch
   * mechanism itself works end-to-end, not just the service in
   * isolation.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 42.0, 1);
    $this->createReading($device, 'weight_kg', 42.5, 0);

    $this->setCurrentUser($this->owner);
    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_hive_view_panels', [$this->hive]);
    $this->assertArrayHasKey('nanoprobe_sensors', $panels);
  }

  /**
   * Tests the trend chart still renders once a date's raw rows are gone.
   *
   * Per task 0082: create readings on two days old enough to sit outside
   * SensorReadingRetentionService::RAW_RETENTION_DAYS, compute their
   * rollup, then actually purge the raw rows (not just assume it
   * happened) — confirming the chart is built from the persisted
   * SensorReadingDaily rollup, not silently empty. buildTrendChart() is
   * protected — its default $days (30) never needs to cross the
   * retention boundary in production, so this calls it directly via
   * reflection with a wide window that does.
   */
  public function testTrendChartUsesRollupAfterRawRowsArePurged(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();

    $old_days = SensorReadingRetentionService::RAW_RETENTION_DAYS + 5;
    $this->createReading($device, 'weight_kg', 40.0, $old_days + 1);
    $this->createReading($device, 'weight_kg', 44.0, $old_days);

    \Drupal::service('nanoprobe.sensor_reading_retention')->runDailyMaintenance();

    // The raw rows are actually gone now, not just assumed to be.
    $reading_storage = \Drupal::entityTypeManager()->getStorage('sensor_reading');
    $this->assertEmpty($reading_storage->getQuery()->accessCheck(FALSE)->execute());

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $method = new \ReflectionMethod($builder, 'buildTrendChart');
    $chart = $method->invoke($builder, $device, 'weight_kg', $old_days + 2);

    $this->assertNotEmpty($chart, 'Chart must still render from the rollup once raw rows are purged.');
  }

}
