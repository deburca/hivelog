---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[breadcrumb-consistency]]"
area: tests
created: 2026-06-17
branch: feature/0015-breadcrumb-test-coverage
release: v1.4.0
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
- [ ] Tests assert the expected trail for each entity type's canonical, add,
      edit, and delete routes (apiary, hive, inspection, queen, observation).
- [ ] Edge cases covered: unassigned queen (no hive) and an observation whose
      queen is unassigned — ancestry should gracefully shorten.
- [ ] `applies()` covered for both matching and non-matching route names
      (including any routes the audit decided to exclude).
- [ ] Tests are tagged `@group hivelog` so `--group hivelog` runs them
      (see `AGENTS.md` for the runner command).
- [ ] Full suite green via the documented PHPUnit command.

## Implementation notes
- Extend the existing unit test for `applies()` / pure-logic cases; add a
  **kernel** test (under `tests/src/Kernel/`) when real entity relationships and
  route upcasting are needed — kernel tests already install the module + deps in
  this codebase.
- Reuse fixture-building patterns from the existing kernel tests
  (apiary→hive→inspection, queen, queen_observation).

## Dependencies
- Depends on: [[0014-implement-breadcrumb-consistency-fixes]].

## Related
- Project:: [[breadcrumb-consistency]]
- Commits:: 
