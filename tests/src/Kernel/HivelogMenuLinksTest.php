<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the `hivelog.nav_item` menu-link deriver (task 0119).
 *
 * `HivelogMenuLinks` derives one main-menu link per
 * `HivelogAppNavBuilder::getAllItems()` entry — this asserts that
 * derivation is exact (same keys, same title/route/weight), not just
 * "some links exist", so the two registries can never silently drift
 * apart the way the old hand-written `hivelog.links.menu.yml` entries
 * could.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogMenuLinksTest extends KernelTestBase {

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
   * Returns every `hivelog.nav_item:*` derivative definition, keyed.
   *
   * @return array<string, array>
   *   Menu link plugin definitions, keyed by derivative ID (the part
   *   after the `:`).
   */
  protected function derivedNavItemLinks(): array {
    $links = [];
    foreach (\Drupal::service('plugin.manager.menu.link')->getDefinitions() as $id => $definition) {
      if (str_starts_with($id, 'hivelog.nav_item:')) {
        $links[substr($id, strlen('hivelog.nav_item:'))] = $definition;
      }
    }
    return $links;
  }

  /**
   * Tests one derived menu link per nav item, no more, no fewer.
   */
  public function testDerivedLinksMatchNavItemsOneToOne(): void {
    $nav_items = \Drupal::service('hivelog.app_nav_builder')->getAllItems();
    $links = $this->derivedNavItemLinks();

    $this->assertEqualsCanonicalizing(
      array_keys($nav_items),
      array_keys($links),
      'Every nav item has exactly one derived menu link, and vice versa.'
    );
  }

  /**
   * Tests each derived link's title, route and weight match its nav item.
   */
  public function testDerivedLinksMatchNavItemFields(): void {
    $nav_items = \Drupal::service('hivelog.app_nav_builder')->getAllItems();
    $links = $this->derivedNavItemLinks();

    foreach ($nav_items as $key => $item) {
      $this->assertArrayHasKey($key, $links);
      $this->assertEquals((string) $item['title'], (string) $links[$key]['title']);
      $this->assertEquals($item['url']->getRouteName(), $links[$key]['route_name']);
      $this->assertEquals($item['url']->getRouteParameters(), $links[$key]['route_parameters']);
      $this->assertEquals($item['weight'], $links[$key]['weight']);
      $this->assertEquals('hivelog.admin', $links[$key]['parent']);
      $this->assertEquals('main', $links[$key]['menu_name']);
    }
  }

}
