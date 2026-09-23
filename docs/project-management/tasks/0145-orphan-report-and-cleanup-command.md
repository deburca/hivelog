---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] `drush hivelog:orphans` reports, per registry row from
      [[0134-delete-dependency-framework]], the children whose reference
      points at a missing target (a `LEFT JOIN … IS NULL` per row). It
      prints a table of counts and, with `--details`, the IDs.
- [ ] `--fix` applies the row's *policy* to existing orphans:
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
- [ ] **No automatic cleanup in an update hook.** The site owner runs
      the command.
- [ ] Run on `cms2`: report first and paste the output into this task's
      Implementation notes (expect ≥ 1,024 / 9), then `--fix` after
      review.
- [ ] Documented in README.md (maintenance section).
- [ ] Kernel / drush test: planted orphans across a BLOCK, a CASCADE and
      a DETACH row are found and fixed per policy. Valid records and
      WARN-row dangling references are untouched.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Orphans fail apiary-membership access, so only admins see them. The
  command runs with admin rights and uses `accessCheck(FALSE)`.
- Key files: new `src/Drush/Commands/HivelogOrphanCommands.php`,
  `drush.services.yml` (or attribute-based command discovery),
  README.md.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0144-delete-warn-historical-references]]
- Commits::
