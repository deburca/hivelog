<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * The hivelog entity hierarchy.
 *
 * Which reference field names each entity type's parent. Extracted in
 * task 0127 from the parent map task 0116 introduced in
 * `HivelogBreadcrumbBuilder`, so the breadcrumb builder's ancestry trail,
 * `HivelogEntityDeleteForm`'s cancel / post-delete redirect, and (task
 * 0129) `HivelogEntityFormTrait`'s Cancel link on every add / edit form
 * all read one definition instead of three that can drift apart. See
 * AGENTS.md "Architecture > Content entities" for the apiary → hive →
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
   * Entity types with their own `entity.<type>.collection` route.
   *
   * Not derivable from `PARENT_FIELD` + `COLLECTION_THREADED_TYPES` +
   * `apiary`: `calendar_action_item_requirement` /
   * `calendar_action_product_yield` are in `PARENT_FIELD` but have no
   * collection/add UI of their own (edit/delete only), so this is its
   * own explicit list (task 0119) — the single source of truth
   * `HivelogBreadcrumbBuilder` reads each type's own `label_collection`
   * against, replacing what used to be a hand-maintained route → label
   * text map.
   */
  public const COLLECTION_TYPES = [
    'apiary',
    'hive',
    'hive_inspection',
    'queen',
    'queen_observation',
    'calendar_action',
    'hive_action_log',
    'apiary_action_log',
    'inventory_item',
    'inventory_purchase',
    'product',
    'sensor_device',
    'ai_provider_config',
    'api_client',
  ];

  /**
   * Route parameter names to check for a route's "subject" entity.
   *
   * Extracted from `HivelogBreadcrumbBuilder` in task 0120 so the nav
   * strip's active-section resolution and the breadcrumb's ancestry
   * trail read one definition of "what is this page about" instead of
   * two that can disagree. Order does not affect the result for any
   * current route — at most one of these ever upcasts to an object on a
   * given route, except the two action-log "add" routes named in
   * `CALENDAR_ACTION_NOT_SUBJECT_ROUTES`, resolved explicitly first.
   */
  public const SUBJECT_PARAMS = [
    'apiary',
    'hive',
    'hive_inspection',
    'queen',
    'queen_observation',
    'calendar_action',
    'hive_action_log',
    'apiary_action_log',
    'inventory_item',
    'inventory_purchase',
    'product',
    'sensor_device',
    'ai_provider_config',
    'api_client',
    'calendar_action_item_requirement',
    'calendar_action_product_yield',
  ];

  /**
   * Routes where {calendar_action} is present but is not the subject.
   *
   * `hivelog.hive_action_log.add` and `hivelog.apiary_action_log.add`
   * carry `{calendar_action}` only to say which action is being logged;
   * the subject is `{hive}` / `{apiary}` instead.
   */
  public const CALENDAR_ACTION_NOT_SUBJECT_ROUTES = [
    'hivelog.hive_action_log.add',
    'hivelog.apiary_action_log.add',
  ];

  /**
   * The route's "subject" entity — the one the page is fundamentally about.
   *
   * At most one of `SUBJECT_PARAMS` ever upcasts to an object on a given
   * route, except the two routes in `CALENDAR_ACTION_NOT_SUBJECT_ROUTES`,
   * which also carry `{calendar_action}` — excluded there explicitly
   * rather than by checking which other params are present, so the rule
   * stays anchored to specific routes instead of a coincidence of
   * parameter names.
   */
  public static function resolveSubject(RouteMatchInterface $route_match, string $route_name): ?FieldableEntityInterface {
    foreach (self::SUBJECT_PARAMS as $param) {
      if ($param === 'calendar_action' && in_array($route_name, self::CALENDAR_ACTION_NOT_SUBJECT_ROUTES, TRUE)) {
        continue;
      }
      $value = $route_match->getParameter($param);
      if ($value instanceof FieldableEntityInterface) {
        return $value;
      }
    }
    return NULL;
  }

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

  /**
   * The parent's canonical page, else the entity's own collection.
   *
   * Falls through to the dashboard when neither is available. Shared by
   * `HivelogEntityDeleteForm` (the post-delete redirect, and the cancel
   * fallback for a type with no canonical page of its own) and
   * `HivelogEntityFormTrait` (the Cancel link on a new entity, which has
   * no canonical page yet regardless of its type).
   */
  public static function parentOrCollectionUrl(EntityInterface $entity): Url {
    if ($entity instanceof FieldableEntityInterface) {
      $parent = self::resolveParent($entity);
      if ($parent && $parent->hasLinkTemplate('canonical')) {
        return $parent->toUrl('canonical');
      }
    }
    if ($entity->hasLinkTemplate('collection')) {
      // Not `$entity->toUrl('collection')`: core's `EntityBase::toUrl()`
      // refuses ANY `$rel` on an entity with no ID yet, even 'collection'
      // (which needs no ID in its URL) — and this method is called for a
      // brand new, unsaved entity on an add form's Cancel link.
      return Url::fromRoute("entity.{$entity->getEntityTypeId()}.collection");
    }
    return Url::fromRoute('hivelog.dashboard');
  }

}
