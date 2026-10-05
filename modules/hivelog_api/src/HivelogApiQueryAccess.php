<?php

declare(strict_types=1);

namespace Drupal\hivelog_api;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\JsonApiFilter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Restricts the API's collection queries to records the user may view.
 *
 * JSON:API runs an access-checked entity query, then paginates, and only then
 * drops the records the user may not view. HiveLog's entity types have no
 * query-level access (their access is decided per record, by apiary
 * membership), so the first page of a collection is the first 50 records
 * on the site, whoever owns them. For a user whose records come later, a page
 * can be empty while their data sits further on, and the response lists the
 * ids of the records it dropped in `meta.omitted`, which tells a user that
 * other people's records exist, and what they are called.
 *
 * So on a request to the versioned API, an access-checked query for an exposed
 * type is narrowed to the ids the type's own access handler allows the
 * current user to view. The set is computed with the very same `view` check
 * that decides a single record, so the filter can neither widen nor narrow
 * what the user may see: pages come back full and nothing is omitted. It is
 * how the web lists already work, which load and filter in PHP too.
 *
 * Only a query that asked for an access check is touched: the delete-block
 * counts and other internal queries use `accessCheck(FALSE)` and must see
 * every row.
 */
class HivelogApiQueryAccess {

  /**
   * Per request and type: the viewable ids.
   *
   * @var array<string, int[]>
   */
  protected array $cache = [];

  /**
   * Whether the allowed-ids lookup is running (its own query is unfiltered).
   */
  protected bool $computing = FALSE;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Narrows a query if it is an access-checked query for an exposed type.
   */
  public function alter(AlterableInterface $query): void {
    if ($this->computing || !$query instanceof SelectInterface) {
      return;
    }
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !HivelogApiResources::isVersionedRequest($request)) {
      return;
    }
    $type = $this->exposedType($query);
    if ($type === NULL || $this->currentUser->hasPermission('administer hivelog')) {
      return;
    }

    $ids = $this->viewableIds($type, $request);
    $id_key = $this->entityTypeManager->getDefinition($type)->getKey('id');
    if ($ids) {
      $query->condition('base_table.' . $id_key, $ids, 'IN');
    }
    else {
      $query->where('1 = 0');
    }
  }

  /**
   * Says whether a request may filter by an entity type's data (JSON:API).
   *
   * For a request with a `filter` parameter JSON:API adds a condition that is
   * always false unless a module vouches that filtering across the type is
   * safe (hook_jsonapi_entity_filter_access()). HiveLog's types have no
   * per-subset rule to name, so without this every filtered request on the
   * versioned API came back empty, and the app could not ask for a hive's
   * queen or inspections. What the hook asks is that the query is narrowed to
   * viewable records by a `<type>_access` alter, which alter() does for
   * exactly these types, so a filter can only ever pick among records the user
   * may already see. It is said only on the versioned API: a request to plain
   * `/jsonapi` keeps JSON:API's own default.
   *
   * @return \Drupal\Core\Access\AccessResultInterface[]
   *   The access result for the "among all" subset.
   */
  public function filterAccess(EntityTypeInterface $entity_type): array {
    $request = $this->requestStack->getCurrentRequest();
    $narrowed = $request
      && HivelogApiResources::isVersionedRequest($request)
      && HivelogApiResources::isNarrowed($entity_type->id());
    $result = $narrowed ? AccessResult::allowed() : AccessResult::neutral();
    // The answer depends on which path the request came in on.
    return [JsonApiFilter::AMONG_ALL => $result->addCacheContexts(['url.path'])];
  }

  /**
   * Gets the exposed entity type an access-checked entity query is for.
   */
  protected function exposedType(SelectInterface $query): ?string {
    foreach (HivelogApiResources::narrowedTypes() as $type) {
      // Core tags a query that asked for an access check with "<type>_access".
      if ($query->hasTag($type . '_access') && $query->hasTag('entity_query_' . $type)) {
        return $type;
      }
    }
    return NULL;
  }

  /**
   * Gets the ids of every record of a type the current user may view.
   *
   * @return int[]
   *   The ids.
   */
  protected function viewableIds(string $type, object $request): array {
    $key = spl_object_id($request) . ':' . $this->currentUser->id() . ':' . $type;
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }

    $this->computing = TRUE;
    try {
      $storage = $this->entityTypeManager->getStorage($type);
      $all = $storage->getQuery()->accessCheck(FALSE)->execute();
      $allowed = [];
      foreach (array_chunk($all, 200) as $chunk) {
        foreach ($storage->loadMultiple($chunk) as $id => $entity) {
          if ($entity->access('view', $this->currentUser)) {
            $allowed[] = $id;
          }
        }
        $storage->resetCache($chunk);
      }
    }
    finally {
      $this->computing = FALSE;
    }
    return $this->cache[$key] = $allowed;
  }

}
