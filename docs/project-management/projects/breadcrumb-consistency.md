---
type: project
tags: [hivelog/project]
status: planning
target: 1.4.0
created: 2026-06-17
---
# Project: Breadcrumb Consistency

## Goal
Guarantee that every Hivelog route renders a predictable, correct, and tested
breadcrumb trail (Home → HiveLog → Apiary → Hive → …), and that the rules are
documented and covered by tests so future routes stay consistent.

## Scope
- In scope: auditing all routes against the current breadcrumb builder,
  reconciling the older [[0002-breadcrumb-queen-canonical]] task, fixing any
  remaining gaps, and adding test coverage across route types.
- Out of scope: visual styling of the breadcrumb (that is theme territory) and
  non-Hivelog routes.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT priority asc, file.name asc
```
Static index (in suggested execution order):
- [[0013-breadcrumb-route-audit]] — audit + reconcile (do first)
- [[0014-implement-breadcrumb-consistency-fixes]]
- [[0015-breadcrumb-test-coverage]]

## Key findings (from code scan, 2026-06-17; reconciled 2026-06-22)
- `src/Breadcrumb/HivelogBreadcrumbBuilder.php` already builds trails for
  apiary, hive, hive_inspection, queen, **and** queen_observation routes, plus a
  catch-all for the `hivelog.` route prefix (`applies()`, lines 41–49).
- The queen branch already threads Apiary → Hive → Queen ancestry and handles
  unassigned queens. That matches the older
  [[0002-breadcrumb-queen-canonical]] task, which is now closed in the vault.
- Ancestry is only added when a route parameter is upcast to an object
  (`is_object(...)` guards). Any custom route that passes a raw ID instead of an
  upcast entity will silently lose its trail — a prime audit target.
- `applies()` is prefix-based; `AGENTS.md` warns that it must stay in sync when
  routes are added or renamed.

## Open questions
- Does the current implementation fully match [[0013-breadcrumb-policy]] on
  excluding non-page `hivelog.*` routes once such routes exist?
- Beyond the already-closed queen canonical case, are there any real route gaps
  left after the audit matrix is completed?

## Related decisions
- [[0013-breadcrumb-policy]]
- [[0020-access-parity-custom-routes]]
