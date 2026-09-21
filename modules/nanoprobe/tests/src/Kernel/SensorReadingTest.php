<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Sensor Reading entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorReadingTest extends KernelTestBase {

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
   * A test sensor device, belonging to `$hive`.
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
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $this->device->save();
  }

  /**
   * Tests creating, updating and deleting a reading.
   */
  public function testCrud(): void {
    $recorded = \Drupal::time()->getRequestTime() - 60;
    $reading = SensorReading::create([
      'sensor_device' => $this->device->id(),
      'metric' => 'weight_kg',
      'value' => 42.5,
      'recorded' => $recorded,
    ]);
    $reading->save();

    $loaded = SensorReading::load($reading->id());
    $this->assertEquals($this->device->id(), $loaded->get('sensor_device')->target_id);
    $this->assertEquals('weight_kg', $loaded->get('metric')->value);
    $this->assertEquals(42.5, (float) $loaded->get('value')->value);
    $this->assertEquals($recorded, (int) $loaded->get('recorded')->value);
    $this->assertNotEmpty($loaded->get('created')->value);
    $this->assertStringContainsString('Weight', (string) $loaded->label());
    $this->assertStringContainsString('VV-01 Scale', (string) $loaded->label());

    // Update.
    $reading->set('value', 43.0);
    $reading->save();
    $this->assertEquals(43.0, (float) SensorReading::load($reading->id())->get('value')->value);

    // Delete.
    $id = $reading->id();
    $reading->delete();
    $this->assertNull(SensorReading::load($id));
  }

  /**
   * Tests that every constant in METRIC_TYPES is a valid, savable metric.
   */
  public function testAllMetricTypesAreValid(): void {
    foreach (array_keys(SensorReading::METRIC_TYPES) as $metric) {
      $reading = SensorReading::create([
        'sensor_device' => $this->device->id(),
        'metric' => $metric,
        'value' => 1,
        'recorded' => \Drupal::time()->getRequestTime(),
      ]);
      $reading->save();
      $this->assertNotNull($reading->id(), "Metric '$metric' should save successfully.");
    }
  }

  /**
   * Tests that an unrecognised metric is rejected.
   */
  public function testUnrecognisedMetricRejected(): void {
    $reading = SensorReading::create([
      'sensor_device' => $this->device->id(),
      'metric' => 'not_a_real_metric',
      'value' => 1,
      'recorded' => \Drupal::time()->getRequestTime(),
    ]);
    $this->expectException(\Exception::class);
    $reading->save();
  }

  /**
   * Tests apiary-scoped access control parity, resolved via sensor_device.
   */
  public function testApiaryScopedAccess(): void {
    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor reading');
    $role->grantPermission('delete own sensor reading');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('beekeeper');
    $owner->save();

    $beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $beekeeper->addRole('beekeeper');
    $beekeeper->save();

    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->addRole('beekeeper');
    $outsider->save();

    $apiary = Apiary::create([
      'name' => 'Access Test Apiary',
      'uid' => $owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$beekeeper->id()],
    ]);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Access Test Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $owner->id(),
    ]);
    $hive->save();

    $device = SensorDevice::create([
      'label' => 'Access Test Device',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'uid' => $owner->id(),
    ]);
    $device->save();

    $reading = SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => 'weight_kg',
      'value' => 10,
      'recorded' => \Drupal::time()->getRequestTime(),
    ]);
    $reading->save();

    $this->assertTrue($reading->access('view', $owner));
    $this->assertTrue($reading->access('view', $beekeeper));
    $this->assertFalse($reading->access('view', $outsider));

    // Delete is owner-only.
    $this->assertTrue($reading->access('delete', $owner));
    $this->assertFalse($reading->access('delete', $beekeeper));

    // No add/edit permission exists at all for this entity type.
    $this->assertFalse($reading->access('update', $beekeeper));
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('sensor_reading');
    $this->assertFalse($access_handler->createAccess(NULL, $beekeeper));

    // Public apiary: an outsider with the "own" permission can view.
    // The reading/device/hive objects above already resolved and cached
    // their reference fields during the earlier access() calls, and
    // those objects are still sitting in their storages' static caches
    // too — so the entity storages and the access handler all need
    // resetting before a reload picks up the new visibility.
    $apiary->set('visibility', 'public');
    $apiary->save();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_manager->getStorage('hive')->resetCache();
    $entity_type_manager->getStorage('sensor_device')->resetCache();
    $entity_type_manager->getStorage('sensor_reading')->resetCache();
    $entity_type_manager->getAccessControlHandler('sensor_reading')->resetCache();
    $reloaded_reading = SensorReading::load($reading->id());
    $this->assertTrue($reloaded_reading->access('view', $outsider));
  }

}
