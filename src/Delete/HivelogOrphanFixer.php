<?php

declare(strict_types=1);

namespace Drupal\hivelog\Delete;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies each `HivelogDeleteDependencyRegistry` row's own policy to its existing orphans (task 0145).
 *
 * BLOCK / CASCADE rows: the orphaned children are deleted outright
 * through the entity API, so any CASCADE/DETACH rows *they* have as a
 * parent run automatically via the existing `hivelog_entity_predelete()`
 * dispatch — no separate recursion logic needed here for that half.
 * DETACH rows: the dangling reference is cleared via
 * `HivelogDeleteDependencyExecutor::detachChildren()` — the exact
 * per-child logic (including `applyDetachSideEffects()`, e.g. a
 * detached queen going `inactive`) a live delete's own DETACH path
 * already uses. WARN rows are found and reported but never touched —
 * that dangling reference is the site owner's own deliberate choice
 * (see [[0144-delete-warn-historical-references]]).
 *
 * Fixing one row can turn a previously-valid reference into a *new*
 * orphan elsewhere — deleting a hive whose own apiary is already gone
 * leaves that hive's BLOCK-treated inspections newly orphaned, in
 * turn — so `fix()` re-runs `HivelogOrphanFinder::findOrphans()` after
 * every pass until a pass finds nothing left to do, rather than
 * hand-coding the registry's parent/child ordering.
 */
final class HivelogOrphanFixer {

  /**
   * Safety cap on find/fix passes, so a logic bug can't loop forever.
   *
   * The registry's own dependency chains are only a few levels deep
   * (Apiary → Hive → HiveInspection is the longest), so real orphan
   * cleanup stabilises in 2-3 passes; this is a generous margin.
   */
  protected const MAX_PASSES = 20;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly HivelogOrphanFinder $orphanFinder,
    protected readonly HivelogDeleteDependencyExecutor $executor,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Finds and fixes every non-WARN orphan, repeating until none remain.
   *
   * @param bool $dry_run
   *   TRUE to report what would happen without changing anything — a
   *   single pass, since nothing changes between passes to re-check.
   *
   * @return array{fixed: array[], warned: array[], passes: int}
   *   `fixed`: one entry per row actually acted on (or that would be,
   *   under `$dry_run`), across every pass — each
   *   `{adr_row, parent, child, treatment, count}`. `warned`: WARN rows
   *   that had orphans but were left untouched, deduplicated by
   *   `adr_row` across passes (a WARN row's own orphan count can't
   *   shrink between passes, since it's never acted on). `passes`: how
   *   many find/fix cycles ran before a pass found nothing left to fix
   *   (capped at `MAX_PASSES`).
   */
  public function fix(bool $dry_run = FALSE): array {
    $fixed = [];
    $warned = [];
    $pass = 0;

    do {
      $pass++;
      $rows = $this->orphanFinder->findOrphans();
      if (!$rows) {
        break;
      }

      $acted = FALSE;
      foreach ($rows as $row) {
        if ($row['treatment'] === HivelogDeleteDependencyRegistry::WARN) {
          $warned[$row['adr_row']] = $this->summarise($row);
          continue;
        }
        $acted = TRUE;
        if (!$dry_run) {
          $this->fixRow($row);
        }
        $fixed[] = $this->summarise($row);
        $this->logger->notice(
          '%verb %count %child row(s) whose %field referenced a missing %parent (adr @adr_row).',
          [
            '%verb' => $dry_run
              ? ($row['treatment'] === HivelogDeleteDependencyRegistry::DETACH ? 'Would detach' : 'Would delete')
              : ($row['treatment'] === HivelogDeleteDependencyRegistry::DETACH ? 'Detached' : 'Deleted'),
            '%count' => count($row['orphan_ids']),
            '%child' => $row['child'],
            '%field' => $row['field'],
            '%parent' => $row['parent'],
            '@adr_row' => $row['adr_row'],
          ],
        );
      }

      // A dry run changes nothing, so a second pass would just find
      // the exact same orphans again; likewise if every found row this
      // pass was WARN-only, nothing was fixed and nothing will change.
      if ($dry_run || !$acted) {
        break;
      }
    } while ($pass < self::MAX_PASSES);

    return ['fixed' => $fixed, 'warned' => array_values($warned), 'passes' => $pass];
  }

  /**
   * Applies one non-WARN row's policy to its `orphan_ids`.
   */
  protected function fixRow(array $row): void {
    if ($row['treatment'] === HivelogDeleteDependencyRegistry::DETACH) {
      $this->executor->detachChildren($row['orphan_ids'], $row);
      return;
    }
    // BLOCK / CASCADE: delete outright. Deleting re-invokes
    // hook_entity_predelete() per entity, so any of ITS OWN CASCADE/
    // DETACH rows still run exactly as a live delete's would.
    $storage = $this->entityTypeManager->getStorage($row['child']);
    $storage->delete($storage->loadMultiple($row['orphan_ids']));
  }

  /**
   * The reporting shape shared by `fixed` and `warned` entries.
   */
  protected function summarise(array $row): array {
    return [
      'adr_row' => $row['adr_row'],
      'parent' => $row['parent'],
      'child' => $row['child'],
      'treatment' => $row['treatment'],
      'count' => count($row['orphan_ids']),
    ];
  }

}
