<?php

declare(strict_types=1);

namespace Drupal\hivelog\Delete;

use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Finds existing dangling references, per `HivelogDeleteDependencyRegistry` row (task 0145).
 *
 * `HivelogDeleteDependencyCounter`/`HivelogDeleteDependencyExecutor` both
 * work forward from a live parent entity — "what does deleting *this*
 * apiary affect?" This class works the other way: "which children,
 * across the whole site, already reference a parent that's gone?" —
 * the orphans ADR-0103's BLOCK/CASCADE/DETACH access-time enforcement
 * cannot retroactively clean up, and the two WARN rows can still
 * produce even going forward, since a WARN delete is a user's
 * deliberate choice to proceed.
 *
 * Deliberately uses the Entity Query API's `exists()` + `NOT IN` against
 * every currently-valid parent ID, not a raw SQL `LEFT JOIN`, so it
 * never has to know a field's actual storage table/column layout (which
 * varies with cardinality/revisionability) — it only needs the field
 * name the registry already records.
 */
final class HivelogOrphanFinder {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityLastInstalledSchemaRepositoryInterface $installedSchemaRepository,
  ) {}

  /**
   * Every registry row with at least one dangling reference right now.
   *
   * @return array[]
   *   Rows in `HivelogDeleteDependencyRegistry::rows()`'s own shape,
   *   each with an added `orphan_ids` key: the child entity IDs whose
   *   `$row['field']` points at a `$row['parent']` entity that no
   *   longer exists. A row with zero orphans is omitted entirely.
   */
  public function findOrphans(): array {
    $valid_ids_by_parent_type = [];
    $results = [];

    foreach (HivelogDeleteDependencyRegistry::rows() as $row) {
      if (!$this->installedSchemaRepository->getLastInstalledDefinition($row['child'])
        || !$this->installedSchemaRepository->getLastInstalledDefinition($row['parent'])) {
        // Mirrors HivelogDeleteDependencyCounter::countsFor()'s own
        // guard — a kernel test installing only the schemas its own
        // fixtures touch shouldn't need every registry row's parent
        // and child type installed just to check for orphans.
        continue;
      }

      if (!array_key_exists($row['parent'], $valid_ids_by_parent_type)) {
        $valid_ids_by_parent_type[$row['parent']] = $this->entityTypeManager
          ->getStorage($row['parent'])
          ->getQuery()
          ->accessCheck(FALSE)
          ->execute();
      }
      $valid_ids = $valid_ids_by_parent_type[$row['parent']];

      $query = $this->entityTypeManager->getStorage($row['child'])->getQuery()
        ->accessCheck(FALSE)
        ->exists($row['field']);
      // An empty NOT IN list is ambiguous across query backends — when
      // there are zero valid parents at all, every non-empty reference
      // is already an orphan, so the `exists()` condition alone is the
      // whole answer.
      if ($valid_ids) {
        $query->condition($row['field'], array_values($valid_ids), 'NOT IN');
      }
      $orphan_ids = $query->execute();

      if ($orphan_ids) {
        $row['orphan_ids'] = array_values($orphan_ids);
        $results[] = $row;
      }
    }

    return $results;
  }

}
