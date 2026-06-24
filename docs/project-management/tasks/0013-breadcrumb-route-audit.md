---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-06-17
branch: feature/0013-breadcrumb-route-audit
release: 1.4.0
---
# Task: Breadcrumb route audit

## Context
`src/Breadcrumb/HivelogBreadcrumbBuilder.php` already builds trails for apiary,
hive, hive_inspection, queen, and queen_observation routes (plus a `hivelog.`
catch-all in `applies()`). Before changing anything we need a route-by-route
audit of expected vs actual breadcrumbs, and to record any remaining gaps after
closing the older [[0002-breadcrumb-queen-canonical]] task as already
implemented. Foundation task for [[breadcrumb-consistency]].

## Acceptance criteria
- [ ] A matrix in this note: every route in `hivelog.routing.yml`
      (canonical / add_form / edit_form / delete_form / collection + scoped add
      routes) → expected trail → observed trail → gap (Y/N).
- [ ] Record that [[0002-breadcrumb-queen-canonical]] was already satisfied by
      the current codebase and closed during vault reconciliation.
- [ ] Flag routes where a parameter is **not** upcast to an entity object (the
      `is_object()` guards), which silently drop ancestry.
- [ ] Confirm whether any non-page `hivelog.*` routes need to be excluded from
      `applies()` as new routes are added.
- [ ] Confirm the current implementation against [[0013-breadcrumb-policy]].

## Implementation notes
- `applies()` (lines 41–49) matches by prefix: `entity.apiary.`, `entity.hive.`,
  `entity.hive_inspection.`, `entity.queen.`, `entity.queen_observation.`,
  `hivelog.`. Cross-check against the actual route names in
  `hivelog.routing.yml`.
- Scoped add routes to verify: `hivelog.hive.add` (`/apiary/{apiary}/hive/add`),
  `hivelog.inspection.add` (`/hive/{hive}/inspection/add`), `hivelog.queen.add`
  (`/hive/{hive}/queen/add`), and the queen-observation add route.
- This is investigation/documentation only — no code change here (that is
  [[0014-implement-breadcrumb-consistency-fixes]]).

## Dependencies
- Blocks: [[0014-implement-breadcrumb-consistency-fixes]].
- Reconciles: [[0002-breadcrumb-queen-canonical]].

## Route audit matrix (fill in)
- entity.apiary.canonical → Home › HiveLog → (no self link):
- entity.hive.canonical → Home › HiveLog › Apiary → (no self link):
- entity.hive_inspection.edit_form → … › Hive › Inspection:
- entity.queen.canonical (assigned vs unassigned):
- hivelog.inspection.add → … › Hive:

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]]
- Commits::
