<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\HivelogApiResources;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what installing the module sets up for the app to sign in.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Everything the module needs except the module itself, which each test
   * installs through the module installer so its hook_install() really runs.
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
    'serialization',
    'jsonapi',
    'consumers',
    'simple_oauth',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_token');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'jsonapi', 'simple_oauth']);
    \Drupal::service('module_installer')->install(['hivelog_api']);
  }

  /**
   * Loads the module's install file for its helper functions.
   */
  protected function loadInstallFile(): void {
    \Drupal::moduleHandler()->loadInclude('hivelog_api', 'install');
  }

  /**
   * Gets the app's consumers.
   */
  protected function consumers(): array {
    return \Drupal::entityTypeManager()->getStorage('consumer')
      ->loadByProperties(['client_id' => HivelogApiResources::CLIENT_ID]);
  }

  /**
   * Tests install creates a public, PKCE-only client for the app's scheme.
   */
  public function testInstallCreatesThePublicPkceClient(): void {
    $consumers = $this->consumers();
    $this->assertCount(1, $consumers);
    $consumer = reset($consumers);
    $this->assertFalse((bool) $consumer->get('confidential')->value);
    $this->assertTrue((bool) $consumer->get('pkce')->value);
    $this->assertSame(HivelogApiResources::REDIRECT_URI, $consumer->get('redirect')->value);
    $this->assertEqualsCanonicalizing(
      ['authorization_code', 'refresh_token'],
      array_column($consumer->get('grant_types')->getValue(), 'value')
    );
  }

  /**
   * Tests the scope maps to the role of the same name.
   */
  public function testInstallCreatesTheScopeForTheRole(): void {
    $scope = Oauth2Scope::load(HivelogApiResources::SCOPE);
    $this->assertNotNull($scope);
    $this->assertSame('role', $scope->get('granularity_id'));
    $this->assertSame(HivelogApiResources::SCOPE, $scope->get('granularity_configuration')['role']);
    $this->assertTrue($scope->isGrantTypeEnabled('authorization_code'));
    $this->assertTrue($scope->isGrantTypeEnabled('refresh_token'));
  }

  /**
   * Tests the role holds the app's permissions, and nothing it must not.
   */
  public function testTheRoleIsLeastPrivilege(): void {
    $permissions = Role::load(HivelogApiResources::SCOPE)->getPermissions();

    foreach (['view own hive', 'add hive inspection', 'add queen observation', 'edit own hive action log'] as $wanted) {
      $this->assertContains($wanted, $permissions);
    }
    $this->assertEmpty(array_filter($permissions, fn($p) => str_starts_with($p, 'delete ')), 'No delete permission');
    $this->assertEmpty(array_filter($permissions, fn($p) => str_contains($p, 'inventory') || str_contains($p, 'product')));
    $this->assertEmpty(array_filter($permissions, fn($p) => str_contains($p, 'sensor') || str_contains($p, 'ai provider')));
    $this->assertNotContains('administer hivelog', $permissions);
    foreach ($permissions as $permission) {
      $this->assertNotContains($permission, ['view any hive', 'edit any hive', 'add hive', 'add apiary']);
    }
  }

  /**
   * Tests install is idempotent and never revokes a site's own change.
   */
  public function testInstallIsIdempotentAndKeepsSiteChanges(): void {
    $this->loadInstallFile();
    $role = Role::load(HivelogApiResources::SCOPE);
    $role->grantPermission('view any apiary')->save();

    hivelog_api_install();

    $this->assertCount(1, $this->consumers(), 'No duplicate client');
    $this->assertContains('view any apiary', Role::load(HivelogApiResources::SCOPE)->getPermissions());
    $this->assertCount(1, array_filter(Oauth2Scope::loadMultiple(), fn($s) => $s->id() === HivelogApiResources::SCOPE));
  }

  /**
   * Tests install does not touch JSON:API's site-wide read-only setting.
   */
  public function testInstallLeavesJsonapiSettingsAlone(): void {
    $this->assertTrue($this->config('jsonapi.settings')->get('read_only'));
  }

  /**
   * Tests uninstall removes what install created.
   */
  public function testUninstallRemovesTheClientScopeAndRole(): void {
    \Drupal::service('module_installer')->uninstall(['hivelog_api']);

    $this->assertCount(0, $this->consumers());
    $this->assertNull(Oauth2Scope::load(HivelogApiResources::SCOPE));
    $this->assertNull(Role::load(HivelogApiResources::SCOPE));
  }

  /**
   * Tests the status report flags missing keys and extra public scopes.
   */
  public function testRequirementsFlagMissingKeysAndBroadScopes(): void {
    $this->loadInstallFile();

    $requirements = hivelog_api_requirements('runtime');
    $this->assertSame(REQUIREMENT_ERROR, $requirements['hivelog_api_keys']['severity'], 'No key pair yet');
    $this->assertSame(REQUIREMENT_OK, $requirements['hivelog_api_scopes']['severity']);

    Oauth2Scope::create([
      'id' => 'broad',
      'name' => 'broad',
      'description' => 'A broad scope',
      'grant_types' => ['authorization_code' => ['status' => TRUE, 'description' => 'x']],
      'umbrella' => FALSE,
      'parent' => NULL,
      'granularity_id' => 'role',
      'granularity_configuration' => ['role' => 'authenticated'],
    ])->save();

    $requirements = hivelog_api_requirements('runtime');
    $this->assertSame(REQUIREMENT_WARNING, $requirements['hivelog_api_scopes']['severity']);
    $this->assertStringContainsString('broad', (string) $requirements['hivelog_api_scopes']['description']);
  }

}
