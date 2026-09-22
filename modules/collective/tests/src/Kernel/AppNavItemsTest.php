<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests collective's hook_hivelog_app_nav_items() implementation (task 0105).
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
    'collective',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('api_client');
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
   * Tests the hook returns the "API Clients" item.
   */
  public function testHookReturnsApiClientsItem(): void {
    $items = collective_hivelog_app_nav_items();
    $this->assertArrayHasKey('collective_api_clients', $items);
    $this->assertEquals('entity.api_client.collection', $items['collective_api_clients']['url']->getRouteName());
  }

  /**
   * Tests the item appears in HivelogAppNavBuilder's real merged output.
   *
   * Mirrors `SensorAlertCollectorTest::testSensorAlertAppearsInMergedDashboardQueue()`'s
   * "real merged output, not just the collector in isolation" reasoning.
   */
  public function testApiClientsItemAppearsInRealAppNav(): void {
    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $this->assertArrayHasKey('collective_api_clients', $build);
  }

}
