<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Shared setup for kernel tests that send real requests through the API.
 *
 * Requests go through the full HTTP kernel, so routing, the path processor,
 * route filters, access and JSON:API normalisation all run for real. Basic
 * Auth stands in for the OAuth bearer token (the token exchange itself is
 * simple_oauth's own, and is proven end to end in spike 0198); what matters
 * here is that the request is made by a real user holding the real field-app
 * role.
 */
abstract class HivelogApiKernelTestBase extends KernelTestBase {

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
    'basic_auth',
    'consumers',
    'simple_oauth',
    'hivelog',
    'hivelog_api',
  ];

  /**
   * The beekeeper whose records are "mine".
   */
  protected User $me;

  /**
   * Another beekeeper, unrelated to the first.
   */
  protected User $them;

  /**
   * Fixture records, keyed 'my_apiary', 'my_hive', 'their_apiary', ….
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   */
  protected array $fixtures = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'user', 'jsonapi']);
    foreach ([
      'user', 'file', 'consumer', 'oauth2_token', 'apiary', 'hive', 'hive_inspection', 'queen',
      'queen_observation', 'calendar_action', 'hive_action_log', 'apiary_action_log',
      'inventory_item', 'inventory_purchase', 'product', 'calendar_action_item_requirement',
      'calendar_action_product_yield', 'hive_component',
    ] as $type) {
      $this->installEntitySchema($type);
    }

    // The first user is uid 1 and bypasses every permission check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    // The real role the module installs, so a pass proves the app's
    // permission set is enough for what the app does.
    \Drupal::moduleHandler()->loadInclude('hivelog_api', 'install');
    hivelog_api_sync_field_app_role();

    $this->me = $this->beekeeper('me');
    $this->them = $this->beekeeper('them');
    $this->fixtures += $this->buildSide($this->me, 'my');
    $this->fixtures += $this->buildSide($this->them, 'their');

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Creates a user holding the field-app role.
   */
  protected function beekeeper(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'pass' => 'pw-' . $name,
      'status' => 1,
    ]);
    $user->addRole(HivelogApiResources::SCOPE);
    $user->save();
    return $user;
  }

  /**
   * Builds an apiary, hive and inspection owned by a user.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed '<prefix>_apiary', '<prefix>_hive' and '<prefix>_inspection'.
   */
  protected function buildSide(User $owner, string $prefix): array {
    $apiary = Apiary::create(['name' => $prefix . ' apiary', 'uid' => $owner->id(), 'visibility' => 'private']);
    $apiary->save();
    $hive = Hive::create([
      'name' => $prefix . ' hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $owner->id(),
    ]);
    $hive->save();
    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2026-06-01',
      'uid' => $owner->id(),
    ]);
    $inspection->save();
    return [
      $prefix . '_apiary' => $apiary,
      $prefix . '_hive' => $hive,
      $prefix . '_inspection' => $inspection,
    ];
  }

  /**
   * Sends a request through the HTTP kernel.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $path
   *   The path, with an optional query string.
   * @param array|null $json
   *   A JSON:API document to send as the body.
   * @param \Drupal\user\Entity\User|null $user
   *   The user to authenticate as, or NULL for anonymous.
   *
   * @return array{status: int, body: array|null, raw: string}
   *   The response status and decoded body.
   */
  protected function api(string $method, string $path, ?array $json = NULL, ?User $user = NULL): array {
    $server = ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    if ($user) {
      $server['PHP_AUTH_USER'] = $user->getAccountName();
      $server['PHP_AUTH_PW'] = 'pw-' . $user->getAccountName();
    }
    if ($json !== NULL) {
      $server['CONTENT_TYPE'] = 'application/vnd.api+json';
    }
    $request = Request::create('http://localhost' . $path, $method, [], [], [], $server, $json === NULL ? NULL : json_encode($json));
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $this->container->get('http_kernel')->handle($request);
    $raw = (string) $response->getContent();
    // Leave the container as a fresh request would find it.
    $this->container->get('request_stack')->pop();
    return ['status' => $response->getStatusCode(), 'body' => json_decode($raw, TRUE), 'raw' => $raw];
  }

}
