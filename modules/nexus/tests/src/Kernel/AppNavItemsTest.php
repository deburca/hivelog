<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nexus's hook_hivelog_app_nav_items() implementation (task 0105).
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
    'key',
    'hivelog',
    'collective',
    'nexus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('ai_provider_config');
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
   * Tests the hook returns the "AI Provider Configs" item.
   */
  public function testHookReturnsAiProviderConfigsItem(): void {
    $items = nexus_hivelog_app_nav_items();
    $this->assertArrayHasKey('nexus_ai_provider_configs', $items);
    $this->assertEquals('entity.ai_provider_config.collection', $items['nexus_ai_provider_configs']['url']->getRouteName());
  }

  /**
   * Tests the item appears in HivelogAppNavBuilder's real merged output.
   */
  public function testAiProviderConfigsItemAppearsInRealAppNav(): void {
    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $this->assertArrayHasKey('nexus_ai_provider_configs', $build);
  }

  /**
   * Tests the item gets a derived main-menu link (task 0119).
   *
   * `nexus` used to ship its own `nexus.links.menu.yml` (deleted) —
   * this proves `HivelogMenuLinks` picks the item up as a real
   * main-menu link instead.
   */
  public function testAiProviderConfigsItemGetsDerivedMenuLink(): void {
    $definitions = \Drupal::service('plugin.manager.menu.link')->getDefinitions();
    $this->assertArrayHasKey('hivelog.nav_item:nexus_ai_provider_configs', $definitions);
    $this->assertEquals('entity.ai_provider_config.collection', $definitions['hivelog.nav_item:nexus_ai_provider_configs']['route_name']);
    $this->assertEquals('hivelog.admin', $definitions['hivelog.nav_item:nexus_ai_provider_configs']['parent']);
  }

}
