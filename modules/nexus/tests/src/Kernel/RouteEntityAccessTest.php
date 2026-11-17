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
use Symfony\Component\Routing\Route;

/**
 * Tests every nexus.routing.yml route with an entity parameter (0133).
 *
 * Mirrors `\Drupal\Tests\collective\Kernel\RouteEntityAccessTest` exactly
 * — `AiProviderConfigAccessControlHandler`'s "own" permission is also a
 * flat owner check with no apiary dimension.
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
    'key',
    'hivelog',
    'collective',
    'nexus',
  ];

  /**
   * Routes with no entity parameter, and why they need no per-entity check.
   */
  protected const EXEMPT_ROUTES = [
    'entity.ai_provider_config.collection' => 'collection page; row filtering is HivelogListBuilder::load() (task 0124)',
    'entity.ai_provider_config.add_form' => 'administer hivelog only, no parent context to check',
  ];

  /**
   * The config owner — holds only `view own ai provider config` (task 0135).
   */
  protected User $owner;

  /**
   * A user with the same "own" permissions but no relation to the fixture.
   */
  protected User $outsider;

  /**
   * A user with every "any" permission but no relation to the fixture.
   */
  protected User $anyUser;

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
    $this->installEntitySchema('ai_provider_config');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    // Only `view` has an "own" permission (task 0135 removed `edit own`/
    // `delete own ai provider config` as dead config).
    $role = Role::create(['id' => 'own_config', 'label' => 'Own config']);
    $role->grantPermission('view own ai provider config');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('own_config');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('own_config');
    $this->outsider->save();

    $any_role = Role::create(['id' => 'any_config', 'label' => 'Any config']);
    $any_role->grantPermission('view any ai provider config');
    $any_role->grantPermission('edit any ai provider config');
    $any_role->grantPermission('delete any ai provider config');
    $any_role->save();

    $this->anyUser = User::create(['name' => 'any_user', 'mail' => 'any_user@example.com']);
    $this->anyUser->addRole('any_config');
    $this->anyUser->save();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $this->admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $this->admin->addRole('hivelog_admin');
    $this->admin->save();

    $config = AiProviderConfig::create([
      'label' => "Owner's Config",
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'anthropic_api_key',
      'uid' => $this->owner->id(),
    ]);
    $config->save();
    $this->fixtures['ai_provider_config'] = $config;
  }

  /**
   * This submodule's own routes, keyed by name.
   *
   * `hivelog` is also installed (nexus depends on it), so the router
   * contains hivelog core's routes too — filter by route name prefix to
   * this module's own routes only, not by path (every route in every
   * routing file here shares the `/hivelog` path prefix).
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The routes, keyed by route name.
   */
  protected function hivelogRoutes(): array {
    $provider = \Drupal::service('router.route_provider');
    $routes = [];
    foreach ($provider->getAllRoutes() as $name => $route) {
      if (str_starts_with($name, 'nexus.') || str_starts_with($name, 'entity.ai_provider_config.')) {
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
   * Tests every entity route denies an outsider, admin/"any" are allowed.
   *
   * The owner fixture only holds `view own ai provider config` (task
   * 0135 removed `edit own`/`delete own ai provider config`), so it's
   * allowed only on the `view` route and denied everywhere else, exactly
   * like a true outsider — determined generically from each route's own
   * `_entity_access` operation rather than hard-coding a route list.
   */
  public function testOutsiderDeniedOwnerViewOnlyAnyAndAdminAllowed(): void {
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

      $entity_access = $route->getRequirements()['_entity_access'] ?? '';
      $owner_should_be_allowed = str_ends_with($entity_access, '.view');

      $this->assertFalse(
        $access_manager->checkNamedRoute($name, $route_params, $this->outsider),
        "Outsider (not the owner) must be denied on route '$name'."
      );
      $this->assertSame(
        $owner_should_be_allowed,
        $access_manager->checkNamedRoute($name, $route_params, $this->owner),
        "Owner must be " . ($owner_should_be_allowed ? 'allowed' : 'denied') . " on route '$name'."
      );
      $this->assertTrue(
        $access_manager->checkNamedRoute($name, $route_params, $this->anyUser),
        "A user with 'any' permissions must be allowed on route '$name'."
      );
      $this->assertTrue(
        $access_manager->checkNamedRoute($name, $route_params, $this->admin),
        "administer hivelog must be allowed on route '$name'."
      );
    }
  }

}
