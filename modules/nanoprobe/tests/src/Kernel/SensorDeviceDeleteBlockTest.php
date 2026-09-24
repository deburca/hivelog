<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Drupal\hivelog\Entity\Apiary;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ADR-0103 row #7 (apiary → sensor_device, BLOCK) — task 0141.
 *
 * The row itself is registered by `nanoprobe_hivelog_delete_dependencies()`
 * (nanoprobe.module); this confirms it actually reaches
 * `HivelogDeleteDependencyRegistry::rows()` at runtime and blocks an
 * apiary's delete access exactly like hivelog core's own 12 BLOCK rows,
 * tested in `\Drupal\Tests\hivelog\Kernel\HivelogDeleteBlockRelationshipsTest`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceDeleteBlockTest extends KernelTestBase {

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
   * A non-admin apiary owner.
   */
  protected User $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('sensor_device');
    // Every one of hivelog core's own apiary-rooted BLOCK/CASCADE/WARN
    // rows is deliberately NOT installed here — this test's fixtures
    // don't touch them, and HivelogDeleteDependencyCounter::countsFor()
    // skips a registered row whose child type isn't installed rather
    // than erroring, so nothing but sensor_device is needed.
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'owner', 'label' => 'Owner']);
    $role->grantPermission('delete own apiary');
    $role->save();
    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('owner');
    $this->owner->save();
    \Drupal::currentUser()->setAccount($this->owner);
  }

  /**
   * A SensorDevice blocks its apiary's delete access.
   */
  public function testApiaryBlockedBySensorDevice(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $this->owner->id()]);
    $apiary->save();
    $device = SensorDevice::create([
      'label' => 'Test Device',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'weight',
      'transport' => 'lorawan',
    ]);
    $device->save();

    $result = $apiary->access('delete', $this->owner, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
    $this->assertStringContainsString('1 sensor device', (string) $result->getReason());

    $device->delete();
    // Two separate memoizations — the access handler's own per-request
    // cache and the delete-dependency counter's per-entity one (task
    // 0134, by design) — would otherwise show a stale "still blocked"
    // result within this same test method; see
    // \Drupal\Tests\hivelog\Kernel\HivelogDeleteBlockRelationshipsTest::
    // resetDeleteCaches() for why this never matters in real use.
    $etm = \Drupal::entityTypeManager();
    \Drupal::getContainer()->set(
      'hivelog.delete_dependency_counter',
      new HivelogDeleteDependencyCounter($etm, \Drupal::service('entity.last_installed_schema.repository')),
    );
    $etm->clearCachedDefinitions();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

}
