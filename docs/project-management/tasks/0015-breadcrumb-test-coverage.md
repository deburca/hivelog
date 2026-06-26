---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[breadcrumb-consistency]]"
area: tests
created: 2026-06-17
branch: feature/0015-breadcrumb-test-coverage
release: 1.4.0
depends-on: ["[[0014-implement-breadcrumb-consistency-fixes]]"]
---
# Task: Breadcrumb test coverage

## Context
Today the only breadcrumb test is the mocked unit test
`tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` (per `AGENTS.md`).
To keep breadcrumbs consistent as routes evolve, add coverage that exercises
the real ancestry threading for each entity type and the edge cases found in
[[0013-breadcrumb-route-audit]]. Closing task for [[breadcrumb-consistency]].

## Acceptance criteria
- [x] Tests assert the expected trail for each entity type's canonical, add,
      edit, and delete routes (apiary, hive, inspection, queen, observation).
- [x] Edge cases covered: unassigned queen (no hive) and an observation whose
      queen is unassigned — ancestry gracefully shortens to Home › HiveLog (queen)
      and Home › HiveLog › Queen (observation).
- [x] `applies()` covered for both matching and non-matching route names,
      including the pre-emptive CSV export exclusion.
- [x] Tests tagged `#[Group('hivelog')]` — `--group hivelog` runs them.
- [x] Full suite green: 178/178 tests, 2296 assertions.

## Tests added (all in `HivelogBreadcrumbBuilderTest`)

### applies() additions
- `testAppliesReturnsFalseForCsvExportRoute` — asserts `hivelog.queen.observations_csv`
  returns FALSE, documenting and enforcing the Task 0001 pre-emptive exclusion.

### build() additions
- `testBuildApiaryDeleteForm` — apiary delete trail
- `testBuildHiveDeleteForm` — hive delete trail
- `testBuildInspectionDeleteForm` — inspection delete trail
- `testBuildQueenDeleteForm` — queen delete trail
- `testBuildQueenObservationDeleteForm` — observation delete trail
- `testBuildQueenCanonicalUnassigned` — queen with no hive → Home › HiveLog only
- `testBuildQueenEditFormUnassigned` — unassigned queen edit → Home › HiveLog › Queen
- `testBuildObservationCanonicalQueenUnassigned` — observation on unassigned queen → Home › HiveLog › Queen
- `testBuildObservationEditFormQueenUnassigned` — observation edit on unassigned queen → Home › HiveLog › Queen › Observation

### Helper added
- `createUnassignedQueenMock()` — queen mock whose `hive` reference returns `entity = NULL`.

## Related
- Project:: [[breadcrumb-consistency]]
- Depends on:: [[0014-implement-breadcrumb-consistency-fixes]]
- PRs:: #95
