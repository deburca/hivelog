<?php

namespace Drupal\Tests\hivelog\Unit\Breadcrumb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hivelog\Breadcrumb\HivelogBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for HivelogBreadcrumbBuilder.
 */
#[CoversClass(HivelogBreadcrumbBuilder::class)]
#[Group('hivelog')]
class HivelogBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The breadcrumb builder under test.
   *
   * @var \Drupal\hivelog\Breadcrumb\HivelogBreadcrumbBuilder
   */
  protected HivelogBreadcrumbBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Cache::mergeContexts() calls \Drupal::service('cache_contexts_manager')
    // inside an assert() statement. Provide a minimal stub so unit tests do
    // not require a fully-bootstrapped Drupal container.
    $cache_contexts_manager = new class {

      /**
       * Stub for assertValidTokens().
       */
      public function assertValidTokens(array $tokens): bool {
        return TRUE;
      }

    };
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $this->builder = new HivelogBreadcrumbBuilder($entity_type_manager);
    $this->builder->setStringTranslation($this->getStringTranslationStub());
  }

  // -------------------------------------------------------------------------
  // applies() tests
  // -------------------------------------------------------------------------

  /**
   * Tests that applies() returns TRUE for apiary entity routes.
   */
  #[DataProvider('apiaryRouteProvider')]
  public function testAppliesReturnsTrueForApiaryRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for apiary entity routes.
   */
  public static function apiaryRouteProvider(): array {
    return [
      'collection' => ['entity.apiary.collection'],
      'canonical'  => ['entity.apiary.canonical'],
      'add_form'   => ['entity.apiary.add_form'],
      'edit_form'  => ['entity.apiary.edit_form'],
      'delete_form' => ['entity.apiary.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for hive entity routes.
   */
  #[DataProvider('hiveRouteProvider')]
  public function testAppliesReturnsTrueForHiveRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for hive entity routes.
   */
  public static function hiveRouteProvider(): array {
    return [
      'canonical'  => ['entity.hive.canonical'],
      'edit_form'  => ['entity.hive.edit_form'],
      'delete_form' => ['entity.hive.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for hive_inspection entity routes.
   */
  #[DataProvider('hiveInspectionRouteProvider')]
  public function testAppliesReturnsTrueForHiveInspectionRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for hive_inspection entity routes.
   */
  public static function hiveInspectionRouteProvider(): array {
    return [
      'canonical'  => ['entity.hive_inspection.canonical'],
      'edit_form'  => ['entity.hive_inspection.edit_form'],
      'delete_form' => ['entity.hive_inspection.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for queen entity routes.
   */
  #[DataProvider('queenRouteProvider')]
  public function testAppliesReturnsTrueForQueenRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for queen entity routes.
   */
  public static function queenRouteProvider(): array {
    return [
      'collection' => ['entity.queen.collection'],
      'canonical'  => ['entity.queen.canonical'],
      'add_form'   => ['entity.queen.add_form'],
      'edit_form'  => ['entity.queen.edit_form'],
      'delete_form' => ['entity.queen.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for queen observation entity routes.
   */
  #[DataProvider('queenObservationRouteProvider')]
  public function testAppliesReturnsTrueForQueenObservationRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for queen observation entity routes.
   */
  public static function queenObservationRouteProvider(): array {
    return [
      'collection' => ['entity.queen_observation.collection'],
      'canonical'  => ['entity.queen_observation.canonical'],
      'edit_form'  => ['entity.queen_observation.edit_form'],
      'delete_form' => ['entity.queen_observation.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for hivelog.* custom routes.
   */
  #[DataProvider('hivelogCustomRouteProvider')]
  public function testAppliesReturnsTrueForHivelogCustomRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for hivelog.* custom routes.
   */
  public static function hivelogCustomRouteProvider(): array {
    return [
      'hive add'        => ['hivelog.hive.add'],
      'inspection add'  => ['hivelog.inspection.add'],
      'queen add'       => ['hivelog.queen.add'],
      'observation add' => ['hivelog.queen_observation.add'],
    ];
  }

  /**
   * Tests that applies() returns FALSE for the future CSV export route.
   *
   * When Task 0001 (queen observation CSV export) is implemented, its route
   * hivelog.queen.observations_csv must be explicitly excluded from applies()
   * so that a file-download response does not receive a breadcrumb. This test
   * documents the expected behaviour and will fail as a reminder when the route
   * is added unless the exclusion is also added to applies() at that time.
   *
   * @see \Drupal\hivelog\Breadcrumb\HivelogBreadcrumbBuilder::applies()
   * @see docs/project-management/tasks/0001-queen-observation-csv-export.md
   * @see docs/project-management/tasks/0013-breadcrumb-route-audit.md
   */
  public function testAppliesReturnsFalseForCsvExportRoute(): void {
    // This route does not exist yet (Task 0001 is backlog). The test asserts
    // the intended future behaviour: the hivelog. catch-all must NOT match
    // file-download routes. When Task 0001 is implemented, add an explicit
    // exclusion to applies() and this test will confirm it is correct.
    $this->assertFalse($this->builder->applies($this->createRouteMatch('hivelog.queen.observations_csv')));
  }

  /**
   * Tests that applies() returns FALSE for unrelated routes.
   */
  #[DataProvider('unrelatedRouteProvider')]
  public function testAppliesReturnsFalseForUnrelatedRoutes(string $route_name): void {
    $this->assertFalse($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for routes the builder should not handle.
   */
  public static function unrelatedRouteProvider(): array {
    return [
      'front'           => ['<front>'],
      'node canonical'  => ['entity.node.canonical'],
      'user login'      => ['user.login'],
      'admin structure' => ['system.admin_structure'],
      'user register'   => ['user.register'],
    ];
  }

  // -------------------------------------------------------------------------
  // build() tests
  // -------------------------------------------------------------------------

  /**
   * Apiary collection: no entity parameters → 3 base links only.
   */
  public function testBuildApiaryCollection(): void {
    $route_match = $this->createRouteMatch('entity.apiary.collection');
    $route_match->method('getParameter')->willReturn(NULL);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(2, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('<front>', $links[0]->getUrl()->getRouteName());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('entity.apiary.collection', $links[1]->getUrl()->getRouteName());
    $this->assertContains('route', $breadcrumb->getCacheContexts());
  }

  /**
   * Apiary canonical: apiary label is the terminal crumb (rendered as plain text by the theme via loop.last).
   *
   * Home and HiveLog are navigable ancestors.
   */
  public function testBuildApiaryCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $route_match = $this->createRouteMatch('entity.apiary.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Apiary edit: apiary is an ancestor, so its link IS added (4 links total).
   */
  public function testBuildApiaryEditForm(): void {
    $apiary = $this->createApiaryMock(2, 'Mountain Apiary');
    $route_match = $this->createRouteMatch('entity.apiary.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Mountain Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 2], $links[2]->getUrl()->getRouteParameters());
    $this->assertContains('apiary:2', $breadcrumb->getCacheTags());
  }

  /**
   * Hive canonical: apiary is a navigable ancestor; hive label is the terminal crumb rendered as plain text by the theme.
   */
  public function testBuildHiveCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
  }

  /**
   * Hive edit: apiary and hive ancestor links are both added (5 links total).
   */
  public function testBuildHiveEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[3]->getUrl()->getRouteParameters());
  }

  /**
   * Inspection canonical: apiary and hive are navigable ancestors; inspection label is the terminal crumb rendered as plain text by the theme.
   */
  public function testBuildInspectionCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $inspection = $this->createInspectionMock(10, 'Inspection on 2024-06-15', $hive);
    $route_match = $this->createRouteMatch('entity.hive_inspection.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', $inspection],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertContains('hive_inspection:10', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Inspection edit: apiary, hive, and inspection ancestor links all added
   * (6 links total).
   */
  public function testBuildInspectionEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $inspection = $this->createInspectionMock(10, 'Inspection on 2024-06-15', $hive);
    $route_match = $this->createRouteMatch('entity.hive_inspection.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', $inspection],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive_inspection' => 10], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * hivelog.hive.add carries an {apiary} parameter; the apiary link is added.
   */
  public function testBuildHiveAddRoute(): void {
    $apiary = $this->createApiaryMock(3, 'Garden Apiary');
    $route_match = $this->createRouteMatch('hivelog.hive.add');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Garden Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
  }

  /**
   * hivelog.inspection.add carries a {hive} parameter; apiary and hive added.
   */
  public function testBuildInspectionAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(7, 'Hive Beta', $apiary);
    $route_match = $this->createRouteMatch('hivelog.inspection.add');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Beta', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
  }

  /**
   * Queen canonical: apiary and hive are navigable ancestors; queen is terminal.
   */
  public function testBuildQueenCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('entity.queen.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertContains('queen:20', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Queen edit: apiary, hive, and queen ancestor links are all added
   * (6 links total).
   */
  public function testBuildQueenEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('entity.queen.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 20], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Queen observation canonical: apiary/hive/queen are ancestors; obs terminal.
   */
  public function testBuildQueenObservationCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $observation = $this->createObservationMock(30, 'Observation A', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('Observation A', (string) $links[5]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[5]->getUrl()->getRouteName());
    $this->assertContains('queen_observation:30', $breadcrumb->getCacheTags());
    $this->assertContains('queen:20', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Queen observation edit: apiary, hive, queen, and observation ancestor
   * links are added (7 links total).
   */
  public function testBuildQueenObservationEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $observation = $this->createObservationMock(30, 'Observation A', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('Observation A', (string) $links[5]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[5]->getUrl()->getRouteName());
  }

  /**
   * hivelog.queen_observation.add: apiary, hive, and queen links added.
   */
  public function testBuildQueenObservationAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('hivelog.queen_observation.add');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
      ['queen_observation', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
  }

  /**
   * hivelog.queen.add carries a {hive} parameter; apiary and hive are added.
   */
  public function testBuildQueenAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(9, 'Hive Gamma', $apiary);
    $route_match = $this->createRouteMatch('hivelog.queen.add');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
      ['queen', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Gamma', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
  }

  /**
   * Apiary delete: same as edit — apiary ancestor link IS added.
   */
  public function testBuildApiaryDeleteForm(): void {
    $apiary = $this->createApiaryMock(2, 'Mountain Apiary');
    $route_match = $this->createRouteMatch('entity.apiary.delete_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Mountain Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
  }

  /**
   * Hive delete: apiary and hive ancestor links are both added.
   */
  public function testBuildHiveDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.delete_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
  }

  /**
   * Inspection delete: apiary, hive, and inspection ancestor links all added.
   */
  public function testBuildInspectionDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $inspection = $this->createInspectionMock(10, 'Inspection on 2024-06-15', $hive);
    $route_match = $this->createRouteMatch('entity.hive_inspection.delete_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', $inspection],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[4]->getUrl()->getRouteName());
  }

  /**
   * Queen delete: apiary, hive, and queen ancestor links all added.
   */
  public function testBuildQueenDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('entity.queen.delete_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
  }

  /**
   * Queen observation delete: apiary, hive, queen, and observation links added.
   */
  public function testBuildQueenObservationDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $observation = $this->createObservationMock(30, 'Observation A', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.delete_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('Observation A', (string) $links[5]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[5]->getUrl()->getRouteName());
  }

  /**
   * Unassigned queen canonical: no hive ancestry; trail is Home › HiveLog › Queen (queen label is the terminal crumb rendered as plain text).
   */
  public function testBuildQueenCanonicalUnassigned(): void {
    $queen = $this->createUnassignedQueenMock(21, 'Q-2023-archived');
    $route_match = $this->createRouteMatch('entity.queen.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen (no apiary or hive because queen has no hive).
    $this->assertCount(3, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('entity.queen.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertContains('queen:21', $breadcrumb->getCacheTags());
  }

  /**
   * Unassigned queen edit: trail is Home › HiveLog › Queen (no hive ancestry).
   */
  public function testBuildQueenEditFormUnassigned(): void {
    $queen = $this->createUnassignedQueenMock(21, 'Q-2023-archived');
    $route_match = $this->createRouteMatch('entity.queen.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen (no apiary or hive).
    $this->assertCount(3, $links);
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('entity.queen.canonical', $links[2]->getUrl()->getRouteName());
  }

  /**
   * Observation canonical whose queen is unassigned: queen is a navigable ancestor; observation label is the terminal crumb rendered as plain text.
   *
   * Apiary and hive are skipped because the queen has no hive.
   */
  public function testBuildObservationCanonicalQueenUnassigned(): void {
    $queen = $this->createUnassignedQueenMock(21, 'Q-2023-archived');
    $observation = $this->createObservationMock(31, 'Observation B', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen + Observation.
    $this->assertCount(4, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('entity.queen.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Observation B', (string) $links[3]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('queen_observation:31', $breadcrumb->getCacheTags());
    $this->assertContains('queen:21', $breadcrumb->getCacheTags());
  }

  /**
   * Observation edit whose queen is unassigned: trail is Home › HiveLog › Queen › Observation.
   */
  public function testBuildObservationEditFormQueenUnassigned(): void {
    $queen = $this->createUnassignedQueenMock(21, 'Q-2023-archived');
    $observation = $this->createObservationMock(31, 'Observation B', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.edit_form');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen + Observation.
    $this->assertCount(4, $links);
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('Observation B', (string) $links[3]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[3]->getUrl()->getRouteName());
  }

  // -------------------------------------------------------------------------
  // Helper methods
  // -------------------------------------------------------------------------

  /**
   * Creates a mock RouteMatchInterface for the given route name.
   */
  private function createRouteMatch(string $route_name): RouteMatchInterface {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route_name);
    return $route_match;
  }

  /**
   * Creates a mock Apiary entity.
   */
  private function createApiaryMock(int $id, string $label): ContentEntityInterface {
    $apiary = $this->createMock(ContentEntityInterface::class);
    $apiary->method('id')->willReturn($id);
    $apiary->method('label')->willReturn($label);
    $apiary->method('getCacheTags')->willReturn(["apiary:$id"]);
    $apiary->method('getCacheContexts')->willReturn([]);
    $apiary->method('getCacheMaxAge')->willReturn(-1);
    return $apiary;
  }

  /**
   * Creates a mock Hive entity referencing the given apiary.
   */
  private function createHiveMock(int $id, string $label, ContentEntityInterface $apiary): ContentEntityInterface {
    $hive = $this->createMock(ContentEntityInterface::class);
    $hive->method('id')->willReturn($id);
    $hive->method('label')->willReturn($label);
    $hive->method('getCacheTags')->willReturn(["hive:$id"]);
    $hive->method('getCacheContexts')->willReturn([]);
    $hive->method('getCacheMaxAge')->willReturn(-1);

    $apiary_ref = new \stdClass();
    $apiary_ref->entity = $apiary;
    $hive->method('get')->with('apiary')->willReturn($apiary_ref);

    return $hive;
  }

  /**
   * Creates a mock HiveInspection entity referencing the given hive.
   */
  private function createInspectionMock(int $id, string $label, ContentEntityInterface $hive): ContentEntityInterface {
    $inspection = $this->createMock(ContentEntityInterface::class);
    $inspection->method('id')->willReturn($id);
    $inspection->method('label')->willReturn($label);
    $inspection->method('getCacheTags')->willReturn(["hive_inspection:$id"]);
    $inspection->method('getCacheContexts')->willReturn([]);
    $inspection->method('getCacheMaxAge')->willReturn(-1);

    $hive_ref = new \stdClass();
    $hive_ref->entity = $hive;
    $inspection->method('get')->with('hive')->willReturn($hive_ref);

    return $inspection;
  }

  /**
   * Creates a mock Queen entity referencing the given hive.
   */
  private function createQueenMock(int $id, string $label, ContentEntityInterface $hive): ContentEntityInterface {
    $queen = $this->createMock(ContentEntityInterface::class);
    $queen->method('id')->willReturn($id);
    $queen->method('label')->willReturn($label);
    $queen->method('getCacheTags')->willReturn(["queen:$id"]);
    $queen->method('getCacheContexts')->willReturn([]);
    $queen->method('getCacheMaxAge')->willReturn(-1);

    $hive_ref = new \stdClass();
    $hive_ref->entity = $hive;
    $queen->method('get')->with('hive')->willReturn($hive_ref);

    return $queen;
  }

  /**
   * Creates a mock Queen entity with no hive reference (unassigned/archived).
   */
  private function createUnassignedQueenMock(int $id, string $label): ContentEntityInterface {
    $queen = $this->createMock(ContentEntityInterface::class);
    $queen->method('id')->willReturn($id);
    $queen->method('label')->willReturn($label);
    $queen->method('getCacheTags')->willReturn(["queen:$id"]);
    $queen->method('getCacheContexts')->willReturn([]);
    $queen->method('getCacheMaxAge')->willReturn(-1);

    $hive_ref = new \stdClass();
    $hive_ref->entity = NULL;
    $queen->method('get')->with('hive')->willReturn($hive_ref);

    return $queen;
  }

  /**
   * Creates a mock QueenObservation entity referencing the given queen.
   */
  private function createObservationMock(int $id, string $label, ContentEntityInterface $queen): ContentEntityInterface {
    $observation = $this->createMock(ContentEntityInterface::class);
    $observation->method('id')->willReturn($id);
    $observation->method('label')->willReturn($label);
    $observation->method('getCacheTags')->willReturn(["queen_observation:$id"]);
    $observation->method('getCacheContexts')->willReturn([]);
    $observation->method('getCacheMaxAge')->willReturn(-1);

    $queen_ref = new \stdClass();
    $queen_ref->entity = $queen;
    $observation->method('get')->with('queen')->willReturn($queen_ref);

    return $observation;
  }

}
