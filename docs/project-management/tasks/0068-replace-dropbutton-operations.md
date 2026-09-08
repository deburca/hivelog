---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: theme
created: 2026-09-08
branch: feature/0068-replace-dropbutton-operations
release:
depends-on:
blocked-by:
---
# Task: Replace core's dropbutton operations with a button group

## Context
`/hivelog/hives` (and `/hivelog/inspections`, `/hivelog/calendar-actions`,
`/hivelog/hive-action-logs`, `/hivelog/apiary-action-logs`,
`/hivelog/queen-observations`) rendered the row Operations column as
Drupal core's collapsed **dropbutton** widget. Everywhere else in the
module — the five other collection pages (`ApiaryListBuilder` etc.), the
embedded child lists on the canonical pages, the dashboard — actions are
a flat `hivelog:button-group` cluster (ADR-0012).

Audit: of the 11 `EntityListBuilder` subclasses, 5 hand-rolled a
button-group in `buildRow()` and 6 fell through to
`parent::buildRow()` → `#type => 'operations'` → dropbutton. No other
dropbutton usage in `src/` (controllers all use `hivelog:button-group`).

## Acceptance criteria
- [x] New `src/HivelogListBuilder.php` — abstract base extending
      `EntityListBuilder`, with a `buildOperations()` override returning a
      `hivelog:button-group` component (Edit + Delete, Delete = danger,
      `#attached` the `hivelog/buttons` library since core's
      `#type => 'table'` doesn't).
- [x] All 11 list builders extend `HivelogListBuilder` instead of
      `EntityListBuilder`. The 6 "plain" ones need no other change (they
      inherit `buildOperations()` through `parent::buildRow()`). The 5
      SDC-table ones drop their byte-identical inline button block for
      `$row['operations']['data'] = $this->buildOperations($entity);`.
- [x] Tests: `ListCollectionTitleTest` gains
      `testAllListBuildersExtendHivelogBase` (all 11) and
      `testOperationsRenderAsButtonGroupNotDropbutton` (renders hive /
      inspection / queen / queen-observation / calendar-action /
      inventory-item / product lists, asserts `hivelog-button-group`
      present and `dropbutton` absent). phpcs clean.
- [x] `AGENTS.md` documents the base class.

## Implementation notes
- Key files: `src/HivelogListBuilder.php`, the 11 `src/*ListBuilder.php`,
  `tests/src/Kernel/ListCollectionTitleTest.php`, `AGENTS.md`.
- `EntityListBuilder::buildOperations()` is **public** in this core
  version — the override must be public too.
- Verified on the ddev site: `/hivelog/hives`, `/inspections`,
  `/calendar-actions`, `/queen-observations` now render only
  `hivelog-button-group`, zero `dropbutton`.
- No theme change — the buttons already pick up the beeswax palette via
  the `--hivelog-btn-*` token override (task 0061).

## Related
- Decisions:: [[0012-action-button-design-system]], [[0005-sdc-component-library]]
- Follows:: [[0063-strip-list-page-title-prefix]], [[0066-list-heading-action-float]]
- Commits::
