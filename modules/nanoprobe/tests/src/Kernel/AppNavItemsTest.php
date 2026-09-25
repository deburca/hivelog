<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nanoprobe's hook_hivelog_app_nav_items() implementation (task 0106).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AppNavItemsTest extends KernelTestBase {

  use UserCreationTrait;

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
    $this->installEntitySchema('sensor_device');
    \Drupal::service('router.builder')->rebuild();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    $this->setCurrentUser($admin);
  }

  /**
   * Tests the hook returns the "Sensor Devices" item.
   */
  public function testHookReturnsSensorDevicesItem(): void {
    $items = nanoprobe_hivelog_app_nav_items();
    $this->assertArrayHasKey('nanoprobe_sensor_devices', $items);
    $this->assertEquals('entity.sensor_device.collection', $items['nanoprobe_sensor_devices']['url']->getRouteName());
  }

  /**
   * Tests the item appears in HivelogAppNavBuilder's real merged output.
   *
   * Mirrors `SensorAlertCollectorTest::testSensorAlertAppearsInMergedDashboardQueue()`'s
   * "real merged output, not just the collector in isolation" reasoning.
   */
  public function testSensorDevicesItemAppearsInRealAppNav(): void {
    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $this->assertArrayHasKey('nanoprobe_sensor_devices', $build);
  }

  /**
   * Tests the item gets a derived main-menu link (task 0119).
   *
   * `nanoprobe` used to ship its own `nanoprobe.links.menu.yml`
   * (deleted) — this proves `HivelogMenuLinks` picks the item up as a
   * real main-menu link instead.
   */
  public function testSensorDevicesItemGetsDerivedMenuLink(): void {
    $definitions = \Drupal::service('plugin.manager.menu.link')->getDefinitions();
    $this->assertArrayHasKey('hivelog.nav_item:nanoprobe_sensor_devices', $definitions);
    $this->assertEquals('entity.sensor_device.collection', $definitions['hivelog.nav_item:nanoprobe_sensor_devices']['route_name']);
    $this->assertEquals('hivelog.admin', $definitions['hivelog.nav_item:nanoprobe_sensor_devices']['parent']);
  }

}
