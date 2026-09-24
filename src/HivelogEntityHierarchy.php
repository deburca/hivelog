<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * The hivelog entity hierarchy.
 *
 * Which reference field names each entity type's parent. Extracted in
 * task 0127 from the parent map task 0116 introduced in
 * `HivelogBreadcrumbBuilder`, so the breadcrumb builder's ancestry trail
 * and `HivelogEntityDeleteForm`'s cancel / post-delete redirect read one
 * definition instead of two that can drift apart. See AGENTS.md
 * "Architecture > Content entities" for the apiary → hive →
 * inspection / queen → observation hierarchy this maps, and
 * "Architecture > Services" for the breadcrumb builder that also
 * consumes it.
 */
final class HivelogEntityHierarchy {

  /**
   * Entity type ID → the reference field naming its parent.
   *
   * Entity types with no entry here (`apiary`, and the three
   * collection-threaded types in `COLLECTION_THREADED_TYPES`) are the
   * root of their own trail.
   */
  public const PARENT_FIELD = [
    'hive' => 'apiary',
    'hive_inspection' => 'hive',
    'queen' => 'hive',
    'queen_observation' => 'queen',
    'calendar_action' => 'apiary',
    'hive_action_log' => 'hive',
    'apiary_action_log' => 'apiary',
    'inventory_item' => 'apiary',
    'inventory_purchase' => 'apiary',
    'product' => 'apiary',
    'calendar_action_item_requirement' => 'calendar_action',
    'calendar_action_product_yield' => 'calendar_action',
  ];

  /**
   * Entity types threaded through their own collection page, not a parent.
   *
   * Global config/credential entities and sensor devices with no
   * apiary/hive ancestor in their trail.
   */
  public const COLLECTION_THREADED_TYPES = [
    'sensor_device',
    'ai_provider_config',
    'api_client',
  ];

  /**
   * The entity `PARENT_FIELD` names as `$entity`'s parent, or NULL.
   *
   * NULL both when the entity type has no parent field (root types,
   * collection-threaded types) and when the reference itself is empty (a
   * deleted apiary, an unassigned queen) — callers treat both cases the
   * same way: stop the walk, or fall back.
   */
  public static function resolveParent(FieldableEntityInterface $entity): ?EntityInterface {
    $parent_field = self::PARENT_FIELD[$entity->getEntityTypeId()] ?? NULL;
    return $parent_field ? $entity->get($parent_field)->entity : NULL;
  }

}
