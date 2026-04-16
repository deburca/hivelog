<?php

namespace Drupal\Tests\hivelog\Unit\Breadcrumb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hivelog\Breadcrumb\HivelogBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
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
   * @covers ::applies
   * @dataProvider apiaryRouteProvider
   */
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
   * @covers ::applies
   * @dataProvider hiveRouteProvider
   */
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
   * @covers ::applies
   * @dataProvider hiveInspectionRouteProvider
   */
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
   * @covers ::applies
   * @dataProvider hivelogCustomRouteProvider
   */
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
      'admin'           => ['hivelog.admin'],
    ];
  }

  /**
   * @covers ::applies
   * @dataProvider unrelatedRouteProvider
   */
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
   * @covers ::build
   *
   * Apiary collection: no entity parameters → 3 base links only.
   */
  public function testBuildApiaryCollection(): void {
    $route_match = $this->createRouteMatch('entity.apiary.collection');
    $route_match->method('getParameter')->willReturn(NULL);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('<front>', $links[0]->getUrl()->getRouteName());
    $this->assertEquals('Structure', (string) $links[1]->getText());
    $this->assertEquals('system.admin_structure', $links[1]->getUrl()->getRouteName());
    $this->assertEquals('HiveLog', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.collection', $links[2]->getUrl()->getRouteName());
    $this->assertContains('route', $breadcrumb->getCacheContexts());
  }

  /**
   * @covers ::build
   *
   * Apiary canonical: apiary is the current page so it is NOT appended to the
   * trail, but its cache tags are still added.
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
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * @covers ::build
   *
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

    $this->assertCount(4, $links);
    $this->assertEquals('Mountain Apiary', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 2], $links[3]->getUrl()->getRouteParameters());
    $this->assertContains('apiary:2', $breadcrumb->getCacheTags());
  }

  /**
   * @covers ::build
   *
   * Hive canonical: hive is the current page; apiary ancestor link is added,
   * hive link is NOT.
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
    $this->assertEquals('Home Apiary', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
  }

  /**
   * @covers ::build
   *
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

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[3]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[4]->getText());
    $this->assertEquals('entity.hive.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * @covers ::build
   *
   * Inspection canonical: inspection is the current page; apiary and hive
   * ancestor links are added, inspection link is NOT.
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
    $this->assertEquals('Home Apiary', (string) $links[3]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[4]->getText());
    $this->assertContains('hive_inspection:10', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * @covers ::build
   *
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

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[3]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[4]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[5]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['hive_inspection' => 10], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * @covers ::build
   *
   * hivelog.hive.add carries an {apiary} route parameter; the apiary link
   * should be added as an ancestor (4 links total).
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

    $this->assertCount(4, $links);
    $this->assertEquals('Garden Apiary', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[3]->getUrl()->getRouteName());
  }

  /**
   * @covers ::build
   *
   * hivelog.inspection.add carries a {hive} route parameter; apiary and hive
   * ancestor links should both be added (5 links total).
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

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[3]->getText());
    $this->assertEquals('Hive Beta', (string) $links[4]->getText());
    $this->assertEquals('entity.hive.canonical', $links[4]->getUrl()->getRouteName());
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

}
