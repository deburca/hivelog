---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[queen-observation-enhancements]]"
area: routing
created: 2026-06-16
branch: feature/0002-breadcrumb-queen-canonical
release:
---
# Task: Breadcrumb trail on the queen canonical page

## Context
`hivelog.breadcrumb` (priority 100) should build the Apiary → Hive → Queen
trail for the queen canonical route, resolving the queen's linked hive and
handling unassigned queens gracefully. This task was created before the vault
was fully reconciled with the codebase.

## Resolution
No code change was needed during the documentation cleanup: the behaviour is
already implemented in `src/Breadcrumb/HivelogBreadcrumbBuilder.php` and covered
by `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.

## Acceptance criteria
- [x] `applies()` in the breadcrumb builder matches `entity.queen.canonical`.
- [x] Trail renders Apiary → Hive → Queen, using the queen's `hive` reference.
- [x] Gracefully handles a queen with no `hive` (inactive queen): stop at the
      last resolvable parent rather than erroring.
- [x] Unit test extended in
      `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.

## Implementation notes
- Verified against the current builder and unit test on 2026-06-22.
- This task remains linked from [[breadcrumb-consistency]] only as historical
  context; ongoing breadcrumb work starts from [[0013-breadcrumb-route-audit]].
- No schema change → no update hook.

## Related
- Project:: [[queen-observation-enhancements]]
- Decisions:: [[0013-breadcrumb-policy]]
- Commits:: implemented before vault reconciliation; see current code/tests
