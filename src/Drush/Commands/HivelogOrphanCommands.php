<?php

declare(strict_types=1);

namespace Drupal\hivelog\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\hivelog\Delete\HivelogDeleteDependencyRegistry;
use Drupal\hivelog\Delete\HivelogOrphanFinder;
use Drupal\hivelog\Delete\HivelogOrphanFixer;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reports and repairs existing dangling references (task 0145).
 *
 * ADR-0103's BLOCK/CASCADE/DETACH treatments (task 0134/0141-0143) stop
 * *new* orphans from being created, but a site can already have them
 * from before that enforcement existed, and the two WARN rows (task
 * 0144) can still produce one going forward whenever a site owner
 * deliberately deletes a warned-about item/product. This command is
 * therefore a permanent maintenance tool, not a one-off migration —
 * see [[0145-orphan-report-and-cleanup-command]].
 */
final class HivelogOrphanCommands extends DrushCommands {

  use AutowireTrait;

  public const ORPHANS = 'hivelog:orphans';

  public function __construct(
    #[Autowire(service: 'hivelog.orphan_finder')]
    protected readonly HivelogOrphanFinder $orphanFinder,
    #[Autowire(service: 'hivelog.orphan_fixer')]
    protected readonly HivelogOrphanFixer $orphanFixer,
  ) {
    parent::__construct();
  }

  /**
   * Reports, and optionally fixes, existing dangling references.
   */
  #[CLI\Command(name: self::ORPHANS, aliases: ['hivelog-orphans'])]
  #[CLI\Option(name: 'details', description: "Also list each row's offending entity IDs.")]
  #[CLI\Option(name: 'fix', description: "Apply each row's own policy to its orphans: BLOCK/CASCADE rows delete them (cascading further per their own rows, exactly as a live delete would); DETACH rows clear the dangling reference; WARN rows are reported but never touched.")]
  #[CLI\Option(name: 'dry-run', description: 'With --fix, report what would be done without changing anything.')]
  #[CLI\Usage(name: 'drush hivelog:orphans', description: 'Report every dangling reference, one row per registry entry.')]
  #[CLI\Usage(name: 'drush hivelog:orphans --details', description: 'Also list the offending entity IDs.')]
  #[CLI\Usage(name: 'drush hivelog:orphans --fix --dry-run', description: 'Show exactly what --fix would do, without doing it.')]
  #[CLI\Usage(name: 'drush hivelog:orphans --fix', description: "Apply each row's policy to its existing orphans (asks to confirm first).")]
  #[CLI\FieldLabels(labels: [
    'adr_row' => 'ADR row',
    'parent' => 'Parent',
    'child' => 'Child',
    'treatment' => 'Treatment',
    'count' => 'Orphans',
    'ids' => 'IDs',
  ])]
  #[CLI\DefaultTableFields(fields: ['adr_row', 'parent', 'child', 'treatment', 'count'])]
  public function orphans(array $options = ['details' => FALSE, 'fix' => FALSE, 'dry-run' => FALSE]): RowsOfFields {
    $rows = $this->orphanFinder->findOrphans();
    $table_rows = $this->buildTableRows($rows, (bool) $options['details']);

    if (!$rows) {
      $this->logger()->success(dt('No orphaned references found.'));
      return new RowsOfFields($table_rows);
    }

    if (!$options['fix']) {
      return new RowsOfFields($table_rows);
    }

    $fixable_total = 0;
    $warn_total = 0;
    foreach ($rows as $row) {
      if ($row['treatment'] === HivelogDeleteDependencyRegistry::WARN) {
        $warn_total += count($row['orphan_ids']);
      }
      else {
        $fixable_total += count($row['orphan_ids']);
      }
    }

    if ($fixable_total === 0) {
      $this->logger()->success(dt('Only WARN-row orphans found (@count), which --fix never touches; nothing to do.', ['@count' => $warn_total]));
      return new RowsOfFields($table_rows);
    }

    if (!$options['dry-run'] && !$this->io()->confirm(dt(
      'This will delete or detach @count orphaned record(s) across the rows above. WARN-row orphans (@warn) are never touched. Continue?',
      ['@count' => $fixable_total, '@warn' => $warn_total],
    ))) {
      throw new UserAbortException();
    }

    $result = $this->orphanFixer->fix((bool) $options['dry-run']);
    $this->reportFixResult($result, (bool) $options['dry-run']);

    return new RowsOfFields($table_rows);
  }

  /**
   * The report table's rows, from `HivelogOrphanFinder::findOrphans()`'s.
   */
  protected function buildTableRows(array $rows, bool $details): array {
    $table_rows = [];
    foreach ($rows as $row) {
      $table_row = [
        'adr_row' => $row['adr_row'],
        'parent' => $row['parent'],
        'child' => $row['child'],
        'treatment' => $row['treatment'],
        'count' => count($row['orphan_ids']),
      ];
      if ($details) {
        $table_row['ids'] = implode(', ', $row['orphan_ids']);
      }
      $table_rows[] = $table_row;
    }
    return $table_rows;
  }

  /**
   * Prints `HivelogOrphanFixer::fix()`'s summary to the console.
   *
   * The per-row "deleted/detached N" detail already reached the console
   * via `HivelogOrphanFixer::fix()`'s own `hivelog`-channel logging
   * (Drush mirrors watchdog messages to the console as they're logged),
   * so this only adds the WARN-row summary and the overall outcome —
   * printing the per-row detail again here would just duplicate it.
   */
  protected function reportFixResult(array $result, bool $dry_run): void {
    if ($result['warned']) {
      $warn_count = array_sum(array_column($result['warned'], 'count'));
      $this->logger()->warning(dt('@count WARN-row orphan(s) across @rows row(s) were left untouched, as intended.', [
        '@count' => $warn_count,
        '@rows' => count($result['warned']),
      ]));
    }
    $this->logger()->success($dry_run
      ? dt('Dry run complete (@passes pass(es)).', ['@passes' => $result['passes']])
      : dt('Fix complete (@passes pass(es)).', ['@passes' => $result['passes']]));
  }

}
