<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests every nanoprobe.routing.yml route with an entity parameter (0133).
 *
 * Mirrors `\Drupal\Tests\hivelog\Kernel\RouteEntityAccessTest`, scoped to
 * this submodule's own routing file. `entity.sensor_device.add_form` is
 * `administer hivelog` only (no hive/apiary in the URL to scope against),
 * so it stays excluded — see `EXEMPT_ROUTES`. The two hive/apiary-scoped
 * add routes (`nanoprobe.sensor_device.add_for_hive`/`_apiary`) gained a
 * non-admin `add sensor device` permission in task 0135; they are
 * included in the functional outsider/owner loop below like every other
 * own/any-gated route, and the owner/outsider fixtures both hold that
 * permission at the role level so the loop actually exercises the
 * `_entity_access: hive.update`/`apiary.update` scoping — an outsider
 * with the bare permission but no access to this specific hive/apiary
 * must still be denied.
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
    'nanoprobe',
  ];

  /**
   * Routes with no entity parameter, and why they need no per-entity check.
   */
  protected const EXEMPT_ROUTES = [
    'nanoprobe.sensor_reading.ingest' => 'token-authenticated device endpoint, no Drupal session',
    'entity.sensor_device.collection' => 'collection page; row filtering is HivelogListBuilder::load() (task 0124)',
    'entity.sensor_device.add_form' => 'administer hivelog only, no parent context to check',
  ];

  /**
   * The apiary owner.
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
   * Fixture entities, keyed by route parameter name (== entity type ID).
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
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    foreach (['apiary', 'hive'] as $phrase) {
      $role->grantPermission('view own ' . $phrase);
      $role->grantPermission('edit own ' . $phrase);
    }
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->grantPermission('delete own sensor device');
    $role->grantPermission('add sensor device');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $this->admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $this->admin->addRole('hivelog_admin');
    $this->admin->save();

    $apiary = Apiary::create(['name' => 'Owner Apiary', 'uid' => $this->owner->id(), 'visibility' => 'private']);
    $apiary->save();
    $this->fixtures['apiary'] = $apiary;

    $hive = Hive::create([
      'name' => 'Owner Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();
    $this->fixtures['hive'] = $hive;

    $device = SensorDevice::create([
      'label' => 'Owner Device',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
    ]);
    $device->save();
    $this->fixtures['sensor_device'] = $device;
  }

  /**
   * This submodule's own routes, keyed by name.
   *
   * `hivelog` is also installed (nanoprobe depends on it), so the router
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
      if (str_starts_with($name, 'nanoprobe.') || str_starts_with($name, 'entity.sensor_device.')) {
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
   *
   * And that every route WITH an entity parameter declares at least one
   * of _entity_access / _entity_create_access / _custom_access.
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
   * Tests every own/any-gated entity route denies an unrelated user.
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
        "Outsider must be denied on route '$name'."
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

  /**
   * Tests a hive/apiary owner without `add sensor device` is still denied.
   *
   * Complements the generic outsider/owner loop above, which only proves
   * an outsider *with* the permission is denied by the hive/apiary
   * scoping — this proves the permission itself is still required even
   * for a user who genuinely owns the target hive/apiary.
   */
  public function testHiveOwnerWithoutPermissionDenied(): void {
    $access_manager = \Drupal::service('access_manager');

    $role = Role::create(['id' => 'no_add_permission', 'label' => 'No add permission']);
    $role->grantPermission('edit own apiary');
    $role->grantPermission('edit own hive');
    $role->save();

    $unprivileged_owner = User::create([
      'name' => 'unprivileged_owner',
      'mail' => 'unprivileged_owner@example.com',
    ]);
    $unprivileged_owner->addRole('no_add_permission');
    $unprivileged_owner->save();

    $apiary = Apiary::create([
      'name' => 'Unprivileged Owner Apiary',
      'uid' => $unprivileged_owner->id(),
      'visibility' => 'private',
    ]);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Unprivileged Owner Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $unprivileged_owner->id(),
    ]);
    $hive->save();

    $this->assertFalse($access_manager->checkNamedRoute(
      'nanoprobe.sensor_device.add_for_hive',
      ['hive' => $hive->id()],
      $unprivileged_owner
    ), 'Owning the hive is not enough without the add sensor device permission.');
    $this->assertFalse($access_manager->checkNamedRoute(
      'nanoprobe.sensor_device.add_for_apiary',
      ['apiary' => $apiary->id()],
      $unprivileged_owner
    ), 'Owning the apiary is not enough without the add sensor device permission.');
  }

}
