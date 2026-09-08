---
type: task
tags: [hivelog/task]
status: review
priority: low
project:
area: ui
created: 2026-09-08
branch: feature/0069-remove-previous-queens-block
release:
depends-on:
blocked-by:
---
# Task: Remove the "Previous Queens" block from the hive page

## Context
The hive canonical page (`/hivelog/hive/{hive}`) rendered a "Previous
Queens" table below the current-queen section, listing every retired
queen the hive has had. That information is already one click away via
the "View all Queens" link (and each retired queen's own page), so the
block is redundant.

## Acceptance criteria
- [x] `HiveController::buildQueenSection()` no longer appends the history
      section; `buildQueenHistorySection()` deleted. The "View all
      Queens" link stays.
- [x] `.hivelog-queen-history` / `.hivelog-queen-history h4` rules removed
      from `css/hivelog.tables.css` (now dead).
- [x] `Hive::getQueens()` is unchanged and still used — for the
      observation aggregation on the hive page and the observation
      filter's queen options; its docblock updated to drop the "show
      past queens" purpose.
- [x] `HiveTest::testHiveViewQueenObservationsSurviveQueenReplacement`
      drops the "Previous Queens" assertion (adds a
      `assertStringNotContainsString`) and keeps the `Q-first` check —
      a retired queen still appears as the Queen link on its observation
      row.
- [x] `AGENTS.md` updated. phpcs clean; `HiveTest` (20) green.

## Implementation notes
- Key files: `src/Controller/HiveController.php`, `src/Entity/Hive.php`,
  `css/hivelog.tables.css`, `tests/src/Kernel/HiveTest.php`, `AGENTS.md`.
- Verified on the ddev site with a two-queen hive: no "Previous Queens"
  heading, no `hivelog-queen-history` markup; the retired queen still
  surfaces via its observation row.

## Related
- Decisions:: [[0026-breed-moves-to-queen]]
- Commits::
