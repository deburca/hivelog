<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReadingDaily;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Sensor Reading Daily Rollup entity (task 0082).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorReadingDailyTest extends KernelTestBase {

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
    $this->installEntitySchema('sensor_reading_daily');
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
   * Tests creating, updating and deleting a rollup row.
   */
  public function testCrud(): void {
    $rollup = SensorReadingDaily::create([
      'sensor_device' => $this->device->id(),
      'metric' => 'weight_kg',
      'date' => '2026-09-15',
      'min_value' => 40.0,
      'max_value' => 42.5,
      'avg_value' => 41.2,
      'sample_count' => 12,
    ]);
    $rollup->save();

    $loaded = SensorReadingDaily::load($rollup->id());
    $this->assertEquals($this->device->id(), $loaded->get('sensor_device')->target_id);
    $this->assertEquals('weight_kg', $loaded->get('metric')->value);
    $this->assertEquals('2026-09-15', $loaded->get('date')->value);
    $this->assertEquals(40.0, (float) $loaded->get('min_value')->value);
    $this->assertEquals(42.5, (float) $loaded->get('max_value')->value);
    $this->assertEquals(41.2, (float) $loaded->get('avg_value')->value);
    $this->assertEquals(12, (int) $loaded->get('sample_count')->value);
    $this->assertStringContainsString('Weight', (string) $loaded->label());
    $this->assertStringContainsString('2026-09-15', (string) $loaded->label());

    // Update.
    $rollup->set('sample_count', 13);
    $rollup->save();
    $this->assertEquals(13, (int) SensorReadingDaily::load($rollup->id())->get('sample_count')->value);

    // Delete.
    $id = $rollup->id();
    $rollup->delete();
    $this->assertNull(SensorReadingDaily::load($id));
  }

  /**
   * Tests apiary-scoped access, reusing SensorReading's own permissions.
   */
  public function testApiaryScopedAccessReusesSensorReadingPermissions(): void {
    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor reading');
    $role->grantPermission('delete own sensor reading');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('beekeeper');
    $owner->save();

    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->addRole('beekeeper');
    $outsider->save();

    $apiary = Apiary::create([
      'name' => 'Access Test Apiary',
      'uid' => $owner->id(),
      'visibility' => 'private',
    ]);
    $apiary->save();
    $hive = Hive::create(['name' => 'H', 'apiary' => $apiary->id(), 'status' => 'active', 'uid' => $owner->id()]);
    $hive->save();
    $device = SensorDevice::create([
      'label' => 'D',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'uid' => $owner->id(),
    ]);
    $device->save();

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

    $this->assertTrue($rollup->access('view', $owner));
    $this->assertFalse($rollup->access('view', $outsider));
    $this->assertTrue($rollup->access('delete', $owner));

    // $owner is this test's first-created user, and so is uid 1 (Drupal's
    // superuser, which bypasses permission checks entirely) — use
    // $outsider here instead, to actually exercise the permission check
    // rather than the uid-1 bypass.
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('sensor_reading_daily');
    $this->assertFalse($access_handler->createAccess(NULL, $outsider));
  }

}
