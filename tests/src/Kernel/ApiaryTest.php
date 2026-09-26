<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Apiary entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryTest extends KernelTestBase {

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
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user;

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
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $this->user = User::create([
      'name' => 'testuser',
      'mail' => 'test@example.com',
    ]);
    $this->user->save();
  }

  /**
   * Tests basic apiary creation and field values.
   */
  public function testCreateApiary(): void {
    $apiary = Apiary::create([
      'name' => 'Home Apiary',
      'location' => 'Back garden, Dublin',
      'geolocation' => 'POINT (-6.2603 53.3498)',
      'notes' => 'Sheltered site near hedge.',
      'uid' => $this->user->id(),
    ]);
    $apiary->save();

    $this->assertNotEmpty($apiary->id());

    // Reload from storage.
    $loaded = Apiary::load($apiary->id());
    $this->assertEquals('Home Apiary', $loaded->label());
    $this->assertEquals('Back garden, Dublin', $loaded->get('location')->value);
    $this->assertEquals('Sheltered site near hedge.', $loaded->get('notes')->value);
    $this->assertNotEmpty($loaded->get('created')->value);
    $this->assertNotEmpty($loaded->get('changed')->value);
  }

  /**
   * Tests `ai_insights_enabled` defaults to FALSE on a new apiary.
   *
   * The per-apiary opt-in consent field for
   * [[0087-ai-insights-hosting-and-privacy-model]], consumed by the
   * optional `collective` submodule (task 0089) — tested here, not in
   * `collective`'s own tests, since the field itself belongs to core's
   * Apiary entity, per [[0098-nanoprobe-collective-locutus-submodule-split]].
   */
  public function testAiInsightsEnabledDefaultsToFalse(): void {
    $apiary = Apiary::create(['name' => 'Default Consent Apiary']);
    $this->assertFalse((bool) $apiary->get('ai_insights_enabled')->value);
  }

  /**
   * Tests geolocation coordinate field.
   */
  public function testGeolocation(): void {
    // Geofield stores coordinates as WKT: POINT (longitude latitude).
    $apiary = Apiary::create([
      'name' => 'Mountain Apiary',
      'geolocation' => 'POINT (-6.2603 53.3498)',
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEqualsWithDelta(53.3498, (float) $loaded->get('geolocation')->lat, 0.0001);
    $this->assertEqualsWithDelta(-6.2603, (float) $loaded->get('geolocation')->lon, 0.0001);
  }

  /**
   * Tests that geolocation field is optional.
   */
  public function testGeolocationOptional(): void {
    $apiary = Apiary::create([
      'name' => 'No GPS Apiary',
      'location' => 'Somewhere rural',
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEmpty($loaded->get('geolocation')->lat);
    $this->assertEmpty($loaded->get('geolocation')->lon);
  }

  /**
   * Tests the owner (uid) field.
   */
  public function testOwner(): void {
    $apiary = Apiary::create([
      'name' => 'Owned Apiary',
      'uid' => $this->user->id(),
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEquals($this->user->id(), $loaded->getOwnerId());
    $this->assertEquals('testuser', $loaded->getOwner()->getAccountName());
  }

  /**
   * Tests updating an apiary.
   */
  public function testUpdateApiary(): void {
    $apiary = Apiary::create([
      'name' => 'Original Name',
      'location' => 'Original location',
    ]);
    $apiary->save();
    $original_changed = $loaded = Apiary::load($apiary->id())->get('changed')->value;

    // Allow time difference.
    sleep(1);

    $apiary->set('name', 'Updated Name');
    $apiary->set('location', 'New location');
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEquals('Updated Name', $loaded->label());
    $this->assertEquals('New location', $loaded->get('location')->value);
  }

  /**
   * Tests deleting an apiary.
   */
  public function testDeleteApiary(): void {
    $apiary = Apiary::create(['name' => 'To Delete']);
    $apiary->save();
    $id = $apiary->id();

    $apiary->delete();

    $this->assertNull(Apiary::load($id));
  }

  /**
   * Tests apiary page child listing only includes accessible hives.
   */
  public function testApiaryViewFiltersInaccessibleHives(): void {
    $apiary = Apiary::create([
      'name' => 'Restricted View Apiary',
    ]);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Hidden Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $viewer = User::create([
      'name' => 'apiary-viewer',
      'mail' => 'apiary-viewer@example.com',
    ]);
    $viewer->save();

    $role = Role::create([
      'id' => 'apiary_view_only',
      'label' => 'Apiary view only',
    ]);
    $role->grantPermission('view any apiary');
    $role->save();
    $authenticated = Role::load('authenticated');
    if ($authenticated) {
      $authenticated->revokePermission('view any hive');
      $authenticated->revokePermission('edit any hive');
      $authenticated->revokePermission('delete any hive');
      $authenticated->save();
    }
    $viewer->addRole('apiary_view_only');
    $viewer->save();

    \Drupal::currentUser()->setAccount($viewer);

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringNotContainsString('Hidden Hive', $html);
    $this->assertStringContainsString(
      'No hives have been added to this apiary yet.',
      $html
    );
  }

  /**
   * Tests the page-owned Edit/Delete button group appears with access.
   *
   * Task 0118 — Apiary previously had no page-owned actions at all,
   * relying entirely on the Navigation module's top bar. Mirrors
   * `ApiClientControllerTest::testViewRendersForAuthorizedUser()`'s own
   * "renders for an authorized user" pattern.
   */
  public function testActionsAppearWithUpdateAndDeleteAccess(): void {
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();
    \Drupal::currentUser()->setAccount($admin);

    $apiary = Apiary::create(['name' => 'Actions Test Apiary']);
    $apiary->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertEquals('hivelog:button-group', $build['actions']['#component']);
    $labels = array_column($build['actions']['#props']['buttons'], 'label');
    $this->assertContains('Edit', $labels);
    $this->assertContains('Delete', $labels);
  }

  /**
   * Tests the page-owned Edit/Delete button group is absent without access.
   *
   * Mirrors `ApiClientControllerTest::testRegenerateActionHiddenWithoutUpdateAccess()`.
   */
  public function testActionsAbsentWithoutUpdateOrDeleteAccess(): void {
    // The first user created in a kernel test becomes uid 1, which
    // bypasses every permission check entirely (Drupal core
    // behaviour) — a throwaway user here ensures $viewer's "cannot"
    // assertion below actually exercises the access check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $apiary = Apiary::create(['name' => 'View Only Apiary']);
    $apiary->save();

    $viewer = User::create(['name' => 'view-only', 'mail' => 'view-only@example.com']);
    $viewer->save();
    $role = Role::create(['id' => 'apiary_view_only_2', 'label' => 'Apiary view only']);
    $role->grantPermission('view any apiary');
    $role->save();
    $viewer->addRole('apiary_view_only_2');
    $viewer->save();

    \Drupal::currentUser()->setAccount($viewer);

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertSame([], $build['actions']);
  }

  /**
   * Tests global hive and inspection collection routes are registered.
   */
  public function testGlobalCollectionRoutesAndMenuLinksExist(): void {
    $route_provider = \Drupal::service('router.route_provider');
    $hive_route = $route_provider->getRouteByName('entity.hive.collection');
    $inspection_route = $route_provider->getRouteByName('entity.hive_inspection.collection');
    $queen_route = $route_provider->getRouteByName('entity.queen.collection');
    $observation_route = $route_provider->getRouteByName('entity.queen_observation.collection');

    $this->assertEquals('/hivelog/hives', $hive_route->getPath());
    $this->assertEquals('/hivelog/inspections', $inspection_route->getPath());
    $this->assertEquals('/hivelog/queens', $queen_route->getPath());
    $this->assertEquals('/hivelog/queen-observations', $observation_route->getPath());
    $this->assertEquals('view own hive+view any hive+administer hivelog', $hive_route->getRequirement('_permission'));
    $this->assertEquals('view own hive inspection+view any hive inspection+administer hivelog', $inspection_route->getRequirement('_permission'));
    $this->assertEquals('view own queen+view any queen+administer hivelog', $queen_route->getRequirement('_permission'));
    $this->assertEquals('view own queen observation+view any queen observation+administer hivelog', $observation_route->getRequirement('_permission'));

    // Menu link plugin IDs are `hivelog.nav_item:<key>` since task 0119
    // (derived from HivelogAppNavBuilder::getAllItems(), not the
    // hand-written `hivelog.hives` / `hivelog.inspections` / … IDs
    // hivelog.links.menu.yml used before) — see HivelogMenuLinksTest for
    // the exhaustive one-to-one check against every nav item.
    $menu_links = \Drupal::service('plugin.manager.menu.link')->getDefinitions();
    $this->assertArrayHasKey('hivelog.nav_item:hives', $menu_links);
    $this->assertArrayHasKey('hivelog.nav_item:inspections', $menu_links);
    $this->assertArrayHasKey('hivelog.nav_item:queens', $menu_links);
    $this->assertArrayHasKey('hivelog.nav_item:queen_observations', $menu_links);
    $this->assertEquals('entity.hive.collection', $menu_links['hivelog.nav_item:hives']['route_name']);
    $this->assertEquals('entity.hive_inspection.collection', $menu_links['hivelog.nav_item:inspections']['route_name']);
    $this->assertEquals('entity.queen.collection', $menu_links['hivelog.nav_item:queens']['route_name']);
    $this->assertEquals('entity.queen_observation.collection', $menu_links['hivelog.nav_item:queen_observations']['route_name']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.nav_item:hives']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.nav_item:inspections']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.nav_item:queens']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.nav_item:queen_observations']['parent']);
  }

  /**
   * Tests the Seasonal Calendar heading's "View all Logs" link (task 0121).
   *
   * `entity.apiary_action_log.collection` otherwise has no inbound link
   * anywhere in the UI. Visible only to a user who can actually reach
   * it, per the task's own "link visible to users with the route's
   * permission and hidden otherwise" requirement.
   */
  public function testApiaryViewCalendarHeadingLinksToLogsWithAccess(): void {
    $this->installConfig(['system']);

    $role = Role::create(['id' => 'apiary_log_viewer', 'label' => 'Apiary Log Viewer']);
    $role->grantPermission('view any apiary action log');
    $role->save();
    $user = User::create(['name' => 'apiary-log-viewer', 'mail' => 'apiary-log-viewer@example.com']);
    $user->addRole('apiary_log_viewer');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $apiary = Apiary::create(['name' => 'Log Link Apiary']);
    $apiary->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('View all Logs', $html);
    $this->assertStringContainsString('/hivelog/apiary-action-logs', $html);
  }

  /**
   * Tests the "View all Logs" link is hidden without access (task 0121).
   */
  public function testApiaryViewCalendarHeadingHidesLogsLinkWithoutAccess(): void {
    $this->installConfig(['system']);

    $user = User::create(['name' => 'no-apiary-log-access', 'mail' => 'no-apiary-log-access@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $apiary = Apiary::create(['name' => 'No Log Access Apiary']);
    $apiary->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringNotContainsString('View all Logs', $html);
  }

}
