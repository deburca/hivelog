<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Entity\ApiClient;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests every collective.routing.yml route with an entity parameter (0133).
 *
 * Mirrors `\Drupal\Tests\hivelog\Kernel\RouteEntityAccessTest`. Unlike the
 * core module's apiary-scoped routes, `ApiClientAccessControlHandler`'s
 * "own" permission is a flat owner check, so before this task a route
 * checking only `_permission: 'view own api client+...'` let ANY user
 * with that permission view/edit/delete ANY other user's client by URL —
 * the permission says nothing about who owns the specific record.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class RouteEntityAccessTest extends KernelTestBase {

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
   * Routes with no entity parameter, and why they need no per-entity check.
   */
  protected const EXEMPT_ROUTES = [
    'collective.api_client.context' => 'token-authenticated external-client endpoint, no Drupal session',
    'entity.api_client.collection' => 'collection page; row filtering is HivelogListBuilder::load() (task 0124)',
    'entity.api_client.add_form' => 'administer hivelog only, no parent context to check',
  ];

  /**
   * The client owner.
   */
  protected User $owner;

  /**
   * A user with the same "own" permissions but no relation to the fixture.
   */
  protected User $outsider;

  /**
   * An `administer hivelog` user.
   */
  protected User $admin;

  /**
   * Fixture entities, keyed by route parameter name.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $fixtures = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('api_client');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'own_client', 'label' => 'Own client']);
    $role->grantPermission('view own api client');
    $role->grantPermission('edit own api client');
    $role->grantPermission('delete own api client');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('own_client');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('own_client');
    $this->outsider->save();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $this->admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $this->admin->addRole('hivelog_admin');
    $this->admin->save();

    $client = ApiClient::create(['label' => "Owner's Client", 'uid' => $this->owner->id()]);
    $client->save();
    $this->fixtures['api_client'] = $client;
  }

  /**
   * This submodule's own routes, keyed by name.
   *
   * `hivelog` is also installed (collective depends on it), so the
   * router contains hivelog core's routes too — filter by route name
   * prefix to this module's own routes only, not by path (every route in
   * every routing file here shares the `/hivelog` path prefix).
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The routes, keyed by route name.
   */
  protected function hivelogRoutes(): array {
    $provider = \Drupal::service('router.route_provider');
    $routes = [];
    foreach ($provider->getAllRoutes() as $name => $route) {
      if (str_starts_with($name, 'collective.') || str_starts_with($name, 'entity.api_client.')) {
        $routes[$name] = $route;
      }
    }
    return $routes;
  }

  /**
   * Returns the entity-typed route parameter names on a route.
   *
   * @return string[]
   *   The route parameter names whose upcast type is an entity.
   */
  protected function entityParameterNames(Route $route): array {
    $names = [];
    foreach ($route->getOption('parameters') ?? [] as $name => $info) {
      if (isset($info['type']) && str_starts_with($info['type'], 'entity:')) {
        $names[] = $name;
      }
    }
    return $names;
  }

  /**
   * Tests every route with no entity parameter is in the exempt list.
   */
  public function testEveryRouteIsExemptOrDeclaresAnEntityCheck(): void {
    foreach ($this->hivelogRoutes() as $name => $route) {
      $entity_params = $this->entityParameterNames($route);

      if (!$entity_params) {
        $this->assertArrayHasKey(
          $name,
          self::EXEMPT_ROUTES,
          "Route '$name' has no entity parameter and is not in the documented EXEMPT_ROUTES list."
        );
        continue;
      }

      $requirements = $route->getRequirements();
      $has_check = isset($requirements['_entity_access'])
        || isset($requirements['_entity_create_access'])
        || isset($requirements['_custom_access']);
      $this->assertTrue(
        $has_check,
        "Route '$name' has entity parameter(s) [" . implode(', ', $entity_params) . '] but no ' .
        '_entity_access / _entity_create_access / _custom_access requirement.'
      );
    }
  }

  /**
   * Tests every entity route denies an unrelated owner and allows the owner.
   */
  public function testOutsiderDeniedOwnerAndAdminAllowed(): void {
    $access_manager = \Drupal::service('access_manager');

    foreach ($this->hivelogRoutes() as $name => $route) {
      $entity_params = $this->entityParameterNames($route);
      if (!$entity_params) {
        continue;
      }

      $route_params = [];
      foreach ($entity_params as $param_name) {
        $this->assertArrayHasKey($param_name, $this->fixtures, "No fixture for route parameter '$param_name' on route '$name'.");
        $route_params[$param_name] = $this->fixtures[$param_name]->id();
      }

      $this->assertFalse(
        $access_manager->checkNamedRoute($name, $route_params, $this->outsider),
        "Outsider (not the owner) must be denied on route '$name'."
      );
      $this->assertTrue(
        $access_manager->checkNamedRoute($name, $route_params, $this->owner),
        "Owner must be allowed on route '$name'."
      );
      $this->assertTrue(
        $access_manager->checkNamedRoute($name, $route_params, $this->admin),
        "administer hivelog must be allowed on route '$name'."
      );
    }
  }

}
