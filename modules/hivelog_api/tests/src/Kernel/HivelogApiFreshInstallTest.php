<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\HivelogApiResources;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `drush en hivelog_api` on a site that has none of its dependencies.
 *
 * The documented install is one command, which enables jsonapi, consumers,
 * simple_oauth and hivelog_api together. `HivelogApiInstallTest` enables the
 * dependencies first, so it cannot see a problem that only appears when they
 * all arrive in one batch: simple_oauth adds its fields (pkce, grant types,
 * redirect…) to the consumer entity, and on a real site the first install
 * failed because those fields did not exist yet when this module's install hook
 * created the client.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiFreshInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Only what a HiveLog site has; none of the API's dependencies.
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
  ];

  /**
   * Tests the one-command install completes and creates a working client.
   */
  public function testInstallingEverythingInOneBatch(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);

    \Drupal::service('module_installer')->install(['hivelog_api']);

    $consumers = \Drupal::entityTypeManager()->getStorage('consumer')
      ->loadByProperties(['client_id' => HivelogApiResources::CLIENT_ID]);
    $this->assertCount(1, $consumers, 'The client was created');
    $consumer = reset($consumers);
    $this->assertTrue((bool) $consumer->get('pkce')->value);
    $this->assertSame(HivelogApiResources::REDIRECT_URI, $consumer->get('redirect')->value);
    $this->assertEqualsCanonicalizing(
      ['authorization_code', 'refresh_token'],
      array_column($consumer->get('grant_types')->getValue(), 'value')
    );
  }

  /**
   * Tests the install heals a site whose consumer fields were never created.
   *
   * Reproduces the real-site failure: with simple_oauth's fields missing from
   * the consumer storage, creating the client used to die on a missing table.
   */
  public function testInstallCreatesMissingConsumerFields(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);

    // Everything the API needs, except the module itself.
    \Drupal::service('module_installer')->install(['simple_oauth', 'jsonapi']);
    $manager = \Drupal::entityDefinitionUpdateManager();
    $installed = \Drupal::service('entity.last_installed_schema.repository');
    $fields = $installed->getLastInstalledFieldStorageDefinitions('consumer');
    foreach (['grant_types', 'pkce', 'redirect', 'confidential', 'authorization_code_scopes'] as $name) {
      $this->assertArrayHasKey($name, $fields, "$name starts installed");
      $manager->uninstallFieldStorageDefinition($fields[$name]);
    }
    $this->assertArrayNotHasKey('pkce', $installed->getLastInstalledFieldStorageDefinitions('consumer'));

    \Drupal::service('module_installer')->install(['hivelog_api']);

    $consumers = \Drupal::entityTypeManager()->getStorage('consumer')
      ->loadByProperties(['client_id' => HivelogApiResources::CLIENT_ID]);
    $this->assertCount(1, $consumers);
    $this->assertTrue((bool) reset($consumers)->get('pkce')->value);
  }

}
