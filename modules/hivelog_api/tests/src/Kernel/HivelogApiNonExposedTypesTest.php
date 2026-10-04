<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the types the app must not reach are really hidden by the allow-list.
 *
 * Inventory, products, components, sensors, API clients, AI providers and
 * insights are web-only. This enables every optional submodule so those
 * entity types exist, shows that plain JSON:API does serve them to an
 * administrator, and that the versioned API answers 404 for the same
 * administrator: the allow-list is what hides them, not a missing route.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiNonExposedTypesTest extends HivelogApiKernelTestBase {

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
    'key',
    'hivelog',
    'collective',
    'nanoprobe',
    'nexus',
    'hivelog_api',
  ];

  /**
   * Types that exist but must not be served by the versioned API.
   */
  protected const HIDDEN = [
    'inventory_item', 'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield',
    'calendar_action_item_requirement', 'calendar_action_product_yield', 'hive_component',
    'sensor_device', 'sensor_reading', 'sensor_reading_daily', 'api_client', 'ai_provider_config', 'hive_insight',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $schemas = [
      'harvest_yield', 'inventory_usage', 'sensor_device', 'sensor_reading', 'sensor_reading_daily',
      'api_client', 'ai_provider_config', 'hive_insight',
    ];
    foreach ($schemas as $type) {
      $this->installEntitySchema($type);
    }
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests plain JSON:API serves each type, the versioned API 404s it.
   */
  public function testHiddenTypesAre404OnTheVersionedApi(): void {
    $role = Role::create(['id' => 'hidden_admin', 'label' => 'Hidden admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $admin = User::create([
      'name' => 'hidden_admin',
      'mail' => 'h@example.com',
      'pass' => 'pw-hidden_admin',
      'status' => 1,
    ]);
    $admin->addRole('hidden_admin');
    $admin->save();

    foreach (self::HIDDEN as $type) {
      $this->assertSame(200, $this->api('GET', "/jsonapi/$type/$type", NULL, $admin)['status'], "$type exists in plain JSON:API");
      foreach (["/hivelog/api/v1/$type/$type", "/hivelog/api/v1/$type/$type/" . \Drupal::service('uuid')->generate()] as $path) {
        $this->assertSame(404, $this->api('GET', $path, NULL, $admin)['status'], $path);
      }
      $this->assertSame(404, $this->api('POST', "/hivelog/api/v1/$type/$type", ['data' => ['type' => "$type--$type"]], $admin)['status'], "POST $type");
    }
  }

}
