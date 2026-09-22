<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Sensor Device entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceTest extends KernelTestBase {

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
    $this->installEntitySchema('sensor_device');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();
  }

  /**
   * Tests creating, updating and deleting a hive-scoped device.
   */
  public function testCrud(): void {
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'transport' => 'lorawan',
    ]);
    $device->save();

    $loaded = SensorDevice::load($device->id());
    $this->assertEquals('VV-01 Scale', $loaded->get('label')->value);
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals('hive', $loaded->get('scope')->value);
    $this->assertEquals('weight', $loaded->get('device_type')->value);
    $this->assertEquals('lorawan', $loaded->get('transport')->value);
    $this->assertTrue((bool) $loaded->get('enabled')->value);

    // Update.
    $device->set('label', 'VV-01 Scale (renamed)');
    $device->save();
    $this->assertEquals('VV-01 Scale (renamed)', SensorDevice::load($device->id())->get('label')->value);

    // Delete.
    $id = $device->id();
    $device->delete();
    $this->assertNull(SensorDevice::load($id));
  }

  /**
   * Tests that `scope` defaults to `hive` and `enabled` defaults to TRUE.
   */
  public function testFieldDefaults(): void {
    $device = SensorDevice::create([
      'label' => 'Default Device',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
    ]);
    $this->assertEquals('hive', $device->get('scope')->value);
    $this->assertTrue((bool) $device->get('enabled')->value);
  }

  /**
   * Tests that an apiary-scoped device may omit hive.
   */
  public function testApiaryScopedDeviceWithoutHive(): void {
    $device = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
    ]);
    $device->save();

    $loaded = SensorDevice::load($device->id());
    $this->assertTrue($loaded->get('hive')->isEmpty());
  }

  /**
   * Tests that hive is required when scope = hive.
   */
  public function testHiveRequiredWhenScopeIsHive(): void {
    $device = SensorDevice::create([
      'label' => 'Missing Hive',
      'apiary' => $this->apiary->id(),
      'scope' => 'hive',
    ]);
    $this->expectException(\Exception::class);
    $device->save();
  }

  /**
   * Tests that hive must be unset when scope = apiary.
   */
  public function testHiveRejectedWhenScopeIsApiary(): void {
    $device = SensorDevice::create([
      'label' => 'Conflicting Scope',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'apiary',
    ]);
    $this->expectException(\Exception::class);
    $device->save();
  }

  /**
   * Tests that a token is auto-generated on insert.
   */
  public function testTokenGeneratedOnInsert(): void {
    $device = SensorDevice::create([
      'label' => 'Token Test',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
    ]);
    $this->assertNull($device->getPlainTextToken());
    $device->save();

    $plaintext = $device->getPlainTextToken();
    $this->assertIsString($plaintext);
    $this->assertNotEmpty($plaintext);
    $this->assertNotEmpty($device->get('token')->value);
    $this->assertNotEquals($plaintext, $device->get('token')->value);
    $this->assertTrue($device->verifyToken($plaintext));
  }

  /**
   * Tests that the plaintext token is not recoverable after a plain load.
   */
  public function testPlainTextTokenNotExposedAfterLoad(): void {
    $device = SensorDevice::create([
      'label' => 'Token Load Test',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
    ]);
    $device->save();
    $plaintext = $device->getPlainTextToken();

    $loaded = SensorDevice::load($device->id());
    $this->assertNull($loaded->getPlainTextToken());
    // The hash itself is still verifiable against the original plaintext.
    $this->assertTrue($loaded->verifyToken($plaintext));
  }

  /**
   * Tests that regenerating a token invalidates the previous one.
   */
  public function testTokenRegeneration(): void {
    $device = SensorDevice::create([
      'label' => 'Regeneration Test',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
    ]);
    $device->save();
    $original = $device->getPlainTextToken();

    $new_plaintext = $device->generateToken();
    $device->save();

    $this->assertNotEquals($original, $new_plaintext);
    $this->assertFalse($device->verifyToken($original));
    $this->assertTrue($device->verifyToken($new_plaintext));
  }

  /**
   * Tests that an incorrect token fails verification.
   */
  public function testIncorrectTokenFailsVerification(): void {
    $device = SensorDevice::create([
      'label' => 'Verify Test',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
    ]);
    $device->save();

    $this->assertFalse($device->verifyToken('not-the-real-token'));
  }

  /**
   * Tests apiary-scoped access control parity.
   */
  public function testApiaryScopedAccess(): void {
    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->grantPermission('delete own sensor device');
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
      'uid' => $beekeeper->id(),
    ]);
    $device->save();

    $this->assertTrue($device->access('view', $owner));
    $this->assertTrue($device->access('view', $beekeeper));
    $this->assertFalse($device->access('view', $outsider));

    $this->assertTrue($device->access('update', $beekeeper));

    // Delete is owner-only, regardless of who created the device.
    $this->assertTrue($device->access('delete', $owner));
    $this->assertFalse($device->access('delete', $beekeeper));

    // Public apiary: an outsider with the "own" permission can view.
    // The device/hive objects above already resolved and cached their
    // `hive`/`apiary` reference fields during the earlier access() calls,
    // and those objects are still sitting in their storages' static
    // caches too — so both the entity storages and the access handler
    // need resetting before a reload picks up the new visibility.
    $apiary->set('visibility', 'public');
    $apiary->save();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_manager->getStorage('hive')->resetCache();
    $entity_type_manager->getStorage('sensor_device')->resetCache();
    $entity_type_manager->getAccessControlHandler('sensor_device')->resetCache();
    $reloaded_device = SensorDevice::load($device->id());
    $this->assertTrue($reloaded_device->access('view', $outsider));
  }

}
