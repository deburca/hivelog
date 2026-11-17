<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests every collection route has a matching link template (task 0136).
 *
 * Discovered generically from the route provider and entity type
 * manager, not a hand-maintained list of type IDs — five core types
 * (Hive, HiveInspection, CalendarAction, HiveActionLog,
 * ApiaryActionLog) had an `entity.<type>.collection` route but no
 * `collection` link template until this task, so `$entity->toUrl('collection')`
 * and `$entity_type->getLinkTemplate('collection')` both failed for
 * them silently until something actually called one. A future entity
 * type with the same gap now fails this test immediately instead.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CollectionLinkTemplateTest extends KernelTestBase {

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
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests every hivelog-provided entity type with a collection route.
   *
   * Scoped to `hivelog` core's own entity types — a submodule installs
   * its own entity types and is responsible for auditing its own (all
   * three of `nanoprobe`/`collective`/`nexus`'s already have the link
   * template; nothing to fix there).
   */
  public function testCollectionRouteHasLinkTemplate(): void {
    $all_routes = \Drupal::service('router.route_provider')->getAllRoutes();
    $entity_type_manager = \Drupal::entityTypeManager();

    $checked = 0;
    foreach ($entity_type_manager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->getProvider() !== 'hivelog') {
        continue;
      }
      $route_name = "entity.$entity_type_id.collection";
      if (!isset($all_routes[$route_name])) {
        continue;
      }
      $checked++;
      $this->assertTrue(
        $entity_type->hasLinkTemplate('collection'),
        "Entity type '$entity_type_id' has route '$route_name' but no 'collection' link template."
      );
    }

    // Guards against the test silently checking nothing if entity type
    // discovery or the route provider ever broke — hivelog core alone
    // has 11 entity types with a collection route today (apiary, hive,
    // hive_inspection, queen, queen_observation, calendar_action,
    // hive_action_log, apiary_action_log, inventory_item,
    // inventory_purchase, product).
    $this->assertGreaterThanOrEqual(
      11,
      $checked,
      'Expected at least the 11 core hivelog entity types with a collection route to be checked.'
    );
  }

}
