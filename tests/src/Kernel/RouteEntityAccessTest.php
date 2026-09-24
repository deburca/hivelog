<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests every hivelog.routing.yml route with an entity parameter (0133).
 *
 * Reproduces and closes the IDOR found in the 2026-09-23 gap analysis:
 * every hivelog route checked only `_permission` (a coarse own/any/admin
 * gate), never `_entity_access`, so a user with "own" permissions but no
 * relationship to a specific record could still reach it by URL —
 * `access_manager->checkNamedRoute()` returned ALLOWED for another
 * user's apiary/hive canonical, edit and delete routes even though
 * `$entity->access()` correctly returned FALSE.
 *
 * Routes are discovered from the built router (`route_provider`), not
 * hand-listed, so a new route without the right requirement fails this
 * test automatically instead of silently shipping unprotected. Only
 * `hivelog` is installed, so the router contains exactly this module's
 * own routes — the submodules get their own copy of this test in their
 * own `tests/src/Kernel/` (nanoprobe, collective, nexus).
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
  ];

  /**
   * Routes with no entity parameter, and why they need no per-entity check.
   */
  protected const EXEMPT_ROUTES = [
    'hivelog.dashboard' => 'aggregates every apiary the current user can already view',
    'entity.apiary.collection' => 'collection page; row filtering is HivelogListBuilder::load() (task 0124)',
    'entity.apiary.add_form' => 'site-wide add, no parent context to check',
    'entity.hive.collection' => 'collection page; row filtering is task 0124',
    'entity.hive_inspection.collection' => 'collection page; row filtering is task 0124',
    'entity.queen.collection' => 'collection page; row filtering is task 0124',
    'entity.queen.add_form' => 'site-wide add, no parent context to check',
    'entity.queen_observation.collection' => 'collection page; row filtering is task 0124',
    'entity.calendar_action.collection' => 'collection page; CalendarActionController::collection() filters by access(view)',
    'entity.hive_action_log.collection' => 'collection page; row filtering is task 0124',
    'entity.apiary_action_log.collection' => 'collection page; row filtering is task 0124',
    'hivelog.apiaries.financial_report' => 'aggregates every apiary the current user can already view',
    'entity.inventory_item.collection' => 'collection page; row filtering is task 0124',
    'entity.inventory_item.add_form' => 'site-wide add, no parent context to check',
    'entity.inventory_purchase.collection' => 'collection page; row filtering is task 0124',
    'entity.inventory_purchase.add_form' => 'site-wide add, no parent context to check',
    'entity.product.collection' => 'collection page; row filtering is task 0124',
    'entity.product.add_form' => 'site-wide add, no parent context to check',
  ];

  /**
   * The apiary owner, member of every fixture entity's apiary.
   */
  protected User $owner;

  /**
   * A user with the same "own" + "add" permissions but no relation.
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
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    // The first user created in a kernel test becomes uid 1, which
    // bypasses every permission check — burn it before the real users.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $permissions = [];
    foreach ([
      'apiary', 'hive', 'hive inspection', 'queen', 'queen observation',
      'calendar action', 'hive action log', 'apiary action log',
      'inventory item', 'inventory purchase', 'product',
      'calendar action item requirement', 'calendar action product yield',
    ] as $phrase) {
      $permissions[] = 'view own ' . $phrase;
      $permissions[] = 'edit own ' . $phrase;
      $permissions[] = 'delete own ' . $phrase;
      $permissions[] = 'add ' . $phrase;
    }

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
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

    $apiary = Apiary::create([
      'name' => 'Owner Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
    ]);
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

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2026-06-15',
      'uid' => $this->owner->id(),
    ]);
    $inspection->save();
    $this->fixtures['hive_inspection'] = $inspection;

    $queen = Queen::create([
      'name' => 'Q-owner',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $queen->save();
    $this->fixtures['queen'] = $queen;

    $observation = QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2026-06-20',
      'health' => 'good',
      'uid' => $this->owner->id(),
    ]);
    $observation->save();
    $this->fixtures['queen_observation'] = $observation;

    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Owner Calendar Action',
      'description' => 'Desc.',
      'week_start' => 15,
      'uid' => $this->owner->id(),
    ]);
    $calendar_action->save();
    $this->fixtures['calendar_action'] = $calendar_action;

    $hive_action_log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $hive_action_log->save();
    $this->fixtures['hive_action_log'] = $hive_action_log;

    $apiary_action_log = ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $apiary_action_log->save();
    $this->fixtures['apiary_action_log'] = $apiary_action_log;

    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Owner Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();
    $this->fixtures['inventory_item'] = $item;

    $purchase = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 10,
      'unit_price' => 2,
      'uid' => $this->owner->id(),
    ]);
    $purchase->save();
    $this->fixtures['inventory_purchase'] = $purchase;

    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Owner Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $this->owner->id(),
    ]);
    $product->save();
    $this->fixtures['product'] = $product;

    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 2,
      'uid' => $this->owner->id(),
    ]);
    $requirement->save();
    $this->fixtures['calendar_action_item_requirement'] = $requirement;

    $yield = CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $product->id(),
      'quantity' => 5,
      'uid' => $this->owner->id(),
    ]);
    $yield->save();
    $this->fixtures['calendar_action_product_yield'] = $yield;
  }

  /**
   * Every route under /hivelog, keyed by route name.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The routes, keyed by route name.
   */
  protected function hivelogRoutes(): array {
    $provider = \Drupal::service('router.route_provider');
    $routes = [];
    foreach ($provider->getAllRoutes() as $name => $route) {
      if (str_starts_with($route->getPath(), '/hivelog')) {
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
   * of _entity_access / _entity_create_access / _custom_access — plain
   * _permission alone is exactly the bug this task fixes.
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
   * Tests every entity route denies an unrelated user and allows the owner.
   *
   * `$this->outsider` holds every "own" view/edit/delete/add permission
   * these routes require, but has no relationship to any fixture entity
   * — so a denial here can only come from the per-entity access check
   * this task adds, not from a missing coarse permission.
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
        $this->assertArrayHasKey(
          $param_name,
          $this->fixtures,
          "No fixture registered for route parameter '$param_name' on route '$name' — add one to setUp()."
        );
        $route_params[$param_name] = $this->fixtures[$param_name]->id();
      }

      $this->assertFalse(
        $access_manager->checkNamedRoute($name, $route_params, $this->outsider),
        "Outsider (no relation to the fixture apiary) must be denied on route '$name'."
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
   * Tests a truly anonymous account (no roles, no permissions) is denied.
   *
   * Ported from `PermissionMatrixTest::testAnonymousHasNoAccess()`
   * (task 0137) — that functional test's assertion is advisory-only in
   * CI, so the same claim needed a kernel-test copy in the hard gate.
   * Unlike `$this->outsider` (holds every "own" permission, just no
   * relationship to the fixture — the IDOR case this class otherwise
   * tests), a real `AnonymousUserSession` has no permissions at all, so
   * every route — not just entity-parameterized ones — must deny it.
   */
  public function testAnonymousDeniedOnEveryRoute(): void {
    $anonymous = new AnonymousUserSession();
    $access_manager = \Drupal::service('access_manager');

    foreach ($this->hivelogRoutes() as $name => $route) {
      $route_params = [];
      foreach ($this->entityParameterNames($route) as $param_name) {
        $route_params[$param_name] = $this->fixtures[$param_name]->id();
      }

      $this->assertFalse(
        $access_manager->checkNamedRoute($name, $route_params, $anonymous),
        "Anonymous must be denied on route '$name'."
      );
    }
  }

  /**
   * Routes gated by `apiary.update`, owner-only unlike every child type.
   *
   * Unlike every child type's own `update`, `apiary.update` is owner-only
   * (`ApiaryAccessControlHandler`: "Only apiary owner can edit the apiary
   * itself"). A mere beekeeper member is correctly denied on these; see
   * `testBeekeeperMemberDeniedOnApiaryOwnerOnlyRoutes()`.
   */
  protected const APIARY_OWNER_ONLY_ROUTES = [
    'entity.apiary.edit_form',
    'hivelog.hive.add',
    'hivelog.calendar_action.add',
    'hivelog.apiary_action_log.add',
    'hivelog.inventory_item.add',
    'hivelog.inventory_purchase.add',
    'hivelog.product.add',
  ];

  /**
   * Tests a beekeeper member (not owner) is allowed on member-scoped routes.
   *
   * `update` and `view` access resolve via `checkApiaryEditAccess()` /
   * `checkApiaryViewAccess()` — apiary membership, not ownership — for
   * every type EXCEPT Apiary's own `update` (owner-only, see
   * `APIARY_OWNER_ONLY_ROUTES`). `delete` also varies (owner-only on some
   * types, owner-or-creator on others; see `ApiaryScopedAccessTest`).
   * This excludes `*.delete_form` and `APIARY_OWNER_ONLY_ROUTES`, which
   * the beekeeper fixture (a member but neither owner nor creator of
   * anything) would correctly be denied on, and asserts every other
   * entity route instead.
   */
  public function testBeekeeperMemberAllowedOnMemberScopedRoutes(): void {
    $beekeeper = $this->addBeekeeperMemberToFixtureApiary();

    $access_manager = \Drupal::service('access_manager');
    foreach ($this->hivelogRoutes() as $name => $route) {
      if (str_ends_with($name, '.delete_form') || in_array($name, self::APIARY_OWNER_ONLY_ROUTES, TRUE)) {
        continue;
      }
      $entity_params = $this->entityParameterNames($route);
      if (!$entity_params) {
        continue;
      }

      $route_params = [];
      foreach ($entity_params as $param_name) {
        $route_params[$param_name] = $this->fixtures[$param_name]->id();
      }

      $this->assertTrue(
        $access_manager->checkNamedRoute($name, $route_params, $beekeeper),
        "Beekeeper member (not owner) must be allowed on member-scoped route '$name'."
      );
    }
  }

  /**
   * Tests a beekeeper member (not owner) is denied on apiary-owner-only routes.
   *
   * A beekeeper member can already edit every child entity in the apiary
   * (hives, inspections, queens, …) but not the apiary itself, nor add
   * new apiary-direct structure (a hive, a calendar action, inventory,
   * products) — that stays owner-only, matching
   * `ApiaryAccessControlHandler::checkAccess()`'s existing
   * `update`/`delete` restriction and `HiveAccessControlHandler`'s
   * owner-only `delete`.
   */
  public function testBeekeeperMemberDeniedOnApiaryOwnerOnlyRoutes(): void {
    $beekeeper = $this->addBeekeeperMemberToFixtureApiary();

    $access_manager = \Drupal::service('access_manager');
    foreach (self::APIARY_OWNER_ONLY_ROUTES as $name) {
      $route = $this->hivelogRoutes()[$name] ?? NULL;
      $this->assertNotNull($route, "Route '$name' not found — has it been renamed?");

      $route_params = [];
      foreach ($this->entityParameterNames($route) as $param_name) {
        $route_params[$param_name] = $this->fixtures[$param_name]->id();
      }

      $this->assertFalse(
        $access_manager->checkNamedRoute($name, $route_params, $beekeeper),
        "Beekeeper member (not owner) must be denied on apiary-owner-only route '$name'."
      );
    }
  }

  /**
   * Adds a fresh beekeeper-role user as a member of the fixture apiary.
   *
   * Resets storage/access caches so the membership change is seen by
   * subsequently loaded fixture entities.
   */
  protected function addBeekeeperMemberToFixtureApiary(): User {
    $beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $beekeeper->addRole('beekeeper');
    $beekeeper->save();

    $apiary = $this->fixtures['apiary'];
    $apiary->set('beekeepers', [$beekeeper->id()]);
    $apiary->save();

    $etm = \Drupal::entityTypeManager();
    foreach (array_keys($this->fixtures) as $entity_type_id) {
      $etm->getStorage($entity_type_id)->resetCache();
      $etm->getAccessControlHandler($entity_type_id)->resetCache();
    }

    return $beekeeper;
  }

  /**
   * Tests hivelog.hive_action_log.add rejects a mismatched calendar action.
   *
   * {calendar_action} belongs to a different apiary than {hive} — even
   * though the owner can update both entities individually, the route
   * must still deny, per CalendarActionApiaryMatchAccessCheck.
   */
  public function testHiveActionLogAddRejectsCalendarActionFromAnotherApiary(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary', 'uid' => $this->owner->id()]);
    $other_apiary->save();
    $other_action = CalendarAction::create([
      'apiary' => $other_apiary->id(),
      'title' => 'Other Calendar Action',
      'description' => 'Desc.',
      'week_start' => 20,
      'uid' => $this->owner->id(),
    ]);
    $other_action->save();

    $access = \Drupal::service('access_manager')->checkNamedRoute(
      'hivelog.hive_action_log.add',
      ['hive' => $this->fixtures['hive']->id(), 'calendar_action' => $other_action->id()],
      $this->owner
    );
    $this->assertFalse($access);
  }

  /**
   * Tests hivelog.apiary_action_log.add rejects a mismatched calendar action.
   */
  public function testApiaryActionLogAddRejectsCalendarActionFromAnotherApiary(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary 2', 'uid' => $this->owner->id()]);
    $other_apiary->save();
    $other_action = CalendarAction::create([
      'apiary' => $other_apiary->id(),
      'title' => 'Other Calendar Action 2',
      'description' => 'Desc.',
      'week_start' => 21,
      'uid' => $this->owner->id(),
    ]);
    $other_action->save();

    $access = \Drupal::service('access_manager')->checkNamedRoute(
      'hivelog.apiary_action_log.add',
      ['apiary' => $this->fixtures['apiary']->id(), 'calendar_action' => $other_action->id()],
      $this->owner
    );
    $this->assertFalse($access);
  }

}
