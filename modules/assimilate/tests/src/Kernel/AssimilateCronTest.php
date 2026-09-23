<?php

declare(strict_types=1);

namespace Drupal\Tests\assimilate\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `assimilate_cron()` end-to-end (task 0107).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AssimilateCronTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('sensor_device');
    $this->installEntitySchema('sensor_reading');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Counts SensorReading rows currently in storage.
   */
  protected function readingCount(): int {
    return count(\Drupal::entityTypeManager()->getStorage('sensor_reading')->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests cron generates readings for every provisioned demo device.
   */
  public function testCronGeneratesReadingsForDemoDevices(): void {
    $devices = \Drupal::service('assimilate.demo_data_provisioner')->provision();
    $this->assertEquals(0, $this->readingCount());

    assimilate_cron();

    $expected = 0;
    foreach ($devices as $device) {
      $expected += count($device->getConfigMetrics());
    }
    $this->assertEquals($expected, $this->readingCount());

    // A second run adds another batch, not replaces the first.
    assimilate_cron();
    $this->assertEquals($expected * 2, $this->readingCount());
  }

  /**
   * Tests cron is a no-op when nothing has been provisioned yet.
   */
  public function testCronDoesNothingWithNoDemoDevices(): void {
    assimilate_cron();
    $this->assertEquals(0, $this->readingCount());
  }

  /**
   * Tests cron skips a disabled demo device.
   */
  public function testCronSkipsDisabledDevice(): void {
    $devices = \Drupal::service('assimilate.demo_data_provisioner')->provision();
    $devices['weight']->set('enabled', FALSE)->save();

    assimilate_cron();

    $this->assertEquals(count($devices['temperature_humidity']->getConfigMetrics()), $this->readingCount());
  }

  /**
   * Tests cron is skipped entirely once a real apiary opts into AI insights.
   *
   * The more insidious contamination case task 0107 calls out: assimilate
   * gets installed first (safe), then a real apiary opts into AI insights
   * later. Cron must stop generating mock data the moment that happens,
   * not just refuse to install in the first place.
   */
  public function testCronSkippedWhenRealApiaryOptsIn(): void {
    \Drupal::service('assimilate.demo_data_provisioner')->provision();

    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    assimilate_cron();

    $this->assertEquals(0, $this->readingCount());
  }

}
