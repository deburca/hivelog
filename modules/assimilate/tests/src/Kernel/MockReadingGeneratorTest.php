<?php

declare(strict_types=1);

namespace Drupal\Tests\assimilate\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `MockReadingGenerator` — task 0107's plausible-trend generation.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class MockReadingGeneratorTest extends KernelTestBase {

  /**
   * Realistic bounds per metric, mirroring MockReadingGenerator::METRIC_PARAMS.
   *
   * Duplicated here deliberately rather than reflected out of the class
   * under test — this is the actual acceptance criterion ("generated
   * readings fall within realistic bounds for their metric"), so the
   * expected bounds belong in the test, not borrowed from the
   * implementation it's checking.
   */
  protected const EXPECTED_BOUNDS = [
    'weight_kg' => [5.0, 60.0],
    'temp_internal_c' => [30.0, 38.0],
    'temp_external_c' => [-10.0, 40.0],
    'humidity_internal_pct' => [40.0, 75.0],
    'humidity_external_pct' => [15.0, 95.0],
    'battery_voltage' => [3.0, 4.2],
    'signal_rssi' => [-100.0, -40.0],
  ];

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
    'assimilate',
  ];

  /**
   * A test hive-scoped weight device.
   */
  protected SensorDevice $weightDevice;

  /**
   * A test hive-scoped temperature/humidity device.
   */
  protected SensorDevice $tempHumidityDevice;

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
    $this->installSchema('file', ['file_usage']);

    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $this->weightDevice = SensorDevice::create([
      'label' => 'Weight Device',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $this->weightDevice->save();

    $this->tempHumidityDevice = SensorDevice::create([
      'label' => 'Temp/Humidity Device',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'temperature_humidity',
    ]);
    $this->tempHumidityDevice->save();
  }

  /**
   * Tests one reading is generated per metric the device is expected to report.
   */
  public function testGeneratesOneReadingPerMetric(): void {
    $generator = \Drupal::service('assimilate.mock_reading_generator');
    $readings = $generator->generateReadingsForDevice($this->weightDevice);

    $expected_metrics = $this->weightDevice->getConfigMetrics();
    $this->assertCount(count($expected_metrics), $readings);

    $actual_metrics = array_map(fn($reading) => $reading->get('metric')->value, $readings);
    sort($expected_metrics);
    sort($actual_metrics);
    $this->assertEquals($expected_metrics, $actual_metrics);
  }

  /**
   * Tests generated readings are actually persisted, correctly linked.
   */
  public function testReadingsArePersistedAndLinkedToDevice(): void {
    $generator = \Drupal::service('assimilate.mock_reading_generator');
    $readings = $generator->generateReadingsForDevice($this->tempHumidityDevice);

    foreach ($readings as $reading) {
      $this->assertNotEmpty($reading->id());
      $loaded = \Drupal::entityTypeManager()->getStorage('sensor_reading')->load($reading->id());
      $this->assertNotNull($loaded);
      $this->assertEquals($this->tempHumidityDevice->id(), $loaded->get('sensor_device')->target_id);
    }
  }

  /**
   * Tests generated values stay within realistic bounds for their metric.
   *
   * Runs many cron-equivalent cycles so the random-walk-plus-noise model
   * has plenty of opportunity to drift outside a bound if the clamping
   * were broken, not just luck into staying inside it once.
   */
  public function testValuesStayWithinRealisticBounds(): void {
    $generator = \Drupal::service('assimilate.mock_reading_generator');

    for ($i = 0; $i < 60; $i++) {
      foreach ($generator->generateReadingsForDevice($this->weightDevice) as $reading) {
        $metric = $reading->get('metric')->value;
        $value = (float) $reading->get('value')->value;
        [$min, $max] = self::EXPECTED_BOUNDS[$metric];
        $this->assertGreaterThanOrEqual($min, $value, "$metric below its realistic minimum");
        $this->assertLessThanOrEqual($max, $value, "$metric above its realistic maximum");
      }
    }
  }

  /**
   * Tests a new reading trends from the previous one, not a fixed baseline.
   *
   * Seeds an existing reading far from the metric's baseline, generates
   * a new one, and asserts it's anchored near the seeded value — proof
   * this is a random walk reading its own history, not independent
   * noise around a constant.
   */
  public function testNewReadingTrendsFromPreviousReading(): void {
    $seeded_value = 45.0;
    \Drupal::entityTypeManager()->getStorage('sensor_reading')->create([
      'sensor_device' => $this->weightDevice->id(),
      'metric' => 'weight_kg',
      'value' => $seeded_value,
      'recorded' => \Drupal::time()->getRequestTime() - 3600,
    ])->save();

    $generator = \Drupal::service('assimilate.mock_reading_generator');
    $readings = $generator->generateReadingsForDevice($this->weightDevice);

    $weight_reading = current(array_filter($readings, fn($r) => $r->get('metric')->value === 'weight_kg'));
    $this->assertNotFalse($weight_reading);

    // Within a few standard deviations of the seeded value — nowhere
    // near the 22.0 baseline it would be anchored to if history were
    // being ignored.
    $this->assertEqualsWithDelta($seeded_value, (float) $weight_reading->get('value')->value, 3.0);
  }

  /**
   * Tests battery_voltage "resets" once it drains near the floor.
   *
   * Simulates a battery swap rather than draining to the floor and
   * staying pinned there on every subsequent reading.
   */
  public function testBatteryVoltageResetsAfterDraining(): void {
    \Drupal::entityTypeManager()->getStorage('sensor_reading')->create([
      'sensor_device' => $this->weightDevice->id(),
      'metric' => 'battery_voltage',
      'value' => 3.0,
      'recorded' => \Drupal::time()->getRequestTime() - 3600,
    ])->save();

    $generator = \Drupal::service('assimilate.mock_reading_generator');
    $readings = $generator->generateReadingsForDevice($this->weightDevice);

    $battery_reading = current(array_filter($readings, fn($r) => $r->get('metric')->value === 'battery_voltage'));
    $this->assertNotFalse($battery_reading);
    $this->assertEquals(4.2, (float) $battery_reading->get('value')->value);
  }

}
