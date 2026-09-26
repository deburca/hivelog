---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: install
created: 2026-09-23
branch: feature/0145-orphan-report-and-cleanup-command
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Orphan report and cleanup command

## Context
[[0103-delete-policy-for-records-with-children]] stops *new* orphans on
every BLOCK / CASCADE / DETACH row, but existing sites already have
them. On `cms2` (SQL check, 2026-09-23) there are **1,024 calendar
actions** and **9 inventory items** whose apiary no longer exists. The
two WARN rows ([[0144-delete-warn-historical-references]]) can also
still produce dangling references when a user deliberately proceeds.
This command is therefore a permanent maintenance tool, not a one-off
migration.

## Acceptance criteria
- [x] `drush hivelog:orphans` reports, per registry row from
      [[0134-delete-dependency-framework]], the children whose reference
      points at a missing target (a `LEFT JOIN … IS NULL` per row). It
      prints a table of counts and, with `--details`, the IDs.
- [x] `--fix` applies the row's *policy* to existing orphans:
      - BLOCK / CASCADE rows: delete the orphaned children, recursively
        applying their own rows (an orphaned calendar action takes its
        requirements / yields with it; an orphaned hive's inspections
        become orphans and are reported in turn).
      - DETACH rows: clear the dangling reference (and, for queens, set
        inactive).
      - WARN rows: report only, never auto-fix. Those dangling
        references are the user's deliberate choice.
      Asks for confirmation, supports `--dry-run`, and logs what it did
      to the `hivelog` channel.
- [x] **No automatic cleanup in an update hook.** The site owner runs
      the command.
- [x] Run on `cms2`: report first and paste the output into this task's
      Implementation notes (expect ≥ 1,024 / 9), then `--fix` after
      review.
- [x] Documented in README.md (maintenance section).
- [x] Kernel / drush test: planted orphans across a BLOCK, a CASCADE and
      a DETACH row are found and fixed per policy. Valid records and
      WARN-row dangling references are untouched.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Finder uses the Entity Query API, not a raw SQL `LEFT JOIN`.**
`HivelogOrphanFinder::findOrphans()` loads every currently-valid ID for
a row's parent type once (cached per parent type across rows, since
several rows share one, e.g. `apiary`), then for the child type runs
`exists($field)->condition($field, $valid_ids, 'NOT IN')`. This never
needs to know a field's actual storage table/column layout (which
varies with cardinality/revisionability) — only the field name the
registry already records — and handles the "zero valid parents at
all" edge case explicitly (an empty `NOT IN` list is ambiguous across
query backends), tested in
`testZeroValidParentsTreatsEveryReferenceAsOrphan`.

**Fixing reuses the existing delete-dependency machinery rather than
duplicating it.** `HivelogDeleteDependencyExecutor::detach()` was
refactored to extract a new public `detachChildren(array $ids, array $row)`
— the exact per-child logic (including `applyDetachSideEffects()`,
e.g. a detached queen going `inactive`) a live delete's DETACH path
already uses, now callable directly against an orphan batch instead of
only via querying a still-live parent's id. For BLOCK/CASCADE rows,
`HivelogOrphanFixer::fixRow()` just calls `$storage->delete()` on the
orphan batch — deleting re-invokes `hook_entity_predelete()` per
entity, so any CASCADE/DETACH rows *that* entity is itself a parent of
run automatically, with no separate recursion code needed for that
half.

