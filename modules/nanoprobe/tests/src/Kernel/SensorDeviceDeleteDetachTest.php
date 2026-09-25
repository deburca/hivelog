<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ADR-0103 row #12 (hive -> sensor_device, DETACH) — task 0143.
 *
 * `HivelogDeleteDependencyExecutor::detach()`'s own chunked clear-and-save
 * loop is core's, but this row is the one with a real `preSave()`
 * invariant to satisfy: `SensorDevice::preSave()` requires a non-empty
 * `hive` whenever `scope === 'hive'`, so detaching must also flip
 * `scope` to `apiary` in the same save, not just clear `hive`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceDeleteDetachTest extends KernelTestBase {

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
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * A fresh apiary + hive pair.
   *
   * @return array{0: \Drupal\hivelog\Entity\Apiary, 1: \Drupal\hivelog\Entity\Hive}
   *   The apiary and its hive.
   */
  protected function createApiaryAndHive(): array {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    return [$apiary, $hive];
  }

  /**
   * Deleting a hive detaches its device: hive cleared, scope -> apiary.
   */
  public function testHiveDeleteDetachesHiveScopedDevice(): void {
    [$apiary, $hive] = $this->createApiaryAndHive();
    $device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $device->save();

    $hive->delete();

    $reloaded = SensorDevice::load($device->id());
    $this->assertNotNull($reloaded, 'The device must survive the hive delete.');
    $this->assertTrue($reloaded->get('hive')->isEmpty(), "The device's hive reference must be cleared.");
    $this->assertEquals('apiary', $reloaded->get('scope')->value);
    // The apiary reference and every other field are untouched.
    $this->assertEquals($apiary->id(), $reloaded->get('apiary')->target_id);
    $this->assertEquals('VV-01 Scale', $reloaded->label());
    $this->assertEquals('weight', $reloaded->get('device_type')->value);
  }

  /**
   * A saved, detached device passes its own `preSave()` invariant.
   *
   * `SensorDevice::preSave()` throws if `scope === 'hive'` with an
   * empty `hive` — this proves the detach really did flip `scope`
   * *before* saving, not just clear `hive` and rely on validation
   * being skipped.
   */
  public function testDetachedDeviceCanBeResavedWithoutError(): void {
    [, $hive] = $this->createApiaryAndHive();
    $device = SensorDevice::create([
      'label' => 'VV-02 Scale',
      'apiary' => $hive->get('apiary')->target_id,
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $device->save();

    $hive->delete();

    $reloaded = SensorDevice::load($device->id());
    // Would throw InvalidArgumentException if scope were still 'hive'
    // with an empty hive reference.
    $reloaded->set('label', 'VV-02 Scale (renamed)');
    $reloaded->save();

    $this->assertEquals('VV-02 Scale (renamed)', SensorDevice::load($device->id())->label());
  }

  /**
   * Deleting one hive leaves another hive's device alone.
   */
  public function testHiveDeleteLeavesOtherHivesDeviceAlone(): void {
    [$apiary, $hive_a] = $this->createApiaryAndHive();
    $hive_b = Hive::create(['name' => 'Other Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive_b->save();
    $device_b = SensorDevice::create([
      'label' => 'Other Device',
      'apiary' => $apiary->id(),
      'hive' => $hive_b->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $device_b->save();

    $hive_a->delete();

    $reloaded = SensorDevice::load($device_b->id());
    $this->assertNotNull($reloaded);
    $this->assertEquals($hive_b->id(), $reloaded->get('hive')->target_id, "Another hive's device must be untouched.");
    $this->assertEquals('hive', $reloaded->get('scope')->value);
  }

  /**
   * Deleting a hive doesn't touch an already apiary-scoped device.
   */
  public function testHiveDeleteLeavesApiaryScopedDeviceAlone(): void {
    [$apiary, $hive] = $this->createApiaryAndHive();
    $apiary_device = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
    ]);
    $apiary_device->save();

    $hive->delete();

    $reloaded = SensorDevice::load($apiary_device->id());
    $this->assertNotNull($reloaded);
    $this->assertEquals('apiary', $reloaded->get('scope')->value);
    $this->assertTrue($reloaded->get('hive')->isEmpty());
  }

  /**
   * The hive's delete form warns with the device's specific consequence.
   *
   * "1 sensor device (it will become apiary-scoped)" — the row-specific
   * parenthetical `HivelogEntityDeleteForm::detachConsequenceNote()`
   * adds (task 0143), not just a generic DETACH section.
   */
  public function testHiveDeleteFormWarnsDeviceWillBecomeApiaryScoped(): void {
    $this->installConfig(['system']);
    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('admin');
    $admin->save();
    \Drupal::currentUser()->setAccount($admin);

    [, $hive] = $this->createApiaryAndHive();
    SensorDevice::create([
      'label' => 'VV-03 Scale',
      'apiary' => $hive->get('apiary')->target_id,
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ])->save();

    $build = \Drupal::service('entity.form_builder')->getForm($hive, 'delete');
    $this->assertArrayHasKey('detach', $build['hivelog_delete_dependencies']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['hivelog_delete_dependencies']['detach']);
    $this->assertStringContainsString('it will become apiary-scoped', $html);
  }

}
