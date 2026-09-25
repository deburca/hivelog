<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\nanoprobe\Entity\SensorReadingDaily;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ADR-0103 rows #27/#28 (sensor_device -> sensor_reading[_daily], CASCADE) — task 0142.
 *
 * `HivelogDeleteDependencyExecutor::cascade()`'s own chunked delete loop
 * is core's, but this row is the one the task's own "doesn't time out or
 * exhaust memory" concern is actually about — a device can accumulate
 * thousands of raw readings before `SensorReadingRetentionService`
 * (task 0082) ever purges them, so this is where the large-volume test
 * belongs.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceDeleteCascadeTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('sensor_device');
    $this->installEntitySchema('sensor_reading');
    $this->installEntitySchema('sensor_reading_daily');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * A fresh apiary-scoped sensor device.
   */
  protected function createDevice(Apiary $apiary, string $label = 'Test Device'): SensorDevice {
    $device = SensorDevice::create([
      'label' => $label,
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'weight',
    ]);
    $device->save();
    return $device;
  }

  /**
   * A fresh reading for `$device`.
   */
  protected function createReading(SensorDevice $device, float $value): SensorReading {
    $reading = SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => 'weight_kg',
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime(),
    ]);
    $reading->save();
    return $reading;
  }

  /**
   * Deleting a sensor device cascades its raw readings.
   */
  public function testSensorDeviceDeleteCascadesReadings(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $device = $this->createDevice($apiary);

    $reading_a = $this->createReading($device, 40);
    $reading_b = $this->createReading($device, 41);

    $device->delete();

    $this->assertNull(SensorReading::load($reading_a->id()));
    $this->assertNull(SensorReading::load($reading_b->id()));
  }

  /**
   * Deleting a sensor device cascades its daily rollups.
   */
  public function testSensorDeviceDeleteCascadesDailyRollups(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $device = $this->createDevice($apiary);

    $rollup = SensorReadingDaily::create([
      'sensor_device' => $device->id(),
      'metric' => 'weight_kg',
      'date' => '2026-09-15',
      'min_value' => 40.0,
      'max_value' => 42.5,
      'avg_value' => 41.2,
      'sample_count' => 12,
    ]);
    $rollup->save();

    $device->delete();

    $this->assertNull(SensorReadingDaily::load($rollup->id()));
  }

  /**
   * Deleting one device leaves another device's readings alone.
   */
  public function testSensorDeviceDeleteLeavesOtherDevicesReadingsAlone(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $device_a = $this->createDevice($apiary, 'Device A');
    $device_b = $this->createDevice($apiary, 'Device B');

    $reading_a = $this->createReading($device_a, 40);
    $reading_b = $this->createReading($device_b, 41);

    $device_a->delete();

    $this->assertNull(SensorReading::load($reading_a->id()));
    $this->assertNotNull(SensorReading::load($reading_b->id()), "Another device's reading must survive.");
  }

  /**
   * A device with a few thousand readings deletes cleanly.
   *
   * The scale this task's own "doesn't time out or exhaust memory"
   * concern is about — `HivelogDeleteDependencyExecutor::cascade()`
   * deletes in chunks of 50 rather than loading every reading for the
   * device in one query.
   */
  public function testSensorDeviceDeleteCascadesLargeReadingVolume(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $device = $this->createDevice($apiary);

    $total = 2500;
    $storage = \Drupal::entityTypeManager()->getStorage('sensor_reading');
    $now = \Drupal::time()->getRequestTime();
    for ($i = 0; $i < $total; $i++) {
      $storage->create([
        'sensor_device' => $device->id(),
        'metric' => 'weight_kg',
        'value' => 40 + ($i % 5),
        'recorded' => $now - $i,
      ])->save();
    }

    $count_before = (int) $storage->getQuery()->accessCheck(FALSE)->condition('sensor_device', $device->id())->count()->execute();
    $this->assertEquals($total, $count_before);

    $device->delete();

    $count_after = (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute();
    $this->assertEquals(0, $count_after, 'Every reading for the deleted device must be gone.');
  }

}
