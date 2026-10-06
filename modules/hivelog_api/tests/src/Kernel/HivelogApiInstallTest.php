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
   * Tests the sign-in names the app as Vinculum.
   */
  public function testTheConsentScreenNamesTheApp(): void {
    $consumers = $this->consumers();
    $this->assertSame('Vinculum', reset($consumers)->label());
    $this->assertStringContainsString('Vinculum', (string) Oauth2Scope::load(HivelogApiResources::SCOPE)->get('description'));
  }

  /**
   * Tests the update renames an old default and leaves a chosen label alone.
   */
  public function testUpdateRenamesOnlyTheOldDefaults(): void {
    $this->loadInstallFile();
    $consumers = $this->consumers();
    $consumer = reset($consumers);
    $consumer->set('label', 'HiveLog field app')->save();
    $scope = Oauth2Scope::load(HivelogApiResources::SCOPE);
    $scope->set('description', 'Log inspections, queen observations and calendar action reports from the HiveLog field app.')->save();

    hivelog_api_update_10001();

    $consumers = $this->consumers();
    $this->assertSame('Vinculum', reset($consumers)->label());
    $this->assertStringContainsString('Vinculum', (string) Oauth2Scope::load(HivelogApiResources::SCOPE)->get('description'));

    // An administrator's own label survives a second run.
    $consumer = reset($consumers);
    $consumer->set('label', 'Our club app')->save();
    hivelog_api_update_10001();
    $consumers = $this->consumers();
    $this->assertSame('Our club app', reset($consumers)->label());
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
    $this->assertEmpty(array_filter($permissions, fn($p) => str_contains($p, 'ai provider')));
    // Sensors: the two permissions that view the user's own, and no other (task 0216).
    $sensor = array_values(array_filter($permissions, fn($p) => str_contains($p, 'sensor')));
    $this->assertEmpty(array_diff($sensor, ['view own sensor device', 'view own sensor reading']), 'Sensors are view-own only');
    $this->assertNotContains('administer hivelog', $permissions);
    foreach ($permissions as $permission) {
      $this->assertNotContains($permission, ['view any hive', 'edit any hive', 'add hive', 'add apiary']);
    }
  }

  /**
   * Tests the sensor view permissions arrive when nanoprobe is installed later.
   */
  public function testInstallingNanoprobeLaterTopsUpTheRole(): void {
    $role = Role::load(HivelogApiResources::SCOPE);
    $this->assertFalse($role->hasPermission('view own sensor device'), 'No nanoprobe, no such permission to grant');

    \Drupal::service('module_installer')->install(['nanoprobe']);

    $role = Role::load(HivelogApiResources::SCOPE);
    $this->assertTrue($role->hasPermission('view own sensor device'));
    $this->assertTrue($role->hasPermission('view own sensor reading'));
    $this->assertFalse($role->hasPermission('view any sensor device'));
    $this->assertFalse($role->hasPermission('edit own sensor device'));
    $this->assertFalse($role->hasPermission('add sensor device'));
  }

  /**
   * Tests the update hook gives an existing role the sensor view permissions.
   */
  public function testUpdateAddsTheSensorPermissionsToAnExistingRole(): void {
    \Drupal::service('module_installer')->install(['nanoprobe']);
    $this->loadInstallFile();
    $role = Role::load(HivelogApiResources::SCOPE);
    $role->revokePermission('view own sensor device')->revokePermission('view own sensor reading')->save();
    $role->grantPermission('view any apiary')->save();

    hivelog_api_update_10002();

    $role = Role::load(HivelogApiResources::SCOPE);
    $this->assertTrue($role->hasPermission('view own sensor device'));
    $this->assertTrue($role->hasPermission('view own sensor reading'));
    $this->assertTrue($role->hasPermission('view any apiary'), 'A site\'s own grant is kept');
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

}