**"Recursively applying their own rows" is a repeat-until-stable
loop, not a hand-coded traversal order.** `HivelogOrphanFixer::fix()`
re-runs `findOrphans()` after every pass (capped at `MAX_PASSES = 20`)
until a pass finds nothing left to do. Fixing one row's orphan can
turn a previously-valid reference into a *new* orphan elsewhere — e.g.
deleting an orphaned Hive (row #1, BLOCK) leaves its own
BLOCK-treated HiveInspections (row #9) newly dangling, since BLOCK
rows are never auto-cascaded by a live delete either. Re-scanning
catches this in a later pass instead of requiring the fixer to know
the registry's parent/child ordering — tested end-to-end in
`testFixRecursesAcrossPassesForNewlyCreatedOrphans`, and this is
exactly what happened for real on `cms2` (see below: it took 2 passes).

**Test fixtures plant a dangling reference directly** (a bogus target
ID, e.g. `'apiary' => 999999`) rather than by deleting a live parent —
deleting a live parent for a BLOCK/CASCADE/DETACH row would either be
refused or already clean the child up via tasks 0141-0143's own
enforcement, so it can't produce a genuine *existing* orphan to test
against. Saving a reference to a nonexistent entity succeeds cleanly
because Drupal only enforces `ValidReferenceConstraint` when
`->validate()` is explicitly called, not on a bare `->save()` — which
is also, in effect, exactly how a real historical orphan like
`cms2`'s own arises (an import, a script, or a delete that predates
this framework).

**Drush command discovery.** `HivelogOrphanCommands` uses Drush 13's
attribute-based auto-discovery (`src/Drush/Commands/`, no
`drush.services.yml` needed) with `AutowireTrait`, but that trait only
resolves a constructor parameter via `$container->has(<its type-hint>)`
— Drupal's container has no FQCN aliases for hand-registered services,
so each dependency needed an explicit
`#[Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'hivelog.orphan_finder')]`
(etc.) rather than relying on the type hint alone.

**Logging.** Added `logger.channel.hivelog` to `hivelog.services.yml`
(mirroring nexus's own `logger.channel.nexus` — `parent:
logger.channel_base`), injected into `HivelogOrphanFixer` rather than
calling `\Drupal::logger('hivelog')` directly, per phpstan's
`globalDrupalDependencyInjection` rule. Drush mirrors watchdog messages
to the console as they're logged, so the per-row "Deleted/Detached N…"
detail reaches the terminal without the command needing to print it
separately — `HivelogOrphanCommands::reportFixResult()` only adds the
WARN-row summary and the overall pass count on top.

**Run on `cms2` (`drupal-cms2.ddev.site`), report before `--fix`:**
```
 --------- ----------------- -------------------- ----------- ---------
  ADR row   Parent            Child                Treatment   Orphans
 --------- ----------------- -------------------- ----------- ---------
  2         apiary            calendar_action      cascade     1024
  3         apiary            apiary_action_log    block       1
  4         apiary            inventory_item        block       9
  5         apiary            inventory_purchase    block       7
  6         apiary            product               block       1
  10        hive              hive_action_log       block       3
  17        calendar_action   hive_action_log       block       3
  22        inventory_item    inventory_purchase    warn        4
  23        inventory_item    inventory_usage       warn        2
 --------- ----------------- -------------------- ----------- ---------
```
Matches (and exceeds) the ADR's own recorded expectation (≥ 1,024 / 9).

**`--fix` (after user confirmation to run it for real):**
```
 [notice] Deleted 1024 calendar_action row(s) whose apiary referenced a missing apiary (adr 2).
 [notice] Deleted 1 apiary_action_log row(s) whose apiary referenced a missing apiary (adr 3).
 [notice] Deleted 9 inventory_item row(s) whose apiary referenced a missing apiary (adr 4).
 [notice] Deleted 7 inventory_purchase row(s) whose apiary referenced a missing apiary (adr 5).
 [notice] Deleted 1 product row(s) whose apiary referenced a missing apiary (adr 6).
 [notice] Deleted 3 hive_action_log row(s) whose hive referenced a missing hive (adr 10).
 [notice] Deleted 3 hive_action_log row(s) whose calendar_action referenced a missing calendar_action (adr 17).
 [warning] 6 WARN-row orphan(s) across 2 row(s) were left untouched, as intended.
 [success] Fix complete (2 pass(es)).
```
A follow-up report immediately after showed **zero** orphaned
references remaining at all — including the 6 WARN-row ones, which is
worth explaining since `--fix` never deletes a WARN row by its own
policy: a single entity can be independently dangling under more than
one registry row at once, and `inventory_purchase`/`inventory_usage`
rows are no exception. Some of the 7 apiary-orphaned purchases (row
#5, BLOCK) also happened to have a dangling `item` (also counted under
row #22, WARN) — deleting them under row #5's own real policy removed
them from row #22's count too, incidentally. The 2 usage rows under
row #23 (WARN) turned out to also reference one of the 3
`hive_action_log` rows deleted under row #10/#17 (BLOCK) — deleting a
`hive_action_log` cascades its own `inventory_usage` children
automatically (row #19a, CASCADE), exactly as a live delete already
would. In both cases the WARN treatment itself deleted nothing; the
underlying record was independently, legitimately removed by a
*different* row's real policy. Confirmed the site (`drupal-cms2.ddev.site`)
still renders correctly afterwards.

**Verification.** phpcs clean; phpstan clean (module-wide, no baseline
changes — the one finding it caught during development,
`\Drupal::logger()` global-DI, was fixed by injecting
`logger.channel.hivelog` instead). New `HivelogOrphanFinderTest`
(7 tests) and `HivelogOrphanFixerTest` (7 tests) — planted orphans
across a BLOCK, CASCADE, DETACH and WARN row each, dry-run, multi-pass
recursion, and valid-data-untouched. Full kernel/unit/functional suite
against `cms2`, core and every submodule: 1,009 tests, 0 failures.

- Orphans fail apiary-membership access, so only admins see them in
  the UI regardless; the command itself runs with `accessCheck(FALSE)`
  since it's an explicit maintenance operation, same as the counter/
  executor it builds on.
- Key files: `src/Delete/HivelogOrphanFinder.php` (new),
  `src/Delete/HivelogOrphanFixer.php` (new),
  `src/Delete/HivelogDeleteDependencyExecutor.php` (`detachChildren()`
  extracted), `src/Drush/Commands/HivelogOrphanCommands.php` (new),
  `hivelog.services.yml` (three new services), `README.md` (new
  Maintenance section), `tests/src/Kernel/HivelogOrphanFinderTest.php`
  (new), `tests/src/Kernel/HivelogOrphanFixerTest.php` (new).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0144-delete-warn-historical-references]]
- Commits::
