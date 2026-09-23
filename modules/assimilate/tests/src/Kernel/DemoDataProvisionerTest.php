<?php

declare(strict_types=1);

namespace Drupal\Tests\assimilate\Kernel;

use Drupal\assimilate\DemoDataProvisioner;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `DemoDataProvisioner` — task 0107's demo apiary/hive/device setup.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DemoDataProvisionerTest extends KernelTestBase {

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
   * Tests provision() creates the demo apiary, hive, inspection and devices.
   */
  public function testProvisionCreatesEverything(): void {
    $provisioner = \Drupal::service('assimilate.demo_data_provisioner');
    $devices = $provisioner->provision();

    $this->assertArrayHasKey('weight', $devices);
    $this->assertArrayHasKey('temperature_humidity', $devices);

    $apiary_id = \Drupal::state()->get(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY);
    $apiary = Apiary::load($apiary_id);
    $this->assertNotNull($apiary);
    $this->assertTrue((bool) $apiary->get('ai_insights_enabled')->value);

    $hive_id = \Drupal::state()->get(DemoDataProvisioner::DEMO_HIVE_ID_STATE_KEY);
    $hive = Hive::load($hive_id);
    $this->assertNotNull($hive);
    $this->assertEquals($apiary->id(), $hive->get('apiary')->target_id);

    $inspection_id = \Drupal::state()->get(DemoDataProvisioner::DEMO_INSPECTION_ID_STATE_KEY);
    $inspection = HiveInspection::load($inspection_id);
    $this->assertNotNull($inspection);
    $this->assertEquals($hive->id(), $inspection->get('hive')->target_id);

    foreach ($devices as $device_type => $device) {
      $this->assertEquals($device_type, $device->get('device_type')->value);
      $this->assertEquals('hive', $device->get('scope')->value);
      $this->assertEquals($hive->id(), $device->get('hive')->target_id);
      $this->assertEquals($apiary->id(), $device->get('apiary')->target_id);
      $this->assertTrue((bool) $device->get('enabled')->value);
    }
  }

  /**
   * Tests calling provision() again reuses everything instead of duplicating.
   */
  public function testProvisionIsIdempotent(): void {
    $provisioner = \Drupal::service('assimilate.demo_data_provisioner');
    $first = $provisioner->provision();
    $second = $provisioner->provision();

    $this->assertEquals($first['weight']->id(), $second['weight']->id());
    $this->assertEquals($first['temperature_humidity']->id(), $second['temperature_humidity']->id());

    $this->assertCount(1, \Drupal::entityTypeManager()->getStorage('apiary')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(1, \Drupal::entityTypeManager()->getStorage('hive')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(1, \Drupal::entityTypeManager()->getStorage('hive_inspection')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(2, \Drupal::entityTypeManager()->getStorage('sensor_device')->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests getDemoDevices() returns the provisioned devices without creating any.
   */
  public function testGetDemoDevicesReturnsProvisionedDevices(): void {
    $provisioner = \Drupal::service('assimilate.demo_data_provisioner');

    $this->assertEmpty($provisioner->getDemoDevices());

    $provisioner->provision();
    $devices = $provisioner->getDemoDevices();
    $this->assertCount(2, $devices);
    $this->assertArrayHasKey('weight', $devices);
    $this->assertArrayHasKey('temperature_humidity', $devices);

    // Still exactly 2 sensor_device rows — getDemoDevices() must not
    // create anything.
    $this->assertCount(2, \Drupal::entityTypeManager()->getStorage('sensor_device')->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests cleanUp() removes every demo entity and clears State.
   */
  public function testCleanUpRemovesEverything(): void {
    $provisioner = \Drupal::service('assimilate.demo_data_provisioner');
    $devices = $provisioner->provision();

    // A reading on one demo device, to confirm cleanUp() takes readings
    // with it too, not just the devices.
    \Drupal::entityTypeManager()->getStorage('sensor_reading')->create([
      'sensor_device' => $devices['weight']->id(),
      'metric' => 'weight_kg',
      'value' => 20.0,
      'recorded' => \Drupal::time()->getRequestTime(),
    ])->save();

    $provisioner->cleanUp();

    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('apiary')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('hive')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('hive_inspection')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('sensor_device')->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('sensor_reading')->getQuery()->accessCheck(FALSE)->execute());

    $this->assertNull(\Drupal::state()->get(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY));
    $this->assertNull(\Drupal::state()->get(DemoDataProvisioner::DEMO_HIVE_ID_STATE_KEY));
    $this->assertNull(\Drupal::state()->get(DemoDataProvisioner::DEMO_INSPECTION_ID_STATE_KEY));
    $this->assertNull(\Drupal::state()->get(DemoDataProvisioner::DEMO_DEVICE_IDS_STATE_KEY));
  }

}
