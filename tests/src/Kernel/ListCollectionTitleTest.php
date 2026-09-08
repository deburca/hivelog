<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that list pages don't repeat "HiveLog -" / the page title (task 0063).
 *
 * The entity-collection routes used to hard-code `_title: 'HiveLog -
 * Apiaries'` etc., and five list builders rendered a second
 * `hivelog-list-heading__title` `<h3>` with the same text minus the
 * prefix. Both are redundant inside the HiveLog area.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ListCollectionTitleTest extends KernelTestBase {

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1 (superuser), so list-builder access passes.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));
  }

  /**
   * Every collection route carries a bare title, no "HiveLog -" prefix.
   */
  public function testCollectionRouteTitlesHaveNoPrefix(): void {
    $expected = [
      'entity.apiary.collection' => 'Apiaries',
      'entity.hive.collection' => 'Hives',
      'entity.hive_inspection.collection' => 'Inspections',
      'entity.queen.collection' => 'Queens',
      'entity.queen_observation.collection' => 'Queen Observations',
      'entity.calendar_action.collection' => 'Calendar Actions',
      'entity.hive_action_log.collection' => 'Hive Action Logs',
      'entity.apiary_action_log.collection' => 'Apiary Action Logs',
      'entity.inventory_item.collection' => 'Inventory Items',
      'entity.inventory_purchase.collection' => 'Inventory Purchases',
      'entity.product.collection' => 'Products',
    ];
    $provider = \Drupal::service('router.route_provider');
    foreach ($expected as $route_name => $title) {
      $route = $provider->getRouteByName($route_name);
      $this->assertSame($title, $route->getDefault('_title'), $route_name);
      $this->assertStringNotContainsString('HiveLog', (string) $route->getDefault('_title'), $route_name);
    }
  }

  /**
   * The five custom list builders keep the action bar but drop the <h3>.
   */
  public function testListHeadingHasActionButNoTitle(): void {
    $renderer = \Drupal::service('renderer');
    foreach (['apiary', 'queen', 'inventory_item', 'inventory_purchase', 'product'] as $entity_type) {
      $build = \Drupal::entityTypeManager()->getListBuilder($entity_type)->render();
      $html = (string) $renderer->renderInIsolation($build);

      $this->assertStringContainsString('hivelog-list-heading', $html, $entity_type);
      $this->assertStringContainsString('hivelog-list-heading__action', $html, $entity_type);
      $this->assertStringNotContainsString('hivelog-list-heading__title', $html, $entity_type);
    }
  }

}
