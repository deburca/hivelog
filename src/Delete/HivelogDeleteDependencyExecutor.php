<?php

declare(strict_types=1);

namespace Drupal\hivelog\Delete;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Runs CASCADE and DETACH for an entity's `HivelogDeleteDependencyRegistry` rows.
 *
 * The one place `hivelog_entity_predelete()` (task 0134's dispatch shell)
 * delegates to — whatever the delete path (form, list button, API, drush),
 * every entity delete runs through here first, so a row's treatment holds
 * regardless of how the parent was deleted.
 *
 * BLOCK and WARN rows are never handled here: BLOCK is enforced entirely at
 * access-check time (`HivelogDeleteDependencyCounter::blockingAccessResult()`,
 * task 0141) and structurally cannot reach this class's `cascade()` — it
 * only ever processes rows this class's own caller filters to `CASCADE`, so
 * a BLOCK-registered child is never touched here even if the parent's
 * delete somehow bypassed the access check (a raw `$entity->delete()` from
 * code or drush, which — unlike the form/route — Drupal's entity API does
 * not gate on access by default). WARN rows change nothing about the
 * delete itself; there is nothing to run for them.
 */
class HivelogDeleteDependencyExecutor {

  /**
   * How many child entities to load and delete per query, to bound memory.
   *
   * Matches `SensorReadingRetentionService::PURGE_BATCH_SIZE`'s own
   * reasoning (task 0082) — a real Drupal Batch API run (multi-request,
   * progress bar) is the textbook answer for a delete this large, but
   * chunking a single request's queries this way already keeps a cascade
   * of a few thousand rows (this class's own tested scale) well under any
   * realistic timeout or memory ceiling, without adding batch-API
   * plumbing to `HivelogEntityDeleteForm` — the shared base every one of
   * the module's delete forms uses. Revisit if a real deployment's
   * cascade ever needs to shed tens of thousands of rows in one delete.
   */
  protected const CHUNK_SIZE = 50;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityLastInstalledSchemaRepositoryInterface $installedSchemaRepository,
  ) {}

  /**
   * Runs every registered row for `$entity`'s type against `$entity`.
   */
  public function execute(EntityInterface $entity): void {
    foreach (HivelogDeleteDependencyRegistry::rowsForParent($entity->getEntityTypeId()) as $row) {
      switch ($row['treatment']) {
        case HivelogDeleteDependencyRegistry::CASCADE:
          $this->cascade($entity, $row);
          break;

        case HivelogDeleteDependencyRegistry::DETACH:
          // Populated by task 0143.
          break;
      }
    }
  }

  /**
   * Deletes every one of `$row`'s children referencing `$entity`.
   *
   * In chunks of `CHUNK_SIZE`, via the entity API (`$storage->delete()`,
   * never a raw query) so a cascaded child that itself has CASCADE rows —
   * an Apiary's CalendarAction rows (#2) cascading their own
   * CalendarActionItemRequirement/CalendarActionProductYield rows
   * (#15/#16) — chains automatically: deleting each child re-invokes
   * `hook_entity_predelete()`, which calls back into this same method.
   */
  protected function cascade(EntityInterface $entity, array $row): void {
    // A registered row's child type is always installed on a real site —
    // every hivelog/nanoprobe/nexus entity type ships with the module
    // that registers it. This check exists for kernel tests that install
    // only the schemas their own fixtures touch — see
    // HivelogDeleteDependencyCounter::countsFor()'s identical guard.
    if (!$this->installedSchemaRepository->getLastInstalledDefinition($row['child'])) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage($row['child']);
    while (TRUE) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($row['field'], $entity->id())
        ->range(0, self::CHUNK_SIZE)
        ->execute();
      if (!$ids) {
        break;
      }
      $storage->delete($storage->loadMultiple($ids));
    }
  }

}
