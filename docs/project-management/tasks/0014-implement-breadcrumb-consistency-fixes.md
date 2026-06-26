---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-06-17
branch: feature/0015-breadcrumb-test-coverage
release: 1.4.0
depends-on: ["[[0013-breadcrumb-route-audit]]"]
---
# Task: Implement breadcrumb consistency fixes

## Context
Apply the gaps identified in [[0013-breadcrumb-route-audit]] to
`src/Breadcrumb/HivelogBreadcrumbBuilder.php`. Scope is whatever the audit
surfaces; likely candidates: routes that drop ancestry because a parameter
isn't upcast, excluding non-page `hivelog.*` routes from `applies()`, and (if
decided) appending a consistent trailing crumb. Part of
[[breadcrumb-consistency]].

## Outcome
The [[0013-breadcrumb-route-audit]] found **no code fixes required** for the
current route set. The only actionable item was a **pre-emptive exclusion** for
the future CSV export route (Task 0001), implemented in the same branch as
Task 0015 to keep the test and the fix together.

## Acceptance criteria
- [x] Each gap from the [[0013-breadcrumb-route-audit]] matrix is fixed or
      explicitly deferred with a reason.
      — No gaps requiring fixes. The one acceptable gap (`entity.queen.add_form`
      lacking hive context) is not fixable and is documented as accepted.
- [x] `applies()` stays aligned with the routes that should have breadcrumbs.
      — A `$non_page_routes` exclusion list added to `applies()` with
      `hivelog.queen.observations_csv` pre-populated ready for Task 0001.
- [x] Non-page routes excluded from `applies()`.
      — `hivelog.queen.observations_csv` excluded pre-emptively; documented
      with an inline comment referencing Task 0001 and AGENTS.md.
- [x] Cacheability preserved.
      — No changes to cache context/dependency calls.
- [x] Behaviour verified by the tests in [[0015-breadcrumb-test-coverage]].

## Implementation
One change to `src/Breadcrumb/HivelogBreadcrumbBuilder.php`:
- Added `$non_page_routes` exclusion array at the top of `applies()`.
  Currently contains `hivelog.queen.observations_csv`. When Task 0001 is
  implemented, no further change to `applies()` is needed — the exclusion is
  already in place. Add further non-page routes to the array as they are
  introduced.

## Related
- Project:: [[breadcrumb-consistency]]
- Depends on:: [[0013-breadcrumb-route-audit]]
- Verified by:: [[0015-breadcrumb-test-coverage]]
- PRs:: #95
