<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI Provider Config entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AiProviderConfigTest extends KernelTestBase {

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
  }

  /**
   * Tests creating, updating and deleting a config.
   */
  public function testCrud(): void {
    $config = AiProviderConfig::create([
      'label' => 'Production AI Provider',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'anthropic_api_key',
    ]);
    $config->save();

    $loaded = AiProviderConfig::load($config->id());
    $this->assertEquals('Production AI Provider', $loaded->label());
    $this->assertEquals('direct_api', $loaded->get('mode')->value);
    $this->assertEquals('anthropic', $loaded->get('provider')->value);
    $this->assertEquals('anthropic_api_key', $loaded->get('key')->value);
    $this->assertTrue((bool) $loaded->get('enabled')->value);
    $this->assertTrue($loaded->get('last_run')->isEmpty());

    // Update.
    $config->set('label', 'Production AI Provider (renamed)');
    $config->save();
    $this->assertEquals('Production AI Provider (renamed)', AiProviderConfig::load($config->id())->label());

    // Delete.
    $id = $config->id();
    $config->delete();
    $this->assertNull(AiProviderConfig::load($id));
  }

  /**
   * Tests `mode` defaults to `direct_api` and `enabled` defaults to TRUE.
   */
  public function testFieldDefaults(): void {
    $config = AiProviderConfig::create(['label' => 'Default Config', 'key' => 'some_key']);
    $this->assertEquals('direct_api', $config->get('mode')->value);
    $this->assertTrue((bool) $config->get('enabled')->value);
  }

  /**
   * Tests an unrecognised mode is rejected.
   */
  public function testUnrecognisedModeRejected(): void {
    $config = AiProviderConfig::create([
      'label' => 'Bad Mode',
      'mode' => 'not_a_real_mode',
      'key' => 'some_key',
    ]);
    $this->expectException(\Exception::class);
    $config->save();
  }

  /**
   * Tests `key` is required for direct_api mode.
   */
  public function testKeyRequiredForDirectApiMode(): void {
    $config = AiProviderConfig::create([
      'label' => 'No Key',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
    ]);
    $this->expectException(\Exception::class);
    $config->save();
  }

  /**
   * Tests `key` is required for custom_endpoint mode.
   */
  public function testKeyRequiredForCustomEndpointMode(): void {
    $config = AiProviderConfig::create([
      'label' => 'No Key',
      'mode' => 'custom_endpoint',
      'endpoint_url' => 'https://example.com/nexus-endpoint',
    ]);
    $this->expectException(\Exception::class);
    $config->save();
  }

  /**
   * Tests `key` is NOT required for ai_module mode.
   *
   * That mode delegates credential storage to the Drupal AI module's own
   * provider plugins.
   */
  public function testKeyNotRequiredForAiModuleMode(): void {
    $config = AiProviderConfig::create([
      'label' => 'AI Module Config',
      'mode' => 'ai_module',
    ]);
    $config->save();
    $this->assertNotNull($config->id());
  }

  /**
   * Tests `provider` is required for direct_api mode.
   */
  public function testProviderRequiredForDirectApiMode(): void {
    $config = AiProviderConfig::create([
      'label' => 'No Provider',
      'mode' => 'direct_api',
      'key' => 'some_key',
    ]);
    $this->expectException(\Exception::class);
    $config->save();
  }

  /**
   * Tests `endpoint_url` is required for custom_endpoint mode.
   */
  public function testEndpointUrlRequiredForCustomEndpointMode(): void {
    $config = AiProviderConfig::create([
      'label' => 'No Endpoint',
      'mode' => 'custom_endpoint',
      'key' => 'some_key',
    ]);
    $this->expectException(\Exception::class);
    $config->save();
  }

  /**
   * Tests ownership-based access — no apiary/hive dimension at all.
   *
   * Only `view` has an "own" permission (task 0135 removed `edit own`/
   * `delete own ai provider config` as dead config — see
   * `AiProviderConfigAccessControlHandler`'s own docblock). Update/delete
   * are covered separately by {@see testUpdateDeleteRequireAnyPermission}.
   */
  public function testOwnershipBasedAccess(): void {
    // The first user created in a kernel test is uid 1, which bypasses
    // permission checks entirely — a throwaway user here ensures
    // $owner's "can" assertions below actually exercise the
    // ownership-check logic, not the uid-1 bypass.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'config_manager', 'label' => 'Config Manager']);
    $role->grantPermission('view own ai provider config');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('config_manager');
    $owner->save();

    $other = User::create(['name' => 'other', 'mail' => 'other@example.com']);
    $other->addRole('config_manager');
    $other->save();

    $config = AiProviderConfig::create([
      'label' => 'Owned Config',
      'mode' => 'ai_module',
      'uid' => $owner->id(),
    ]);
    $config->save();

    $this->assertTrue($config->access('view', $owner));
    $this->assertFalse($config->access('view', $other));
  }

  /**
   * Tests update/delete are "any"-only — no "own" path exists (task 0135).
   *
   * With create staying `administer hivelog`-only, a config's owner is
   * always an admin already covered by "any", so even the true owner
   * cannot update/delete without the site-wide `edit any`/`delete any
   * ai provider config` permission.
   */
  public function testUpdateDeleteRequireAnyPermission(): void {
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'config_manager', 'label' => 'Config Manager']);
    $role->grantPermission('view own ai provider config');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('config_manager');
    $owner->save();

    $config = AiProviderConfig::create([
      'label' => 'Owned Config',
      'mode' => 'ai_module',
      'uid' => $owner->id(),
    ]);
    $config->save();

    $this->assertFalse($config->access('update', $owner));
    $this->assertFalse($config->access('delete', $owner));

    $any_role = Role::create(['id' => 'config_admin', 'label' => 'Config Admin']);
    $any_role->grantPermission('edit any ai provider config');
    $any_role->grantPermission('delete any ai provider config');
    $any_role->save();

    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('config_admin');
    $admin->save();

    $this->assertTrue($config->access('update', $admin));
    $this->assertTrue($config->access('delete', $admin));
  }

  /**
   * Tests only `administer hivelog` may create a config.
   */
  public function testOnlyAdministerHivelogGrantsCreateAccess(): void {
    // Consume uid 1 (Drupal's permission-check-bypassing superuser) so
    // $user below genuinely exercises the permission check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'config_manager', 'label' => 'Config Manager']);
    $role->grantPermission('view own ai provider config');
    $role->save();

    $user = User::create(['name' => 'user', 'mail' => 'user@example.com']);
    $user->addRole('config_manager');
    $user->save();

    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('ai_provider_config');
    $this->assertFalse($access_handler->createAccess(NULL, $user));

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();

    $this->assertTrue($access_handler->createAccess(NULL, $admin));
  }

}
