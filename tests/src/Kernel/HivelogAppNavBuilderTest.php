<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

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
   * Tests every built-in item appears for an administrator.
   */
  public function testAllBuiltInItemsAppearForAdministrator(): void {
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    $this->setCurrentUser($admin);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();

    $expected_keys = [
      'apiaries', 'hives', 'inspections', 'queens', 'queen_observations',
      'inventory_items', 'inventory_purchases', 'products',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $build);
    }
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
   * Tests items are sorted by weight, built-ins first at their own weights.
   */
  public function testItemsAreSortedByWeight(): void {
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    $this->setCurrentUser($admin);

    $build = \Drupal::service('hivelog.app_nav_builder')->build();
    $keys = array_keys(array_filter($build, fn($k) => is_string($k) && !str_starts_with((string) $k, '#'), ARRAY_FILTER_USE_KEY));

    $expected_keys = [
      'apiaries', 'hives', 'inspections', 'queens', 'queen_observations',
      'inventory_items', 'inventory_purchases', 'products',
    ];
    $this->assertSame($expected_keys, $keys);
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
