<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AiProviderConfigListBuilder::load() filters rows by access (0124).
 *
 * Mirrors `\Drupal\Tests\collective\Kernel\ApiClientListBuilderAccessTest`
 * exactly — `AiProviderConfigAccessControlHandler`'s "own" permission is
 * also a flat owner check with no apiary dimension.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AiProviderConfigListBuilderAccessTest extends KernelTestBase {

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
    $this->installEntitySchema('file');
    $this->installEntitySchema('ai_provider_config');
    $this->installSchema('file', ['file_usage']);

    // Burn uid 1 (bypasses all permission checks) on a throwaway account.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();
  }

  /**
   * Tests a "view own" user does not see another owner's config in load().
   */
  public function testOwnViewerDoesNotSeeOthersConfigInLoad(): void {
    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->save();
    $config = AiProviderConfig::create([
      'label' => "Owner's Config",
      'key' => 'test_key',
      'provider' => 'anthropic',
      'uid' => $owner->id(),
    ]);
    $config->save();

    $role = Role::create(['id' => 'own_config_viewer', 'label' => 'Own config viewer']);
    $role->grantPermission('view own ai provider config');
    $role->save();
    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('own_config_viewer');
    $viewer->save();
    $this->setCurrentUser($viewer);

    $loaded = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->load();
    $this->assertArrayNotHasKey($config->id(), $loaded);
  }

  /**
   * Tests the owner sees their own config, "view any" sees every config.
   */
  public function testOwnerAndAnyViewerSeeConfigInLoad(): void {
    $owner = User::create(['name' => 'owner2', 'mail' => 'owner2@example.com']);
    $owner->save();
    $config = AiProviderConfig::create([
      'label' => "Owner's Config",
      'key' => 'test_key',
      'provider' => 'anthropic',
      'uid' => $owner->id(),
    ]);
    $config->save();

    $own_role = Role::create(['id' => 'own_config_owner', 'label' => 'Own config owner']);
    $own_role->grantPermission('view own ai provider config');
    $own_role->save();
    $owner->addRole('own_config_owner');
    $owner->save();
    $this->setCurrentUser($owner);
    $loaded = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->load();
    $this->assertArrayHasKey($config->id(), $loaded);

    $any_role = Role::create(['id' => 'any_config_viewer', 'label' => 'Any config viewer']);
    $any_role->grantPermission('view any ai provider config');
    $any_role->save();
    $any_viewer = User::create(['name' => 'any-viewer', 'mail' => 'any-viewer@example.com']);
    $any_viewer->addRole('any_config_viewer');
    $any_viewer->save();
    $this->setCurrentUser($any_viewer);
    $loaded = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->load();
    $this->assertArrayHasKey($config->id(), $loaded);
  }

  /**
   * Tests render() carries the same filtering through to the row output.
   */
  public function testRenderHidesOtherOwnersConfig(): void {
    $owner = User::create(['name' => 'owner3', 'mail' => 'owner3@example.com']);
    $owner->save();
    AiProviderConfig::create([
      'label' => 'Hidden Config',
      'key' => 'test_key',
      'provider' => 'anthropic',
      'uid' => $owner->id(),
    ])->save();

    $role = Role::create(['id' => 'own_config_viewer2', 'label' => 'Own config viewer 2']);
    $role->grantPermission('view own ai provider config');
    $role->save();
    $viewer = User::create(['name' => 'viewer2', 'mail' => 'viewer2@example.com']);
    $viewer->addRole('own_config_viewer2');
    $viewer->save();
    $this->setCurrentUser($viewer);

    $build = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->render();
    $this->assertCount(0, $build['table']['#props']['rows']);
  }

}
