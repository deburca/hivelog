<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\nanoprobe\SensorReadingRetentionService;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the daily rollup + raw-reading purge job (task 0082).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorReadingRetentionServiceTest extends KernelTestBase {

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
   * A test sensor device.
   */
  protected SensorDevice $device;

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

    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $this->device->save();
  }

  /**
   * Creates a reading at $seconds_ago before now.
   */
  protected function createReading(string $metric, float $value, int $seconds_ago): SensorReading {
    $reading = SensorReading::create([
      'sensor_device' => $this->device->id(),
      'metric' => $metric,
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime() - $seconds_ago,
    ]);
    $reading->save();
    return $reading;
  }

  /**
   * Loads all sensor_reading_daily rows, for direct inspection.
   */
  protected function loadAllRollups(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('sensor_reading_daily');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Tests the rollup's min/max/avg exactly match the source raw rows.
   */
  public function testRollupValuesAreCorrect(): void {
    // Three readings on the same past day.
    $this->createReading('weight_kg', 40.0, 2 * 86400);
    $this->createReading('weight_kg', 42.0, 2 * 86400 - 3600);
    $this->createReading('weight_kg', 41.0, 2 * 86400 - 7200);

    \Drupal::service('nanoprobe.sensor_reading_retention')->computeRollups();

    $rollups = $this->loadAllRollups();
    $this->assertCount(1, $rollups);
    $rollup = reset($rollups);
    $this->assertEquals(40.0, (float) $rollup->get('min_value')->value);
    $this->assertEquals(42.0, (float) $rollup->get('max_value')->value);
    $this->assertEqualsWithDelta(41.0, (float) $rollup->get('avg_value')->value, 0.001);
    $this->assertEquals(3, (int) $rollup->get('sample_count')->value);
  }

  /**
   * Tests a reading recorded today is not rolled up yet.
   *
   * Today isn't finished accumulating — rolling it up now would freeze
   * an incomplete day's min/max/avg.
   */
  public function testTodayIsNotRolledUpYet(): void {
    $this->createReading('weight_kg', 42.0, 60);

    \Drupal::service('nanoprobe.sensor_reading_retention')->computeRollups();

    $this->assertEmpty($this->loadAllRollups());
  }

  /**
   * Tests recomputing a rollup updates the row rather than duplicating it.
   *
   * An idempotent upsert — simulates a late-arriving reading for an
   * already-rolled-up date.
   */
  public function testRecomputingUpdatesExistingRollupRow(): void {
    $this->createReading('weight_kg', 40.0, 2 * 86400);
    \Drupal::service('nanoprobe.sensor_reading_retention')->computeRollups();

    $rollups = $this->loadAllRollups();
    $this->assertCount(1, $rollups);
    $original_id = reset($rollups)->id();

    // A late-arriving reading for the same day.
    $this->createReading('weight_kg', 44.0, 2 * 86400 - 3600);
    \Drupal::service('nanoprobe.sensor_reading_retention')->computeRollups();

    $rollups = $this->loadAllRollups();
    $this->assertCount(1, $rollups, 'Recomputing must update the existing row, not create a second one.');
    $updated = reset($rollups);
    $this->assertEquals($original_id, $updated->id());
    $this->assertEquals(44.0, (float) $updated->get('max_value')->value);
    $this->assertEquals(2, (int) $updated->get('sample_count')->value);
  }

  /**
   * Tests purge never deletes a row still inside the retention window.
   */
  public function testPurgeKeepsReadingsInsideRetentionWindow(): void {
    $reading = $this->createReading('weight_kg', 42.0, 30 * 86400);

    \Drupal::service('nanoprobe.sensor_reading_retention')->purgeOldRawReadings();

    $this->assertNotNull(SensorReading::load($reading->id()));
  }

  /**
   * Tests purge deletes rows older than RAW_RETENTION_DAYS.
   */
  public function testPurgeDeletesReadingsOlderThanRetentionWindow(): void {
    $reading = $this->createReading(
      'weight_kg',
      42.0,
      (SensorReadingRetentionService::RAW_RETENTION_DAYS + 1) * 86400
    );

    \Drupal::service('nanoprobe.sensor_reading_retention')->purgeOldRawReadings();

    $this->assertNull(SensorReading::load($reading->id()));
  }

  /**
   * Tests a date's data survives purge via its rollup.
   *
   * The full maintenance cycle: the rollup is computed in the same run,
   * before the raw row is deleted.
   */
  public function testDataSurvivesPurgeViaRollup(): void {
    $old_seconds_ago = (SensorReadingRetentionService::RAW_RETENTION_DAYS + 5) * 86400;
    $reading = $this->createReading('weight_kg', 42.0, $old_seconds_ago);

    \Drupal::service('nanoprobe.sensor_reading_retention')->runDailyMaintenance();

    // The raw row is gone...
    $this->assertNull(SensorReading::load($reading->id()));

    // ...but its rollup survives, with the correct value.
    $rollups = $this->loadAllRollups();
    $this->assertCount(1, $rollups);
    $rollup = reset($rollups);
    $this->assertEquals(42.0, (float) $rollup->get('avg_value')->value);
    $this->assertEquals(1, (int) $rollup->get('sample_count')->value);
  }

}
