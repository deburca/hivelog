<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests serving HiveLog's pages under another path prefix (task 0246).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogPathPrefixTest extends KernelTestBase {

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
   * The route's path as the router holds it.
   */
  private function pathOf(string $route_name): string {
    return \Drupal::service('router.route_provider')->getRouteByName($route_name)->getPath();
  }

  /**
   * Unset, every page stays at /hivelog.
   */
  public function testDefaultPrefixIsUnchanged(): void {
    $this->assertSame('/hivelog', $this->pathOf('hivelog.dashboard'));
    $this->assertSame('/hivelog/apiary/{apiary}', $this->pathOf('entity.apiary.canonical'));
    $this->assertSame('/hivelog/hives', $this->pathOf('entity.hive.collection'));
  }

  /**
   * With the setting, routes, entity link templates and error pages move.
   */
  public function testConfiguredPrefixMovesEveryRoute(): void {
    $this->setSetting('hivelog_path_prefix', 'app');
    \Drupal::service('router.builder')->rebuild();

    $this->assertSame('/app', $this->pathOf('hivelog.dashboard'));
    $this->assertSame('/app/apiary/{apiary}', $this->pathOf('entity.apiary.canonical'));
    $this->assertSame('/app/apiary/{apiary}/edit', $this->pathOf('entity.apiary.edit_form'));
    $this->assertSame('/app/hives', $this->pathOf('entity.hive.collection'));
    $this->assertSame('/app/page-not-found', $this->pathOf('hivelog.page_not_found'));

    // Links built from route names follow.
    $this->assertSame('/app/hives', Url::fromRoute('entity.hive.collection')->toString());

    // Nothing of the module's is left under the old prefix.
    foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $name => $route) {
      $this->assertFalse(
        str_starts_with($route->getPath(), '/hivelog/') && !str_starts_with($route->getPath(), '/hivelog/api/'),
        "$name is still at {$route->getPath()}"
      );
    }
  }

  /**
   * The breadcrumb builder follows the configured prefix.
   */
  public function testBreadcrumbFollowsThePrefix(): void {
    $this->setSetting('hivelog_path_prefix', 'app');
    \Drupal::service('router.builder')->rebuild();

    $route = \Drupal::service('router.route_provider')->getRouteByName('entity.hive.collection');
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRouteName')->willReturn('entity.hive.collection');
    $route_match->method('getRouteObject')->willReturn($route);
    $this->assertTrue(\Drupal::service('hivelog.breadcrumb')->applies($route_match));
  }

  /**
   * An unusable value falls back to /hivelog.
   */
  public function testUnusableValueKeepsHivelog(): void {
    $this->setSetting('hivelog_path_prefix', 'admin');
    \Drupal::service('router.builder')->rebuild();
    $this->assertSame('/hivelog', $this->pathOf('hivelog.dashboard'));
  }

}
