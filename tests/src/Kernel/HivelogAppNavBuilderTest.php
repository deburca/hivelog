<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the in-app secondary navigation (task 0105).
 *
 * Covers `HivelogAppNavBuilder` directly and `hivelog_preprocess_page()`'s
 * own path-matching — see that function's docblock in `hivelog.module`
 * for why `page.content` injection was chosen over a placed block or
 * `hook_page_top()`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogAppNavBuilderTest extends KernelTestBase {

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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests every built-in item appears for an administrator (task 0148).
   *
   * `hives`/`inspections`/`queens`/`queen_observations`/
   * `inventory_items`/`inventory_purchases`/`products` all nest under
   * `apiaries` now — only `dashboard` and `apiaries` themselves are
   * top-level keys in this bare (no submodule) environment.
   */
  public function testAllBuiltInItemsAppearForAdministrator(): void {
    $this->loginAdmin();

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $this->assertArrayHasKey('dashboard', $build);
    $this->assertArrayHasKey('apiaries', $build);

    $apiaries_children = [
      'hives', 'inspections', 'queens', 'queen_observations',
      'inventory_items', 'inventory_purchases', 'products',
    ];
    foreach ($apiaries_children as $key) {
      $this->assertArrayHasKey($key, $build['apiaries']['submenu'], "'$key' must appear in the Apiaries submenu.");
      $this->assertArrayNotHasKey($key, $build, "'$key' must not also appear at the top level.");
    }

    // `insights` is deliberately absent here even for an administrator:
    // its own accessibility (`InsightsPageAccessCheck`) requires at
    // least one `parent: 'insights'` item, and this bare environment
    // (no submodule installed) has none — see
    // `testInsightsItemAccessibleOnceChildItemRegistered()`.
    $this->assertArrayNotHasKey('insights', $build);
  }

  /**
   * Tests every built-in item's `parent` key (task 0147, ADR-0104).
   *
   * "Apiaries" and the "Insights" item are primary (no `parent`); every
   * other built-in nests under "Apiaries".
   */
  public function testBuiltInItemsCarryTheExpectedParent(): void {
    $items = \Drupal::service('hivelog.app_nav_builder')->getAllItems();

    $this->assertArrayNotHasKey('parent', $items['apiaries']);
    $this->assertArrayNotHasKey('parent', $items['insights']);
    $apiaries_children = [
      'hives', 'inspections', 'queens', 'queen_observations',
      'inventory_items', 'inventory_purchases', 'products',
    ];
    foreach ($apiaries_children as $key) {
      $this->assertEquals('apiaries', $items[$key]['parent'], "'$key' must declare parent: 'apiaries'.");
    }
  }

  /**
   * Tests the "Insights" built-in resolves to the real route (task 0146).
   */
  public function testInsightsItemResolvesTheInsightsRoute(): void {
    $items = \Drupal::service('hivelog.app_nav_builder')->getAllItems();

    $this->assertEquals('hivelog.insights', $items['insights']['url']->getRouteName());
    $this->assertEquals('insights', $items['insights']['group']);
  }

  /**
   * Tests "Insights" becomes accessible once a `parent: 'insights'` item exists.
   *
   * Complements `testAllBuiltInItemsAppearForAdministrator()`'s
   * negative case above — same admin account, only difference is
   * `hivelog_app_nav_test` (task 0146) being installed.
   */
  public function testInsightsItemAccessibleOnceChildItemRegistered(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $this->loginAdmin();

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $this->assertArrayHasKey('insights', $build);
    $this->assertArrayHasKey('hivelog_app_nav_test_widget', $build['insights']['submenu']);
  }

  /**
   * Tests `getAccessibleChildren()` orders its results by weight.
   *
   * Caught live on `cms2` while verifying this task: with all three
   * real `insights`-group items installed, `InsightsController`'s page
   * listed them in module-invocation order, not weight order, because
   * `getAccessibleChildren()` never sorted its result — invisible in
   * task 0146, where only one test-only item ever existed at once.
   */
  public function testGetAccessibleChildrenOrderedByWeight(): void {
    $this->enableModules(['key', 'collective', 'nanoprobe', 'nexus']);
    $this->installEntitySchema('api_client');
    $this->installEntitySchema('ai_provider_config');
    $this->installEntitySchema('sensor_device');
    \Drupal::service('router.builder')->rebuild();
    $this->loginAdmin();

    $children = \Drupal::service('hivelog.app_nav_builder')->getAccessibleChildren('insights');

    // Weights 9, 10, 11 (task 0147) — collective, nexus, nanoprobe.
    $this->assertSame(
      ['collective_api_clients', 'nexus_ai_provider_configs', 'nanoprobe_sensor_devices'],
      array_keys($children),
    );
  }

  /**
   * Tests an unresolvable `parent` falls back to the top level.
   *
   * Gap in task 0148's own coverage, closed here: its acceptance
   * criteria named this case explicitly, but no fixture existed yet to
   * exercise it — `hivelog_app_nav_test`'s `parent: 'nonexistent_hub'`
   * item (added for task 0150's own fallback test) covers it now too.
   */
  public function testUnresolvableParentFallsBackToTopLevel(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $this->loginAdmin();

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $this->assertArrayHasKey('hivelog_app_nav_test_orphan', $build);
    $this->assertArrayNotHasKey('nonexistent_hub', $build);
  }

  /**
   * Tests a user with no relevant permissions sees an empty nav.
   *
   * Not the anonymous account itself (which would also bypass this via
   * a different path) — a real, authenticated user genuinely lacking
   * every permission these 8 routes require. A throwaway user consumes
   * uid 1 first — the first user created in a kernel test becomes uid
   * 1, which bypasses every permission check entirely, exactly the
   * outcome this test needs to rule out.
   */
  public function testNoAccessibleItemsProducesEmptyNav(): void {
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'no_permissions', 'label' => 'No Permissions']);
    $role->save();
    $user = User::create(['name' => 'nobody', 'mail' => 'nobody@example.com']);
    $user->addRole('no_permissions');
    $user->save();
    $this->setCurrentUser($user);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $this->assertSame([], $build);
  }

  /**
   * Tests items are sorted by group then weight (task 0120/0148).
   *
   * The top level has no separator at all since task 0148 — with only
   * `dashboard` and `apiaries` accessible here, there's nothing for one
   * to usefully divide. Apiaries' own submenu still gets one between
   * its `records`- and `inventory`-sourced children.
   */
  public function testItemsAreSortedByGroupThenWeight(): void {
    $this->loginAdmin();

    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $top_level_keys = array_keys(array_filter($build, fn($k) => is_string($k) && !str_starts_with((string) $k, '#'), ARRAY_FILTER_USE_KEY));

    // The separator between `records` and `inventory` moved into
    // Apiaries' own submenu (task 0148) — the top level has no
    // separator at all with only two primary items here.
    $this->assertSame(['dashboard', 'apiaries'], $top_level_keys);

    $submenu_keys = array_keys(array_filter($build['apiaries']['submenu'], fn($k) => is_string($k) && !str_starts_with((string) $k, '#'), ARRAY_FILTER_USE_KEY));
    $expected_submenu_keys = [
      'hives', 'inspections', 'queens', 'queen_observations',
      'hivelog_app_nav_separator_0',
      'inventory_items', 'inventory_purchases', 'products',
    ];
    $this->assertSame($expected_submenu_keys, $submenu_keys);
  }

  /**
   * Tests the hook is actually dispatched by the module handler.
   *
   * With no submodule implementing it here, an empty array is the
   * correct, expected result — this only confirms the invocation
   * itself doesn't error, matching
   * `SensorAlertCollectorTest::testHookIsDispatchedByModuleHandler()`'s
   * own reasoning for testing dispatch independent of any particular
   * implementation.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    $items = \Drupal::moduleHandler()->invokeAll('hivelog_app_nav_items');
    $this->assertIsArray($items);
  }

  /**
   * Tests `getAccessibleChildren()` returns nothing with no `parent` match.
   *
   * No submodule installed here declares `parent: 'insights'` — this
   * confirms the filter itself finds nothing, distinct from
   * `testGetAccessibleChildrenFindsRegisteredItem()`'s positive case.
   */
  public function testGetAccessibleChildrenEmptyWithNoMatchingParent(): void {
    $this->loginAdmin();
    $builder = \Drupal::service('hivelog.app_nav_builder');

    $this->assertSame([], $builder->getAccessibleChildren('insights'));
  }

  /**
   * Tests `getAccessibleChildren()` finds an installed test module's item.
   */
  public function testGetAccessibleChildrenFindsRegisteredItem(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $this->loginAdmin();
    $builder = \Drupal::service('hivelog.app_nav_builder');

    $children = $builder->getAccessibleChildren('insights');

    $this->assertArrayHasKey('hivelog_app_nav_test_widget', $children);
  }

  /**
   * Tests `getAccessibleChildren()` respects the account it's given.
   *
   * The exact bug this task's own review caught: silently falling back
   * to the current user instead of the account a caller (e.g.
   * `InsightsPageAccessCheck`, which `AccessManager::checkNamedRoute()`
   * may hand a specific account without switching who's logged in)
   * actually asked about would answer the wrong question.
   */
  public function testGetAccessibleChildrenRespectsExplicitAccountOverCurrentUser(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $this->loginAdmin();
    $builder = \Drupal::service('hivelog.app_nav_builder');

    $no_permissions = User::create(['name' => 'no-permissions', 'mail' => 'np@example.com']);
    $no_permissions->save();

    // Current user (set by loginAdmin() above) can access it:
    $this->assertNotEmpty($builder->getAccessibleChildren('insights'));
    // But the explicitly-passed account, with no permissions, cannot:
    $this->assertSame([], $builder->getAccessibleChildren('insights', $no_permissions));
  }

  /**
   * Sets up the current request as if routed to `$route_name`.
   *
   * Mirrors `testNavInjectedOnHivelogPath()`'s own established pattern
   * of mutating the existing current request rather than pushing a
   * fresh one (a fresh `Request::create()` has no session, which
   * `KernelTestBase`'s own teardown then trips over) — extended here to
   * also set `_route` (name) and the upcast entity parameter, both of
   * which `HivelogEntityHierarchy::resolveSubject()` needs and which
   * `testNavInjectedOnHivelogPath()` itself never required.
   */
  protected function setCurrentRequestRoute(string $route_name, array $upcast_parameters = []): void {
    $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
    $request = \Drupal::requestStack()->getCurrentRequest();
    $request->attributes->set('_route_object', $route);
    $request->attributes->set('_route', $route_name);
    foreach ($upcast_parameters as $name => $value) {
      $request->attributes->set($name, $value);
    }
  }

  /**
   * Logs in an administrator so every nav route is accessible.
   */
  protected function loginAdmin(): void {
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    $this->setCurrentUser($admin);
  }

  /**
   * Tests a collection route marks its own item `aria-current="page"`.
   */
  public function testActiveStateForCollectionRoute(): void {
    $this->loginAdmin();
    $this->setCurrentRequestRoute('entity.hive.collection');

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $hives_link = $build['apiaries']['submenu']['hives']['#attributes'];
    $this->assertContains('is-active', $hives_link['class']);
    $this->assertEquals('page', $hives_link['aria-current']);

    // The child is active, not Apiaries' own link — but Apiaries' own
    // wrapper carries `has-active-child` since one of its children is
    // (task 0148).
    $this->assertArrayNotHasKey('aria-current', $build['apiaries']['link']['#attributes']);
    $this->assertContains('has-active-child', $build['apiaries']['#attributes']['class']);
  }

  /**
   * Tests a canonical route marks its section `aria-current="true"`.
   *
   * `/hivelog/hive/22` in the task's own example — a page "about" a
   * hive without being the Hives collection route itself.
   */
  public function testActiveStateForCanonicalRoute(): void {
    $this->loginAdmin();
    $apiary = Apiary::create(['name' => 'Test Apiary', 'visibility' => 'private']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $this->setCurrentRequestRoute('entity.hive.canonical', ['hive' => $hive]);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $hives_link = $build['apiaries']['submenu']['hives']['#attributes'];
    $this->assertContains('is-active', $hives_link['class']);
    $this->assertEquals('true', $hives_link['aria-current']);
    $this->assertContains('has-active-child', $build['apiaries']['#attributes']['class']);
  }

  /**
   * Tests an edit route marks its section `aria-current="true"`.
   *
   * `/hivelog/hive/22/edit` in the task's own example.
   */
  public function testActiveStateForEditRoute(): void {
    $this->loginAdmin();
    $apiary = Apiary::create(['name' => 'Test Apiary', 'visibility' => 'private']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $this->setCurrentRequestRoute('entity.hive.edit_form', ['hive' => $hive]);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $hives_link = $build['apiaries']['submenu']['hives']['#attributes'];
    $this->assertContains('is-active', $hives_link['class']);
    $this->assertEquals('true', $hives_link['aria-current']);
  }

  /**
   * Tests a route with no matching section marks nothing active.
   *
   * The per-apiary financial report carries `{apiary}` — resolvable as
   * a subject — but is deliberately excluded
   * (`HivelogAppNavBuilder::NAV_EXCLUDED_SUBJECT_ROUTES`): it's a
   * report, not a "manage apiaries" page.
   */
  public function testActiveStateForRouteWithNoSection(): void {
    $this->loginAdmin();
    $apiary = Apiary::create(['name' => 'Test Apiary', 'visibility' => 'private']);
    $apiary->save();
    $this->setCurrentRequestRoute('hivelog.apiary.inventory_cost_report', ['apiary' => $apiary]);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    foreach ($build as $key => $wrapper) {
      if (!is_string($key) || str_starts_with($key, '#')) {
        continue;
      }
      $this->assertArrayNotHasKey('aria-current', $wrapper['link']['#attributes'], "Item '$key' must not be marked active.");
      $this->assertNotContains('has-active-child', $wrapper['#attributes']['class'], "Item '$key' must not have an active child.");

      foreach ($wrapper['submenu'] ?? [] as $child_key => $child) {
        if (!is_string($child_key) || str_starts_with($child_key, '#') || str_starts_with($child_key, 'hivelog_app_nav_separator_')) {
          continue;
        }
        $this->assertArrayNotHasKey('aria-current', $child['#attributes'], "Child '$child_key' of '$key' must not be marked active.");
      }
    }
  }

  /**
   * Tests the nav is injected into page.content on a `/hivelog` path.
   */
  public function testNavInjectedOnHivelogPath(): void {
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    $this->setCurrentUser($admin);

    // Set `_route_object` directly on the existing current request rather
    // than pushing a fresh one — hivelog_preprocess_page() only reads
    // the route's own declared path (getPath()), not the request URI,
    // and a freshly `Request::create()`'d request has no session,
    // which KernelTestBase's own teardown then trips over.
    $route = \Drupal::service('router.route_provider')->getRouteByName('entity.apiary.collection');
    \Drupal::requestStack()->getCurrentRequest()->attributes->set('_route_object', $route);

    $variables = ['page' => ['content' => []]];
    hivelog_preprocess_page($variables);

    $this->assertArrayHasKey('hivelog_app_nav', $variables['page']['content']);
  }

  /**
   * Tests the nav is absent on a non-`/hivelog` path.
   */
  public function testNavAbsentOnNonHivelogPath(): void {
    $route = \Drupal::service('router.route_provider')->getRouteByName('user.login');
    \Drupal::requestStack()->getCurrentRequest()->attributes->set('_route_object', $route);

    $variables = ['page' => ['content' => []]];
    hivelog_preprocess_page($variables);

    $this->assertArrayNotHasKey('hivelog_app_nav', $variables['page']['content']);
  }

}
