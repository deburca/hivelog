<?php

declare(strict_types=1);

namespace Drupal\hivelog\Delete;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Counts an entity's children per `HivelogDeleteDependencyRegistry` row.
 *
 * Task 0134. Memoizes per entity for the life of the request — a delete form
 * typically asks for the same entity's counts more than once (rendering
 * the dependency sections, then again to decide whether to hide the
 * Delete button), and this avoids running the same queries twice.
 *
 * The `deletable` / `not_deletable` breakdown is only computed for
 * BLOCK rows, where it's the whole point (see ADR-0103's "children the
 * current user can't delete" open question) — loading and
 * access-checking every child for a WARN/CASCADE/DETACH row (which can
 * be large: sensor readings, historical purchases) would be wasted work
 * nothing reads.
 */
class HivelogDeleteDependencyCounter {

  use StringTranslationTrait;

  /**
   * Per-entity memoized counts, keyed by `"<type>:<id>"`.
   *
   * @var array[]
   */
  protected array $cache = [];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityLastInstalledSchemaRepositoryInterface $installedSchemaRepository,
  ) {}

  /**
   * The applicable rows for `$entity`, with counts added.
   *
   * Each row gets `total` (and, for BLOCK rows, `deletable` /
   * `not_deletable`) added; rows with a zero count are omitted.
   *
   * @return array[]
   *   The counted rows.
   */
  public function countsFor(EntityInterface $entity): array {
    $cache_key = $entity->getEntityTypeId() . ':' . $entity->id();
    if (isset($this->cache[$cache_key])) {
      return $this->cache[$cache_key];
    }

    $counted = [];
    foreach (HivelogDeleteDependencyRegistry::rowsForParent($entity->getEntityTypeId()) as $row) {
      // A registered row's child type is always installed on a real
      // site — every hivelog/nanoprobe/nexus entity type ships with the
      // module that registers it. This check exists for kernel tests
      // that install only the schemas their own fixtures touch: without
      // it, calling access('delete') on, say, an Apiary would need
      // every one of the registry's ~13 apiary-rooted child schemas
      // installed even in a test that has nothing to do with deleting.
      if (!$this->installedSchemaRepository->getLastInstalledDefinition($row['child'])) {
        continue;
      }
      $storage = $this->entityTypeManager->getStorage($row['child']);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($row['field'], $entity->id())
        ->execute();
      $total = count($ids);
      if ($total === 0) {
        continue;
      }

      $row['total'] = $total;
      if ($row['treatment'] === HivelogDeleteDependencyRegistry::BLOCK) {
        $deletable = 0;
        foreach ($storage->loadMultiple($ids) as $child) {
          if ($child->access('delete')) {
            $deletable++;
          }
        }
        $row['deletable'] = $deletable;
        $row['not_deletable'] = $total - $deletable;
      }
      $counted[] = $row;
    }

    return $this->cache[$cache_key] = $counted;
  }

  /**
   * The access-integration point (task 0141 wires this in, not here).
   *
   * An `AccessResult::forbidden()` when a BLOCK row has children, or
   * NULL — for an access control handler's `delete` branch to call.
   */
  public function blockingAccessResult(EntityInterface $entity): ?AccessResultInterface {
    $block_rows = array_filter(
      $this->countsFor($entity),
      static fn(array $row): bool => $row['treatment'] === HivelogDeleteDependencyRegistry::BLOCK,
    );
    if (!$block_rows) {
      return NULL;
    }

    $result = AccessResult::forbidden((string) $this->blockingReason($block_rows))
      ->addCacheableDependency($entity);
    foreach ($block_rows as $row) {
      $result->addCacheTags($this->entityTypeManager->getDefinition($row['child'])->getListCacheTags());
    }
    return $result;
  }

  /**
   * The human-readable reason a set of BLOCK rows refuses the delete.
   *
   * @param array[] $block_rows
   *   BLOCK rows from `countsFor()`, each with `total` / `deletable` /
   *   `not_deletable` set.
   */
  protected function blockingReason(array $block_rows): TranslatableMarkup {
    $parts = [];
    foreach ($block_rows as $row) {
      $definition = $this->entityTypeManager->getDefinition($row['child']);
      $parts[] = $this->formatPlural(
        $row['total'],
        '1 @label',
        '@count @label_plural',
        ['@label' => $definition->getSingularLabel(), '@label_plural' => $definition->getPluralLabel()],
      );
    }
    $list = implode(', ', array_map(static fn($part) => (string) $part, $parts));

    $not_deletable = array_sum(array_column($block_rows, 'not_deletable'));
    if ($not_deletable > 0) {
      return $this->formatPlural(
        $not_deletable,
        'Cannot be deleted while it still has @list. 1 of those records cannot be deleted by you — ask its owner or a site administrator.',
        'Cannot be deleted while it still has @list. @count of those records cannot be deleted by you — ask their owner or a site administrator.',
        ['@list' => $list],
      );
    }
    return $this->t('Cannot be deleted while it still has @list.', ['@list' => $list]);
  }

}
