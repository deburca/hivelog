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

    $menu_links = \Drupal::service('plugin.manager.menu.link')->getDefinitions();
    $this->assertArrayHasKey('hivelog.hives', $menu_links);
    $this->assertArrayHasKey('hivelog.inspections', $menu_links);
    $this->assertArrayHasKey('hivelog.queens', $menu_links);
    $this->assertArrayHasKey('hivelog.queen_observations', $menu_links);
    $this->assertEquals('entity.hive.collection', $menu_links['hivelog.hives']['route_name']);
    $this->assertEquals('entity.hive_inspection.collection', $menu_links['hivelog.inspections']['route_name']);
    $this->assertEquals('entity.queen.collection', $menu_links['hivelog.queens']['route_name']);
    $this->assertEquals('entity.queen_observation.collection', $menu_links['hivelog.queen_observations']['route_name']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.hives']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.inspections']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.queens']['parent']);
    $this->assertEquals('hivelog.admin', $menu_links['hivelog.queen_observations']['parent']);
  }

}
