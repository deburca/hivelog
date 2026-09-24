<?php

namespace Drupal\Tests\hivelog\Unit\Breadcrumb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hivelog\Breadcrumb\HivelogBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;
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

    $this->builder = new HivelogBreadcrumbBuilder();
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
      'dashboard'             => ['hivelog.dashboard'],
      'hive add'              => ['hivelog.hive.add'],
      'inspection add'        => ['hivelog.inspection.add'],
      'queen add'             => ['hivelog.queen.add'],
      'observation add'       => ['hivelog.queen_observation.add'],
      'calendar action add'  => ['hivelog.calendar_action.add'],
      'hive action log add'  => ['hivelog.hive_action_log.add'],
      'apiary action log add' => ['hivelog.apiary_action_log.add'],
      'full calendar'        => ['hivelog.apiary.calendar_action.collection'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for calendar_action entity routes.
   */
  #[DataProvider('calendarActionRouteProvider')]
  public function testAppliesReturnsTrueForCalendarActionRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for calendar_action entity routes.
   */
  public static function calendarActionRouteProvider(): array {
    return [
      'collection' => ['entity.calendar_action.collection'],
      'canonical'  => ['entity.calendar_action.canonical'],
      'edit_form'  => ['entity.calendar_action.edit_form'],
      'delete_form' => ['entity.calendar_action.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for hive_action_log entity routes.
   */
  #[DataProvider('hiveActionLogRouteProvider')]
  public function testAppliesReturnsTrueForHiveActionLogRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for hive_action_log entity routes.
   */
  public static function hiveActionLogRouteProvider(): array {
    return [
      'collection' => ['entity.hive_action_log.collection'],
      'canonical'  => ['entity.hive_action_log.canonical'],
      'edit_form'  => ['entity.hive_action_log.edit_form'],
      'delete_form' => ['entity.hive_action_log.delete_form'],
    ];
  }

  /**
   * Tests that applies() returns TRUE for apiary_action_log entity routes.
   */
  #[DataProvider('apiaryActionLogRouteProvider')]
  public function testAppliesReturnsTrueForApiaryActionLogRoutes(string $route_name): void {
    $this->assertTrue($this->builder->applies($this->createRouteMatch($route_name)));
  }

  /**
   * Data provider for apiary_action_log entity routes.
   */
  public static function apiaryActionLogRouteProvider(): array {
    return [
      'collection' => ['entity.apiary_action_log.collection'],
      'canonical'  => ['entity.apiary_action_log.canonical'],
      'edit_form'  => ['entity.apiary_action_log.edit_form'],
      'delete_form' => ['entity.apiary_action_log.delete_form'],
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
   * Dashboard landing page: trail is Home › HiveLog only.
   *
   * "HiveLog" is the terminal crumb (a self-link the theme renders as
   * plain text). ADR-0057.
   */
  public function testBuildDashboard(): void {
    $route_match = $this->createRouteMatch('hivelog.dashboard');
    $route_match->method('getParameter')->willReturn(NULL);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(2, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('<front>', $links[0]->getUrl()->getRouteName());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('hivelog.dashboard', $links[1]->getUrl()->getRouteName());
    $this->assertContains('route', $breadcrumb->getCacheContexts());
  }

  /**
   * Apiary collection: Home › HiveLog › Apiaries.
   *
   * "HiveLog" links to the dashboard (ADR-0057); "Apiaries" is the
   * collection page's own terminal crumb.
   */
  public function testBuildApiaryCollection(): void {
    $route_match = $this->createRouteMatch('entity.apiary.collection');
    $route_match->method('getParameter')->willReturn(NULL);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('<front>', $links[0]->getUrl()->getRouteName());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('hivelog.dashboard', $links[1]->getUrl()->getRouteName());
    $this->assertEquals('Apiaries', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.collection', $links[2]->getUrl()->getRouteName());
    $this->assertContains('route', $breadcrumb->getCacheContexts());
  }

  /**
   * Every list / report page that has no entity chain ends Home › HiveLog › <own name>.
   */
  #[DataProvider('leafPageProvider')]
  public function testBuildLeafPageTerminalCrumb(string $route_name, string $expected_text): void {
    $route_match = $this->createRouteMatch($route_name);
    $route_match->method('getParameter')->willReturn(NULL);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(3, $links);
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('hivelog.dashboard', $links[1]->getUrl()->getRouteName());
    $this->assertEquals($expected_text, (string) $links[2]->getText());
    $this->assertEquals($route_name, $links[2]->getUrl()->getRouteName());
  }

  /**
   * Data provider: leaf list / report routes and their terminal crumb text.
   */
  public static function leafPageProvider(): array {
    return [
      'hives' => ['entity.hive.collection', 'Hives'],
      'inspections' => ['entity.hive_inspection.collection', 'Inspections'],
      'queens' => ['entity.queen.collection', 'Queens'],
      'queen observations' => ['entity.queen_observation.collection', 'Queen Observations'],
      'calendar actions' => ['entity.calendar_action.collection', 'Calendar Actions'],
      'hive action logs' => ['entity.hive_action_log.collection', 'Hive Action Logs'],
      'apiary action logs' => ['entity.apiary_action_log.collection', 'Apiary Action Logs'],
      'inventory items' => ['entity.inventory_item.collection', 'Inventory Items'],
      'inventory purchases' => ['entity.inventory_purchase.collection', 'Inventory Purchases'],
      'products' => ['entity.product.collection', 'Products'],
      'sensor devices' => ['entity.sensor_device.collection', 'Sensor Devices'],
      'ai provider configs' => ['entity.ai_provider_config.collection', 'AI Provider Configs'],
      'api clients' => ['entity.api_client.collection', 'API Clients'],
      'combined financial report' => ['hivelog.apiaries.financial_report', 'Financial Report: All Apiaries'],
    ];
  }

  /**
   * Site-wide add forms hang off their collection: Home › HiveLog › <Plural> › Add <Type>.
   *
   * The terminal crumb (task 0117) is the route's own static title, a
   * self-link the theme renders as plain text.
   */
  #[DataProvider('addFormProvider')]
  public function testBuildAddFormThreadsToCollection(string $route_name, string $collection_route, string $text, string $add_title): void {
    $route_match = $this->createRouteMatch($route_name, NULL, [], $add_title);
    $route_match->method('getParameter')->willReturn(NULL);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals($text, (string) $links[2]->getText());
    $this->assertEquals($collection_route, $links[2]->getUrl()->getRouteName());
    $this->assertEquals($add_title, (string) $links[3]->getText());
    $this->assertEquals($route_name, $links[3]->getUrl()->getRouteName());
  }

  /**
   * Data provider: global add-form routes → their collection crumb + title.
   */
  public static function addFormProvider(): array {
    return [
      'apiary' => [
        'entity.apiary.add_form', 'entity.apiary.collection', 'Apiaries', 'Add Apiary',
      ],
      'inventory item' => [
        'entity.inventory_item.add_form',
        'entity.inventory_item.collection',
        'Inventory Items',
        'Add Inventory Item',
      ],
      'inventory purchase' => [
        'entity.inventory_purchase.add_form',
        'entity.inventory_purchase.collection',
        'Inventory Purchases',
        'Add Inventory Purchase',
      ],
      'product' => [
        'entity.product.add_form', 'entity.product.collection', 'Products', 'Add Product',
      ],
      'sensor device' => [
        'entity.sensor_device.add_form', 'entity.sensor_device.collection', 'Sensor Devices', 'Add Sensor Device',
      ],
      'ai provider config' => [
        'entity.ai_provider_config.add_form', 'entity.ai_provider_config.collection', 'AI Provider Configs', 'Add AI Provider Config',
      ],
      'api client' => [
        'entity.api_client.add_form', 'entity.api_client.collection', 'API Clients', 'Add API Client',
      ],
    ];
  }

  /**
   * Inventory item / purchase / product canonical: Home › HiveLog › <Apiary> › <Entity>.
   */
  #[DataProvider('apiaryScopedEntityProvider')]
  public function testBuildApiaryScopedEntityCanonical(string $entity_type, string $param, string $canonical_route): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $entity = $this->createApiaryScopedMock($entity_type, 12, 'Honey', $apiary);
    $route_match = $this->createRouteMatch($canonical_route);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      [$param, $entity],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Ravnholt Home', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Honey', (string) $links[3]->getText());
    $this->assertEquals($canonical_route, $links[3]->getUrl()->getRouteName());
    $this->assertContains("$entity_type:12", $breadcrumb->getCacheTags());
    $this->assertContains('apiary:7', $breadcrumb->getCacheTags());
  }

  /**
   * Data provider: the three apiary-scoped catalog / ledger entities.
   */
  public static function apiaryScopedEntityProvider(): array {
    return [
      'inventory item' => ['inventory_item', 'inventory_item', 'entity.inventory_item.canonical'],
      'inventory purchase' => ['inventory_purchase', 'inventory_purchase', 'entity.inventory_purchase.canonical'],
      'product' => ['product', 'product', 'entity.product.canonical'],
    ];
  }

  /**
   * A product's Layout Builder override page still threads the full trail.
   *
   * No `_title` default and no `_title_callback` on this mocked route, so
   * `routeTitle()` (task 0117) finds nothing to add — the entity trail is
   * unaffected, exactly like before this task, rather than erroring.
   */
  public function testBuildLayoutBuilderRouteThreadsEntityTrail(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $product = $this->createApiaryScopedMock('product', 12, 'Honey', $apiary);
    $route_match = $this->createRouteMatch('layout_builder.overrides.product.view', '/hivelog/product/12/layout');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['product', $product],
    ]);

    $this->assertTrue($this->builder->applies($route_match));
    $links = $this->builder->build($route_match)->getLinks();
    $this->assertCount(4, $links);
    $this->assertEquals('Honey', (string) $links[3]->getText());
  }

  /**
   * A bolt-on route with a static `_title` gets a terminal crumb from it.
   *
   * Stands in for a Layout Builder override or any other route this
   * module doesn't itself define — `routeTitle()` reads whatever the
   * route declares, the same way it does for this module's own add
   * routes, rather than special-casing Layout Builder by name.
   */
  public function testBuildBoltOnRouteWithStaticTitleGetsTerminalCrumb(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $product = $this->createApiaryScopedMock('product', 12, 'Honey', $apiary);
    $route_match = $this->createRouteMatch(
      'layout_builder.overrides.product.view',
      '/hivelog/product/12/layout',
      ['product' => 12],
      'Layout',
    );
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['product', $product],
    ]);

    $links = $this->builder->build($route_match)->getLinks();
    $this->assertCount(5, $links);
    $this->assertEquals('Honey', (string) $links[3]->getText());
    $this->assertEquals('Layout', (string) $links[4]->getText());
    $this->assertEquals('layout_builder.overrides.product.view', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['product' => 12], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Sensor device canonical, hive-scoped: threads through its own collection.
   *
   * Home › HiveLog › Sensor Devices › <Device> — not the apiary/hive
   * ancestry, even though a hive-scoped device has one (task 0111
   * follow-up: users reach a device from /hivelog/sensor-devices
   * regardless of scope, and an apiary/hive trail was getting collapsed
   * behind the theme's own ellipsis truncation).
   */
  public function testBuildSensorDeviceCanonicalHiveScoped(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $device = $this->createSensorDeviceMock(12, 'VV-01 Scale', $apiary, $hive);
    $route_match = $this->createRouteMatch('entity.sensor_device.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['sensor_device', $device],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Sensor Devices', (string) $links[2]->getText());
    $this->assertEquals('entity.sensor_device.collection', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('VV-01 Scale', (string) $links[3]->getText());
    $this->assertEquals('entity.sensor_device.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('sensor_device:12', $breadcrumb->getCacheTags());
  }

  /**
   * Sensor device canonical, apiary-scoped: same shape as the hive-scoped case.
   */
  public function testBuildSensorDeviceCanonicalApiaryScoped(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $device = $this->createSensorDeviceMock(13, 'VV-Yard Weather', $apiary, NULL);
    $route_match = $this->createRouteMatch('entity.sensor_device.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['sensor_device', $device],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Sensor Devices', (string) $links[2]->getText());
    $this->assertEquals('VV-Yard Weather', (string) $links[3]->getText());
    $this->assertEquals('entity.sensor_device.canonical', $links[3]->getUrl()->getRouteName());
  }

  /**
   * Sensor device readings page (task 0110): named terminal after the device link.
   */
  public function testBuildSensorDeviceReadingsPage(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $device = $this->createSensorDeviceMock(12, 'VV-01 Scale', $apiary, NULL);
    $route_match = $this->createRouteMatch('entity.sensor_device.readings');
    $route_match->method('getParameter')->willReturnMap([
      ['sensor_device', $device],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('VV-01 Scale', (string) $links[3]->getText());
    $this->assertEquals('entity.sensor_device.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Readings', (string) $links[4]->getText());
    $this->assertEquals('entity.sensor_device.readings', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['sensor_device' => 12], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Sensor device config-download page: named terminal after the device link.
   */
  public function testBuildSensorDeviceConfigPage(): void {
    $apiary = $this->createApiaryMock(7, 'Ravnholt Home');
    $device = $this->createSensorDeviceMock(12, 'VV-01 Scale', $apiary, NULL);
    $route_match = $this->createRouteMatch('nanoprobe.sensor_device.config');
    $route_match->method('getParameter')->willReturnMap([
      ['sensor_device', $device],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Download Configuration', (string) $links[4]->getText());
    $this->assertEquals('nanoprobe.sensor_device.config', $links[4]->getUrl()->getRouteName());
  }

  /**
   * AI Provider Config canonical threads through its own collection.
   *
   * No apiary/hive ancestor, so Home › HiveLog › AI Provider Configs ›
   * <label>.
   */
  public function testBuildAiProviderConfigCanonical(): void {
    $config = $this->createFlatEntityMock('ai_provider_config', 3, 'Production Anthropic Key');
    $route_match = $this->createRouteMatch('entity.ai_provider_config.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['ai_provider_config', $config],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('AI Provider Configs', (string) $links[2]->getText());
    $this->assertEquals('entity.ai_provider_config.collection', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Production Anthropic Key', (string) $links[3]->getText());
    $this->assertEquals('entity.ai_provider_config.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('ai_provider_config:3', $breadcrumb->getCacheTags());
  }

  /**
   * API Client canonical threads through its own collection.
   *
   * No apiary/hive ancestor, so Home › HiveLog › API Clients ›
   * <label>.
   */
  public function testBuildApiClientCanonical(): void {
    $client = $this->createFlatEntityMock('api_client', 4, 'Dashboard Tablet');
    $route_match = $this->createRouteMatch('entity.api_client.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['api_client', $client],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('API Clients', (string) $links[2]->getText());
    $this->assertEquals('entity.api_client.collection', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Dashboard Tablet', (string) $links[3]->getText());
    $this->assertEquals('entity.api_client.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('api_client:4', $breadcrumb->getCacheTags());
  }

  /**
   * API Client regenerate-token page: named terminal after the client link.
   */
  public function testBuildApiClientRegenerateTokenPage(): void {
    $client = $this->createFlatEntityMock('api_client', 4, 'Dashboard Tablet');
    $route_match = $this->createRouteMatch('collective.api_client.regenerate_token');
    $route_match->method('getParameter')->willReturnMap([
      ['api_client', $client],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Dashboard Tablet', (string) $links[3]->getText());
    $this->assertEquals('Regenerate Token', (string) $links[4]->getText());
    $this->assertEquals('collective.api_client.regenerate_token', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['api_client' => 4], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Requirement / yield edit: Home › HiveLog › <Apiary> › <Action> › <Sub>.
   */
  #[DataProvider('calendarActionSubEntityProvider')]
  public function testBuildCalendarActionSubEntityEdit(string $entity_type, string $param, string $route_name): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $action = $this->createCalendarActionMock(40, 'Feed winter stores', $apiary);
    $sub = $this->createSubEntityMock($entity_type, 5, 'Syrup × 6 kg', $action);
    $route_match = $this->createRouteMatch($route_name, NULL, [$param => 5]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['calendar_action', NULL],
      [$param, $sub],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Feed winter stores', (string) $links[3]->getText());
    $this->assertEquals('entity.calendar_action.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Syrup × 6 kg', (string) $links[4]->getText());
    $this->assertFalse($links[4]->getUrl()->isRouted() && $links[4]->getUrl()->getRouteName() !== '<nolink>');
    $this->assertEquals('Edit', (string) $links[5]->getText());
    $this->assertEquals($route_name, $links[5]->getUrl()->getRouteName());
    $this->assertEquals([$param => 5], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Data provider: the two calendar-action sub-entities (edit routes).
   */
  public static function calendarActionSubEntityProvider(): array {
    return [
      'requirement' => [
        'calendar_action_item_requirement',
        'calendar_action_item_requirement',
        'entity.calendar_action_item_requirement.edit_form',
      ],
      'yield' => [
        'calendar_action_product_yield',
        'calendar_action_product_yield',
        'entity.calendar_action_product_yield.edit_form',
      ],
    ];
  }

  /**
   * The requirement "add" form (nested under a calendar action) threads it.
   */
  public function testBuildRequirementAddThreadsCalendarAction(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $action = $this->createCalendarActionMock(40, 'Feed winter stores', $apiary);
    $route_match = $this->createRouteMatch(
      'hivelog.calendar_action_item_requirement.add',
      NULL,
      ['calendar_action' => 40],
      'Add Required Item',
    );
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_action_log', NULL],
      ['apiary_action_log', NULL],
      ['calendar_action', $action],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Feed winter stores', (string) $links[3]->getText());
    $this->assertEquals('entity.calendar_action.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Add Required Item', (string) $links[4]->getText());
    $this->assertEquals('hivelog.calendar_action_item_requirement.add', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['calendar_action' => 40], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Per-apiary financial report: Home › HiveLog › <Apiary> › Financial Report.
   */
  public function testBuildApiaryFinancialReport(): void {
    $apiary = $this->createApiaryMock(3, 'Ravnholt Home');
    $route_match = $this->createRouteMatch('hivelog.apiary.inventory_cost_report');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Ravnholt Home', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Financial Report', (string) $links[3]->getText());
    $this->assertEquals('hivelog.apiary.inventory_cost_report', $links[3]->getUrl()->getRouteName());
  }

  /**
   * Full-calendar page: Home › HiveLog › <Apiary> › Calendar.
   */
  public function testBuildFullCalendarTerminalCrumb(): void {
    $apiary = $this->createApiaryMock(4, 'Søndermarken');
    $route_match = $this->createRouteMatch('hivelog.apiary.calendar_action.collection');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Søndermarken', (string) $links[2]->getText());
    $this->assertEquals('Calendar', (string) $links[3]->getText());
    $this->assertEquals('hivelog.apiary.calendar_action.collection', $links[3]->getUrl()->getRouteName());
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
    $route_match = $this->createRouteMatch('entity.apiary.edit_form', NULL, ['apiary' => 2]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Mountain Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 2], $links[2]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary.edit_form', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 2], $links[3]->getUrl()->getRouteParameters());
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
   * Hive Insights page (task 0111): Home › HiveLog › <Apiary> › <Hive> › Insights.
   *
   * Unlike the canonical page (hive label is the terminal crumb),
   * a named sub-page adds a distinct terminal after the hive link —
   * mirrors testBuildApiaryFinancialReport()'s own pattern for
   * `$apiary_page_crumbs`.
   */
  public function testBuildHiveInsightsPage(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.insights');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
    ]);

    $links = $this->builder->build($route_match)->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Insights', (string) $links[4]->getText());
    $this->assertEquals('entity.hive.insights', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Hive edit: apiary and hive ancestor links are both added (5 links total).
   */
  public function testBuildHiveEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.edit_form', NULL, ['hive' => 5]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[3]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[4]->getText());
    $this->assertEquals('entity.hive.edit_form', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[4]->getUrl()->getRouteParameters());
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
   * Inspection edit: apiary, hive, and inspection ancestor links all added.
   */
  public function testBuildInspectionEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $inspection = $this->createInspectionMock(10, 'Inspection on 2024-06-15', $hive);
    $route_match = $this->createRouteMatch('entity.hive_inspection.edit_form', NULL, ['hive_inspection' => 10]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', $inspection],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive_inspection' => 10], $links[4]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[5]->getText());
    $this->assertEquals('entity.hive_inspection.edit_form', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['hive_inspection' => 10], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.hive.add carries an {apiary} param; the apiary link is added.
   */
  public function testBuildHiveAddRoute(): void {
    $apiary = $this->createApiaryMock(3, 'Garden Apiary');
    $route_match = $this->createRouteMatch('hivelog.hive.add', NULL, ['apiary' => 3], 'Add Hive');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Garden Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Add Hive', (string) $links[3]->getText());
    $this->assertEquals('hivelog.hive.add', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 3], $links[3]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.inspection.add: apiary and hive ancestor links are added.
   */
  public function testBuildInspectionAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(7, 'Hive Beta', $apiary);
    $route_match = $this->createRouteMatch('hivelog.inspection.add', NULL, ['hive' => 7], 'Add Inspection');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Beta', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Add Inspection', (string) $links[4]->getText());
    $this->assertEquals('hivelog.inspection.add', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 7], $links[4]->getUrl()->getRouteParameters());
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
   * Queen edit: apiary, hive, and queen ancestor links are all added.
   */
  public function testBuildQueenEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('entity.queen.edit_form', NULL, ['queen' => 20]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 20], $links[4]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[5]->getText());
    $this->assertEquals('entity.queen.edit_form', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 20], $links[5]->getUrl()->getRouteParameters());
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
   * Queen observation edit: apiary, hive, queen, and observation links added.
   */
  public function testBuildQueenObservationEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $observation = $this->createObservationMock(30, 'Observation A', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.edit_form', NULL, ['queen_observation' => 30]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(7, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('Observation A', (string) $links[5]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[5]->getUrl()->getRouteName());
    $this->assertEquals('Edit', (string) $links[6]->getText());
    $this->assertEquals('entity.queen_observation.edit_form', $links[6]->getUrl()->getRouteName());
    $this->assertEquals(['queen_observation' => 30], $links[6]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.queen_observation.add: apiary, hive, and queen links added.
   */
  public function testBuildQueenObservationAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('hivelog.queen_observation.add', NULL, ['queen' => 20], 'Add Queen Observation');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
      ['queen_observation', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals('Add Queen Observation', (string) $links[5]->getText());
    $this->assertEquals('hivelog.queen_observation.add', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 20], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.queen.add carries a {hive} param; apiary and hive are added.
   */
  public function testBuildQueenAddRoute(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(9, 'Hive Gamma', $apiary);
    $route_match = $this->createRouteMatch('hivelog.queen.add', NULL, ['hive' => 9], 'Add Queen');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
      ['queen', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Gamma', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Add Queen', (string) $links[4]->getText());
    $this->assertEquals('hivelog.queen.add', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 9], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Apiary delete: same as edit — apiary ancestor link IS added.
   */
  public function testBuildApiaryDeleteForm(): void {
    $apiary = $this->createApiaryMock(2, 'Mountain Apiary');
    $route_match = $this->createRouteMatch('entity.apiary.delete_form', NULL, ['apiary' => 2]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Mountain Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Delete', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary.delete_form', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 2], $links[3]->getUrl()->getRouteParameters());
  }

  /**
   * Hive delete: apiary and hive ancestor links are both added.
   */
  public function testBuildHiveDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $route_match = $this->createRouteMatch('entity.hive.delete_form', NULL, ['hive' => 5]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Delete', (string) $links[4]->getText());
    $this->assertEquals('entity.hive.delete_form', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Inspection delete: apiary, hive, and inspection ancestor links all added.
   */
  public function testBuildInspectionDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $inspection = $this->createInspectionMock(10, 'Inspection on 2024-06-15', $hive);
    $route_match = $this->createRouteMatch('entity.hive_inspection.delete_form', NULL, ['hive_inspection' => 10]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', $inspection],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Inspection on 2024-06-15', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_inspection.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals('Delete', (string) $links[5]->getText());
    $this->assertEquals('entity.hive_inspection.delete_form', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['hive_inspection' => 10], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Queen delete: apiary, hive, and queen ancestor links all added.
   */
  public function testBuildQueenDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $route_match = $this->createRouteMatch('entity.queen.delete_form', NULL, ['queen' => 20]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('entity.queen.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals('Delete', (string) $links[5]->getText());
    $this->assertEquals('entity.queen.delete_form', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 20], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Queen observation delete: apiary, hive, queen, and observation links added.
   */
  public function testBuildQueenObservationDeleteForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $queen = $this->createQueenMock(20, 'Q-2024-001', $hive);
    $observation = $this->createObservationMock(30, 'Observation A', $queen);
    $route_match = $this->createRouteMatch('entity.queen_observation.delete_form', NULL, ['queen_observation' => 30]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(7, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Q-2024-001', (string) $links[4]->getText());
    $this->assertEquals('Observation A', (string) $links[5]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[5]->getUrl()->getRouteName());
    $this->assertEquals('Delete', (string) $links[6]->getText());
    $this->assertEquals('entity.queen_observation.delete_form', $links[6]->getUrl()->getRouteName());
    $this->assertEquals(['queen_observation' => 30], $links[6]->getUrl()->getRouteParameters());
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
    $route_match = $this->createRouteMatch('entity.queen.edit_form', NULL, ['queen' => 21]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', $queen],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen + Edit (no apiary or hive).
    $this->assertCount(4, $links);
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('entity.queen.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Edit', (string) $links[3]->getText());
    $this->assertEquals('entity.queen.edit_form', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['queen' => 21], $links[3]->getUrl()->getRouteParameters());
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
    $route_match = $this->createRouteMatch('entity.queen_observation.edit_form', NULL, ['queen_observation' => 31]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['queen', NULL],
      ['queen_observation', $observation],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Queen + Observation + Edit.
    $this->assertCount(5, $links);
    $this->assertEquals('Q-2023-archived', (string) $links[2]->getText());
    $this->assertEquals('Observation B', (string) $links[3]->getText());
    $this->assertEquals('entity.queen_observation.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Edit', (string) $links[4]->getText());
    $this->assertEquals('entity.queen_observation.edit_form', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['queen_observation' => 31], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Calendar action canonical: apiary is a navigable ancestor; calendar action label is the terminal crumb.
   */
  public function testBuildCalendarActionCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $calendarAction = $this->createCalendarActionMock(40, 'Harvest Spring Honey', $apiary);
    $route_match = $this->createRouteMatch('entity.calendar_action.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', $calendarAction],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Apiary + CalendarAction (no Hive — a calendar
    // action belongs to the apiary directly, not to any one hive).
    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Harvest Spring Honey', (string) $links[3]->getText());
    $this->assertEquals('entity.calendar_action.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('calendar_action:40', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Calendar action edit: apiary and calendar action ancestor links are both added.
   */
  public function testBuildCalendarActionEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $calendarAction = $this->createCalendarActionMock(40, 'Harvest Spring Honey', $apiary);
    $route_match = $this->createRouteMatch('entity.calendar_action.edit_form', NULL, ['calendar_action' => 40]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', $calendarAction],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Harvest Spring Honey', (string) $links[3]->getText());
    $this->assertEquals('entity.calendar_action.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['calendar_action' => 40], $links[3]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[4]->getText());
    $this->assertEquals('entity.calendar_action.edit_form', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['calendar_action' => 40], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.calendar_action.add carries an {apiary} param; the apiary link is added.
   */
  public function testBuildCalendarActionAddRoute(): void {
    $apiary = $this->createApiaryMock(3, 'Garden Apiary');
    $route_match = $this->createRouteMatch('hivelog.calendar_action.add', NULL, ['apiary' => 3], 'Add Calendar Action');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Garden Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Add Calendar Action', (string) $links[3]->getText());
    $this->assertEquals('hivelog.calendar_action.add', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 3], $links[3]->getUrl()->getRouteParameters());
  }

  /**
   * Hive action log canonical: apiary and hive are navigable ancestors; log label is the terminal crumb.
   */
  public function testBuildHiveActionLogCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $log = $this->createHiveActionLogMock(50, 'Varroa Treatment for Hive Alpha (2026)', $hive);
    $route_match = $this->createRouteMatch('entity.hive_action_log.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', NULL],
      ['hive_action_log', $log],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Varroa Treatment for Hive Alpha (2026)', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_action_log.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertContains('hive_action_log:50', $breadcrumb->getCacheTags());
    $this->assertContains('hive:5', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Hive action log edit: apiary, hive, and log ancestor links all added.
   */
  public function testBuildHiveActionLogEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $log = $this->createHiveActionLogMock(50, 'Varroa Treatment for Hive Alpha (2026)', $hive);
    $route_match = $this->createRouteMatch('entity.hive_action_log.edit_form', NULL, ['hive_action_log' => 50]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', NULL],
      ['hive_action_log', $log],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(6, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('Varroa Treatment for Hive Alpha (2026)', (string) $links[4]->getText());
    $this->assertEquals('entity.hive_action_log.canonical', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive_action_log' => 50], $links[4]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[5]->getText());
    $this->assertEquals('entity.hive_action_log.edit_form', $links[5]->getUrl()->getRouteName());
    $this->assertEquals(['hive_action_log' => 50], $links[5]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.hive_action_log.add carries a dual {hive}/{calendar_action} route parameter combination.
   *
   * This is the module's first route with TWO entity route parameters at
   * once. The calendar_action breadcrumb block must NOT fire here — it is
   * guarded to only apply on the calendar action's own
   * entity.calendar_action.* CRUD routes — otherwise this route would gain
   * a duplicate/incorrect crumb. Only the hive's own ancestry
   * (Apiary → Hive) should appear.
   */
  public function testBuildHiveActionLogAddRouteDoesNotAddCalendarActionCrumb(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $hive = $this->createHiveMock(5, 'Hive Alpha', $apiary);
    $calendarAction = $this->createCalendarActionMock(40, 'Harvest Spring Honey', $apiary);
    $route_match = $this->createRouteMatch(
      'hivelog.hive_action_log.add',
      NULL,
      ['hive' => 5, 'calendar_action' => 40],
      'Add Hive Action Log',
    );
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', $hive],
      ['hive_inspection', NULL],
      ['calendar_action', $calendarAction],
      ['hive_action_log', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Apiary + Hive + "Add Hive Action Log" — no
    // CalendarAction crumb, and no duplicate Apiary crumb from the
    // calendar_action block. The terminal crumb's own raw parameters
    // (task 0117) still carry both {hive} and {calendar_action}, since
    // that's what the route actually needs to reconstruct its URL.
    $this->assertCount(5, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Hive Alpha', (string) $links[3]->getText());
    $this->assertEquals('entity.hive.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals('Add Hive Action Log', (string) $links[4]->getText());
    $this->assertEquals('hivelog.hive_action_log.add', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['hive' => 5, 'calendar_action' => 40], $links[4]->getUrl()->getRouteParameters());
    $this->assertStringNotContainsString('Harvest Spring Honey', implode(' ', array_map(
      fn($link) => (string) $link->getText(),
      $links
    )));
  }

  /**
   * Apiary action log canonical: apiary is a navigable ancestor; log label is the terminal crumb.
   *
   * Simpler than hive action log — apiary_action_log references its apiary
   * directly, no hive level to traverse (task 0027).
   */
  public function testBuildApiaryActionLogCanonical(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $log = $this->createApiaryActionLogMock(60, 'Renew CBR for Home Apiary (2026)', $apiary);
    $route_match = $this->createRouteMatch('entity.apiary_action_log.canonical');
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', NULL],
      ['hive_action_log', NULL],
      ['apiary_action_log', $log],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(4, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Renew CBR for Home Apiary (2026)', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary_action_log.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertContains('apiary_action_log:60', $breadcrumb->getCacheTags());
    $this->assertContains('apiary:1', $breadcrumb->getCacheTags());
  }

  /**
   * Apiary action log edit: apiary and log ancestor links both added.
   */
  public function testBuildApiaryActionLogEditForm(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $log = $this->createApiaryActionLogMock(60, 'Renew CBR for Home Apiary (2026)', $apiary);
    $route_match = $this->createRouteMatch('entity.apiary_action_log.edit_form', NULL, ['apiary_action_log' => 60]);
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', NULL],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', NULL],
      ['hive_action_log', NULL],
      ['apiary_action_log', $log],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    $this->assertCount(5, $links);
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('Renew CBR for Home Apiary (2026)', (string) $links[3]->getText());
    $this->assertEquals('entity.apiary_action_log.canonical', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary_action_log' => 60], $links[3]->getUrl()->getRouteParameters());
    $this->assertEquals('Edit', (string) $links[4]->getText());
    $this->assertEquals('entity.apiary_action_log.edit_form', $links[4]->getUrl()->getRouteName());
    $this->assertEquals(['apiary_action_log' => 60], $links[4]->getUrl()->getRouteParameters());
  }

  /**
   * Route hivelog.apiary_action_log.add carries a dual {apiary}/{calendar_action} route parameter combination.
   *
   * Mirrors testBuildHiveActionLogAddRouteDoesNotAddCalendarActionCrumb —
   * the calendar_action breadcrumb block must NOT fire here either. Since
   * this route has no {hive} parameter, only the apiary crumb (from the
   * top-level apiary block) should appear.
   */
  public function testBuildApiaryActionLogAddRouteDoesNotAddCalendarActionCrumb(): void {
    $apiary = $this->createApiaryMock(1, 'Home Apiary');
    $calendarAction = $this->createCalendarActionMock(40, 'Renew Central Beehive Registration (CBR)', $apiary);
    $route_match = $this->createRouteMatch(
      'hivelog.apiary_action_log.add',
      NULL,
      ['apiary' => 1, 'calendar_action' => 40],
      'Add Apiary Action Log',
    );
    $route_match->method('getParameter')->willReturnMap([
      ['apiary', $apiary],
      ['hive', NULL],
      ['hive_inspection', NULL],
      ['calendar_action', $calendarAction],
      ['hive_action_log', NULL],
      ['apiary_action_log', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);
    $links = $breadcrumb->getLinks();

    // Home + HiveLog + Apiary + "Add Apiary Action Log" — no
    // CalendarAction crumb.
    $this->assertCount(4, $links);
    $this->assertEquals('Home', (string) $links[0]->getText());
    $this->assertEquals('HiveLog', (string) $links[1]->getText());
    $this->assertEquals('Home Apiary', (string) $links[2]->getText());
    $this->assertEquals('entity.apiary.canonical', $links[2]->getUrl()->getRouteName());
    $this->assertEquals('Add Apiary Action Log', (string) $links[3]->getText());
    $this->assertEquals('hivelog.apiary_action_log.add', $links[3]->getUrl()->getRouteName());
    $this->assertEquals(['apiary' => 1, 'calendar_action' => 40], $links[3]->getUrl()->getRouteParameters());
    $this->assertStringNotContainsString('Renew Central Beehive Registration (CBR)', implode(' ', array_map(
      fn($link) => (string) $link->getText(),
      $links
    )));
  }

  // -------------------------------------------------------------------------
  // Helper methods
  // -------------------------------------------------------------------------

  /**
   * Creates a mock RouteMatchInterface for the given route name.
   *
   * @param string $route_name
   *   The route name.
   * @param string|null $path
   *   The route's path, for `applies()`'s path match. Defaults to a
   *   path under `/hivelog` (or outside it, for a handful of known
   *   unrelated routes).
   * @param array $raw_parameters
   *   The route's raw (un-upcast) path parameters, as `getRawParameters()`
   *   would return them — what the new generic terminal crumb
   *   (task 0117) rebuilds its self-link from.
   * @param string|null $title
   *   The route's static `_title` default, matching what routing.yml
   *   declares for it — read directly by `routeTitle()` for add routes.
   */
  private function createRouteMatch(string $route_name, ?string $path = NULL, array $raw_parameters = [], ?string $title = NULL): RouteMatchInterface {
    if ($path === NULL) {
      // applies() now matches on the route's path; give unrelated routes a
      // path outside /hivelog and everything else a path inside it.
      $unrelated = [
        '<front>' => '/',
        'entity.node.canonical' => '/node/1',
        'user.login' => '/user/login',
        'system.admin_structure' => '/admin/structure',
        'user.register' => '/user/register',
      ];
      $path = $unrelated[$route_name] ?? '/hivelog/_test';
    }
    $defaults = $title !== NULL ? ['_title' => $title] : [];
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route_name);
    $route_match->method('getRouteObject')->willReturn(new Route($path, $defaults));
    $route_match->method('getRawParameters')->willReturn(new ParameterBag($raw_parameters));
    return $route_match;
  }

  /**
   * Creates a mock Apiary entity.
   */
  private function createApiaryMock(int $id, string $label): ContentEntityInterface {
    $apiary = $this->createMock(ContentEntityInterface::class);
    $apiary->method('id')->willReturn($id);
    $apiary->method('label')->willReturn($label);
    $apiary->method('getEntityTypeId')->willReturn('apiary');
    $apiary->method('hasLinkTemplate')->willReturn(TRUE);
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
    $hive->method('getEntityTypeId')->willReturn('hive');
    $hive->method('hasLinkTemplate')->willReturn(TRUE);
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
    $inspection->method('getEntityTypeId')->willReturn('hive_inspection');
    $inspection->method('hasLinkTemplate')->willReturn(TRUE);
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
    $queen->method('getEntityTypeId')->willReturn('queen');
    $queen->method('hasLinkTemplate')->willReturn(TRUE);
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
    $queen->method('getEntityTypeId')->willReturn('queen');
    $queen->method('hasLinkTemplate')->willReturn(TRUE);
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
    $observation->method('getEntityTypeId')->willReturn('queen_observation');
    $observation->method('hasLinkTemplate')->willReturn(TRUE);
    $observation->method('getCacheTags')->willReturn(["queen_observation:$id"]);
    $observation->method('getCacheContexts')->willReturn([]);
    $observation->method('getCacheMaxAge')->willReturn(-1);

    $queen_ref = new \stdClass();
    $queen_ref->entity = $queen;
    $observation->method('get')->with('queen')->willReturn($queen_ref);

    return $observation;
  }

  /**
   * Creates a mock CalendarAction entity referencing the given apiary.
   */
  private function createCalendarActionMock(int $id, string $label, ContentEntityInterface $apiary): ContentEntityInterface {
    $calendar_action = $this->createMock(ContentEntityInterface::class);
    $calendar_action->method('id')->willReturn($id);
    $calendar_action->method('label')->willReturn($label);
    $calendar_action->method('getEntityTypeId')->willReturn('calendar_action');
    $calendar_action->method('hasLinkTemplate')->willReturn(TRUE);
    $calendar_action->method('getCacheTags')->willReturn(["calendar_action:$id"]);
    $calendar_action->method('getCacheContexts')->willReturn([]);
    $calendar_action->method('getCacheMaxAge')->willReturn(-1);

    $apiary_ref = new \stdClass();
    $apiary_ref->entity = $apiary;
    $calendar_action->method('get')->with('apiary')->willReturn($apiary_ref);

    return $calendar_action;
  }

  /**
   * Creates a mock HiveActionLog entity referencing the given hive.
   */
  private function createHiveActionLogMock(int $id, string $label, ContentEntityInterface $hive): ContentEntityInterface {
    $log = $this->createMock(ContentEntityInterface::class);
    $log->method('id')->willReturn($id);
    $log->method('label')->willReturn($label);
    $log->method('getEntityTypeId')->willReturn('hive_action_log');
    $log->method('hasLinkTemplate')->willReturn(TRUE);
    $log->method('getCacheTags')->willReturn(["hive_action_log:$id"]);
    $log->method('getCacheContexts')->willReturn([]);
    $log->method('getCacheMaxAge')->willReturn(-1);

    $hive_ref = new \stdClass();
    $hive_ref->entity = $hive;
    $log->method('get')->with('hive')->willReturn($hive_ref);

    return $log;
  }

  /**
   * Creates a mock ApiaryActionLog entity referencing the given apiary.
   */
  private function createApiaryActionLogMock(int $id, string $label, ContentEntityInterface $apiary): ContentEntityInterface {
    $log = $this->createMock(ContentEntityInterface::class);
    $log->method('id')->willReturn($id);
    $log->method('label')->willReturn($label);
    $log->method('getEntityTypeId')->willReturn('apiary_action_log');
    $log->method('hasLinkTemplate')->willReturn(TRUE);
    $log->method('getCacheTags')->willReturn(["apiary_action_log:$id"]);
    $log->method('getCacheContexts')->willReturn([]);
    $log->method('getCacheMaxAge')->willReturn(-1);

    $apiary_ref = new \stdClass();
    $apiary_ref->entity = $apiary;
    $log->method('get')->with('apiary')->willReturn($apiary_ref);

    return $log;
  }

  /**
   * Creates a mock apiary-scoped entity (inventory item / purchase / product).
   */
  private function createApiaryScopedMock(string $entity_type_id, int $id, string $label, ContentEntityInterface $apiary): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('label')->willReturn($label);
    $entity->method('getEntityTypeId')->willReturn($entity_type_id);
    $entity->method('hasLinkTemplate')->willReturn(TRUE);
    $entity->method('getCacheTags')->willReturn(["$entity_type_id:$id"]);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);

    $apiary_ref = new \stdClass();
    $apiary_ref->entity = $apiary;
    $entity->method('get')->with('apiary')->willReturn($apiary_ref);

    return $entity;
  }

  /**
   * Creates a mock SensorDevice entity.
   *
   * Mirrors the real entity's own invariant (SensorDevice::preSave()):
   * `apiary` is always set; `hive` is only set when the device is
   * hive-scoped — pass NULL for $hive to mock an apiary-scoped device.
   */
  private function createSensorDeviceMock(int $id, string $label, ContentEntityInterface $apiary, ?ContentEntityInterface $hive): ContentEntityInterface {
    $device = $this->createMock(ContentEntityInterface::class);
    $device->method('id')->willReturn($id);
    $device->method('label')->willReturn($label);
    $device->method('getEntityTypeId')->willReturn('sensor_device');
    $device->method('hasLinkTemplate')->willReturn(TRUE);
    $device->method('getCacheTags')->willReturn(["sensor_device:$id"]);
    $device->method('getCacheContexts')->willReturn([]);
    $device->method('getCacheMaxAge')->willReturn(-1);

    $apiary_ref = new \stdClass();
    $apiary_ref->entity = $apiary;
    $hive_ref = new \stdClass();
    $hive_ref->entity = $hive;
    $device->method('get')->willReturnMap([
      ['apiary', $apiary_ref],
      ['hive', $hive_ref],
    ]);

    return $device;
  }

  /**
   * Creates a mock top-level entity with no apiary/hive ancestor.
   *
   * Covers AiProviderConfig / ApiClient — global config/credential
   * entities.
   */
  private function createFlatEntityMock(string $entity_type_id, int $id, string $label): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('label')->willReturn($label);
    $entity->method('getEntityTypeId')->willReturn($entity_type_id);
    $entity->method('hasLinkTemplate')->willReturn(TRUE);
    $entity->method('getCacheTags')->willReturn(["$entity_type_id:$id"]);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    return $entity;
  }

  /**
   * Creates a mock calendar-action sub-entity (requirement / yield).
   *
   * No canonical page of its own — `hasLinkTemplate()` returns FALSE, so
   * the builder falls back to `<nolink>` (today's requirement/yield
   * behaviour).
   */
  private function createSubEntityMock(string $entity_type_id, int $id, string $label, ContentEntityInterface $calendar_action): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn($id);
    $entity->method('label')->willReturn($label);
    $entity->method('getEntityTypeId')->willReturn($entity_type_id);
    $entity->method('hasLinkTemplate')->willReturn(FALSE);
    $entity->method('getCacheTags')->willReturn(["$entity_type_id:$id"]);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);

    $action_ref = new \stdClass();
    $action_ref->entity = $calendar_action;
    $entity->method('get')->with('calendar_action')->willReturn($action_ref);

    return $entity;
  }

}
