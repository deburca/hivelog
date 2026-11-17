<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\HivelogApiResources;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the module's entries on the status report.
 *
 * They went missing on Drupal 11.3 and later, where a procedural requirements
 * hook marked as legacy is skipped and the module had no object-oriented one.
 * These tests read the requirements the way the status report page does
 * (the system manager), so they fail if the entries are not there.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiRequirementsTest extends KernelTestBase {

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
   * Gets one entry of the status report.
   *
   * @return array<string, mixed>
   *   The requirement, with its severity as an integer: 0 is OK, 1 a warning
   *   and 2 an error.
   */
  protected function requirement(string $key): array {
    $all = \Drupal::service('system.manager')->listRequirements();
    $this->assertArrayHasKey($key, $all, "The status report has no $key entry.");
    $requirement = $all[$key];
    $severity = $requirement['severity'];
    $requirement['severity'] = $severity instanceof \BackedEnum ? $severity->value : (int) $severity;
    return $requirement;
  }

  /**
   * Writes a throwaway key file and returns its path.
   */
  protected function keyFile(): string {
    $path = tempnam(sys_get_temp_dir(), 'hivelog_key_');
    file_put_contents($path, 'not a real key');
    chmod($path, 0600);
    return $path;
  }

  /**
   * Without a key pair the status report says so, as an error.
   */
  public function testMissingKeysAreAnError(): void {
    $this->config('simple_oauth.settings')->set('public_key', '')->set('private_key', '')->save();
    $requirement = $this->requirement('hivelog_api_keys');
    $this->assertSame(2, $requirement['severity']);
    $this->assertSame('Missing', (string) $requirement['value']);
  }

  /**
   * A readable public and private key is reported as configured.
   */
  public function testReadableKeysAreOk(): void {
    $this->config('simple_oauth.settings')
      ->set('public_key', $this->keyFile())
      ->set('private_key', $this->keyFile())
      ->save();
    $requirement = $this->requirement('hivelog_api_keys');
    $this->assertSame(0, $requirement['severity']);
    $this->assertSame('Configured', (string) $requirement['value']);
  }

  /**
   * One key that cannot be read is enough to be an error.
   */
  public function testOneUnreadableKeyIsAnError(): void {
    $this->config('simple_oauth.settings')
      ->set('public_key', $this->keyFile())
      ->set('private_key', '/nonexistent/private.key')
      ->save();
    $this->assertSame(2, $this->requirement('hivelog_api_keys')['severity']);
  }

  /**
   * With only the app's own scope the scopes entry is fine.
   */
  public function testOnlyTheAppScopeIsOk(): void {
    $requirement = $this->requirement('hivelog_api_scopes');
    $this->assertSame(0, $requirement['severity']);
    $this->assertSame('Only the field app scope', (string) $requirement['value']);
  }

  /**
   * Another scope that allows the authorization-code grant is a warning.
   */
  public function testAnotherAuthorizationCodeScopeWarns(): void {
    Oauth2Scope::create([
      'id' => 'broad',
      'name' => 'broad',
      'description' => 'A broad scope.',
      'grant_types' => ['authorization_code' => ['status' => TRUE, 'description' => 'Sign in.']],
      'umbrella' => FALSE,
      'parent' => NULL,
      'granularity_id' => 'role',
      'granularity_configuration' => ['role' => HivelogApiResources::SCOPE],
    ])->save();
    $requirement = $this->requirement('hivelog_api_scopes');
    $this->assertSame(1, $requirement['severity']);
    $this->assertStringContainsString('broad', (string) $requirement['description']);
  }

}
