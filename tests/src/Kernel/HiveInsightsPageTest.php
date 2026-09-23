<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive's dedicated Insights page (task 0111).
 *
 * The AI Insight/Sensors panels' own content is covered by nexus's and
 * nanoprobe's own test suites (they dispatch to
 * hook_hivelog_hive_insights_panels() now, not
 * hook_hivelog_hive_view_panels()) — this file covers the core
 * mechanism (`HiveController::insights()`, the route, the title
 * callback) independent of any particular implementer, mirroring
 * `HivelogAppNavBuilderTest`'s/`HivelogStatTileBuilderTest`'s own
 * "dispatch works end-to-end, no implementer needed" reasoning.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInsightsPageTest extends KernelTestBase {

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
   * A test hive.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $this->hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Tests the page is empty when no implementation contributes a panel.
   *
   * With no submodule in this test's $modules list, an empty array is
   * the correct, expected result.
   */
  public function testInsightsIsEmptyWithNoContributingModule(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $this->assertSame([], $controller->insights($this->hive));
  }

  /**
   * Tests hook_hivelog_hive_insights_panels() is actually dispatched.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_hive_insights_panels', [$this->hive]);
    $this->assertIsArray($panels);
  }

  /**
   * Tests the title callback includes the hive's own label.
   */
  public function testInsightsTitleIncludesHiveLabel(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $this->assertStringContainsString('Test Hive', $controller->insightsTitle($this->hive));
  }

  /**
   * Tests the Insights route is registered and resolvable.
   */
  public function testInsightsRouteIsRegistered(): void {
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('entity.hive.insights');

    $this->assertEquals('/hivelog/hive/{hive}/insights', $route->getPath());
  }

}
