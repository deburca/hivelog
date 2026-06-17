---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-06-17
branch: feature/0014-implement-breadcrumb-consistency-fixes
release: v1.4.0
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

## Acceptance criteria
- [ ] Each gap from the [[0013-breadcrumb-route-audit]] matrix is fixed or
      explicitly deferred with a reason.
- [ ] `applies()` stays aligned with the routes that should have breadcrumbs —
      `AGENTS.md` explicitly warns to keep `applies()` in sync when routes
      change.
- [ ] Non-page routes (e.g. CSV export from [[0001-queen-observation-csv-export]])
      are excluded if the audit decided so.
- [ ] Cacheability preserved: keep `addCacheContexts(['route'])` and the
      per-entity `addCacheableDependency()` calls so breadcrumbs invalidate
      correctly.
- [ ] Behaviour verified by the tests in [[0015-breadcrumb-test-coverage]].

## Implementation notes
- The builder threads ancestry via reverse references: hive→apiary,
  inspection→hive→apiary, queen→hive→apiary, observation→queen→hive→apiary.
  Reuse those patterns; avoid duplicating lookups.
- Keep self-link suppression on canonical routes (the `$route_name !==
  'entity.*.canonical'` checks) unless the audit changes the trailing-crumb
  policy.
- If schema/route changes are introduced elsewhere, remember the AGENTS.md rule
  about pairing entity schema changes with update hooks (not expected here —
  this is routing/breadcrumb only).

## Dependencies
- Depends on: [[0013-breadcrumb-route-audit]].
- Verified by: [[0015-breadcrumb-test-coverage]].

## Related
- Project:: [[breadcrumb-consistency]]
- Commits:: 
