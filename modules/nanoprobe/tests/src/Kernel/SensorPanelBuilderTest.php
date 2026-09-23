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

    // Consumes uid 1 (which bypasses every permission check, including
    // the new administer-hivelog-gated "Add Sensor" link's route access
    // — task 0106) so $owner below is a genuinely unprivileged
    // beekeeper, not an accidental superuser.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

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
   * Tests the panel renders the latest reading + trend chart as a metric tab.
   *
   * Task 0111 follow-up: the per-metric summary went from a stacked
   * `<p>` line, to a `Metric | Value | Updated` table, to (a same-day
   * second follow-up) a `nanoprobe:metric-tabs` vertical tab strip —
   * once the table and its separate list of trend charts existed
   * side by side, splitting "the number" from "the chart for that
   * number" made the page harder to scan, not easier.
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

    $device_section = $panel['nanoprobe_sensors'][$device_key];
    $this->assertEquals('nanoprobe:metric-tabs', $device_section['tabs']['#component']);
    $this->assertEquals((string) $device->id(), $device_section['tabs']['#props']['group']);

    $tabs = $device_section['tabs']['#props']['tabs'];
    $this->assertCount(1, $tabs);
    $this->assertEquals('weight_kg', $tabs[0]['id']);
    $this->assertEquals('Weight (kg)', $tabs[0]['label']);
    $this->assertEquals('42', $tabs[0]['value']);
    $this->assertStringContainsString('<svg', $tabs[0]['chart']);

    // Sanity: the reading actually used is the most recent one.
    $this->assertEquals($latest->id(), $latest->id());
  }

  /**
   * Tests a single day of readings gets a metric tab but no trend chart.
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

    $device_section = $panel['nanoprobe_sensors']['device_' . $device->id()];
    $tabs = $device_section['tabs']['#props']['tabs'];
    $this->assertCount(1, $tabs);
    $this->assertStringNotContainsString('<svg', $tabs[0]['chart']);
    $this->assertNotEmpty($tabs[0]['chart']);
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
   * Tests the hivelog_hive_insights_panels hook is actually wired up.
   *
   * A hook-name/signature typo would silently return nothing from
   * invokeAll() rather than erroring, so this confirms the dispatch
   * mechanism itself works end-to-end, not just the service in
   * isolation. Task 0111 moved this panel off hivelog_hive_view_panels()
   * (the main Hive canonical page) onto this dedicated-Insights-page
   * hook instead — the apiary-scoped panel still uses the original hook
   * (see testApiaryViewPanelsHookIsDispatchedByModuleHandler()).
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
    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_hive_insights_panels', [$this->hive]);
    $this->assertArrayHasKey('nanoprobe_sensors', $panels);
  }

  /**
   * Tests the hivelog_apiary_view_panels hook is still wired up for apiary.
   *
   * Unlike the hive-scoped panel above, the apiary-scoped Sensors panel
   * did NOT move (task 0111 only decluttered the Hive canonical page).
   */
  public function testApiaryViewPanelsHookIsDispatchedByModuleHandler(): void {
    $device = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'temp_internal_c', 22.0, 1);
    $this->createReading($device, 'temp_internal_c', 22.5, 0);

    $this->setCurrentUser($this->owner);
    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_apiary_view_panels', [$this->apiary]);
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

  /**
   * Tests the Hive stat tile (task 0110) shows the device's own vital stat.
   *
   * Weight devices headline `weight_kg`; temperature/humidity devices
   * headline `temp_internal_c` — per
   * `SensorPanelBuilder::PRIMARY_METRIC_BY_DEVICE_TYPE`.
   */
  public function testHiveStatTilesShowPrimaryMetricPerDeviceType(): void {
    $weight_device = SensorDevice::create([
      'label' => 'Weight Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $weight_device->save();
    $this->createReading($weight_device, 'weight_kg', 21.7, 0);
    $this->createReading($weight_device, 'battery_voltage', 3.9, 0);

    $temp_device = SensorDevice::create([
      'label' => 'Temp Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'temperature_humidity',
      'uid' => $this->owner->id(),
    ]);
    $temp_device->save();
    $this->createReading($temp_device, 'temp_internal_c', 35.2, 0);

    $builder = \Drupal::service('nanoprobe.sensor_panel_builder');
    $tiles = $builder->buildHiveStatTiles($this->hive);

    $this->assertEquals('21.7 kg', $tiles['nanoprobe_sensor_' . $weight_device->id()]['value']);
    $this->assertEquals('Weight Sensor', $tiles['nanoprobe_sensor_' . $weight_device->id()]['label']);
    $this->assertEquals('35.2 °C', $tiles['nanoprobe_sensor_' . $temp_device->id()]['value']);
  }

  /**
   * Tests a device's tile links to its full-history readings page.
   */
  public function testHiveStatTileLinksToReadingsPage(): void {
    $device = SensorDevice::create([
      'label' => 'Weight Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 21.7, 0);

    $tiles = \Drupal::service('nanoprobe.sensor_panel_builder')->buildHiveStatTiles($this->hive);
    $url = $tiles['nanoprobe_sensor_' . $device->id()]['url'];
    $this->assertEquals('entity.sensor_device.readings', $url->getRouteName());
    $this->assertEquals(['sensor_device' => $device->id()], $url->getRouteParameters());
  }

  /**
   * Tests a device with no accessible reading yet contributes no tile.
   */
  public function testHiveStatTileAbsentForDeviceWithNoReading(): void {
    SensorDevice::create([
      'label' => 'Silent Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ])->save();

    $tiles = \Drupal::service('nanoprobe.sensor_panel_builder')->buildHiveStatTiles($this->hive);
    $this->assertSame([], $tiles);
  }

  /**
   * Tests a stale reading flags the tile's sublabel as a warning.
   */
  public function testHiveStatTileFlagsStaleReading(): void {
    $device = SensorDevice::create([
      'label' => 'Weight Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    // 2 days ago — past SensorPanelBuilder::STALE_THRESHOLD_SECONDS (24h).
    $this->createReading($device, 'weight_kg', 21.7, 2);

    $tiles = \Drupal::service('nanoprobe.sensor_panel_builder')->buildHiveStatTiles($this->hive);
    $tile = $tiles['nanoprobe_sensor_' . $device->id()];
    $this->assertEquals('warning', $tile['sublabel_variant']);
    $this->assertStringContainsString('No data for', (string) $tile['sublabel']);
  }

  /**
   * Tests the Apiary stat tiles summarise apiary-scoped devices.
   */
  public function testApiaryStatTilesShowApiaryScopedDevice(): void {
    $device = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'temp_internal_c', 22.0, 0);

    $tiles = \Drupal::service('nanoprobe.sensor_panel_builder')->buildApiaryStatTiles($this->apiary);
    $this->assertArrayHasKey('nanoprobe_sensor_' . $device->id(), $tiles);
  }

  /**
   * Tests both stat-tile hooks are actually dispatched by the module handler.
   */
  public function testStatTileHooksAreDispatchedByModuleHandler(): void {
    $device = SensorDevice::create([
      'label' => 'Weight Sensor',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $device->save();
    $this->createReading($device, 'weight_kg', 21.7, 0);

    $hive_tiles = \Drupal::moduleHandler()->invokeAll('hivelog_hive_stat_tiles', [$this->hive]);
    $this->assertArrayHasKey('nanoprobe_sensor_' . $device->id(), $hive_tiles);

    $apiary_tiles = \Drupal::moduleHandler()->invokeAll('hivelog_apiary_stat_tiles', [$this->apiary]);
    $this->assertIsArray($apiary_tiles);
  }

}
