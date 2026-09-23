---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0130-shared-calendar-checklist-helpers
release:
depends-on:
blocked-by:
---
# Task: De-duplicate the calendar-checklist helpers

## Context
From the page-structure review of 2026-09-23. `ApiaryController` (1,272
lines) and `HiveController` (1,414 lines) are the two largest
controllers, and both embed a seasonal-calendar checklist built with
copied helpers (fingerprinted by body hash):

| Helper | Copies | Identical? |
|---|---|---|
| `extractCalendarFilters()` | Apiary, Hive | yes |
| `pendingActionTimingLabel()` | Apiary, Hive | yes |
| `calendarChecklistEmptyMessage()` | Apiary, Hive | near (wording differs) |
| `secondsUntilNextIsoWeek()` | Apiary, Hive, Dashboard | yes |
| `viewableApiaries()` | Dashboard, InventoryReport | near |

The checklist builders themselves (`buildApiaryCalendarChecklist()`,
`HiveController::buildCalendarChecklist()`) share most of their row and
Done / Ignored button logic too.

## Acceptance criteria
- [ ] A `hivelog.calendar_checklist_builder` service (matching the
      existing `hivelog.stat_tile_builder` precedent) owns the checklist
      build for both scopes (apiary / hive), the filter extraction,
      the timing label, the empty message (scope-aware wording) and the
      cache max-age (`secondsUntilNextIsoWeek()`).
- [ ] `ApiaryController` and `HiveController` delegate to it. Their
      copies are deleted.
- [ ] `secondsUntilNextIsoWeek()` has one home (the service, or a small
      static utility used by the service and `DashboardController`).
- [ ] `viewableApiaries()` has one home, shared by `DashboardController`
      and `InventoryReportController`, keeping the stricter of the two
      current implementations if they differ. Document which and why.
- [ ] Rendered output of both calendar sections unchanged. Existing
      `ApiaryCalendarChecklistTest` / `HiveCalendarChecklistTest` pass
      unchanged.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Pure refactor, no behaviour change. Low priority: it reduces
  maintenance cost but fixes nothing user-visible.
- Key files: `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php`,
  `src/Controller/DashboardController.php`,
  `src/Controller/InventoryReportController.php`,
  `hivelog.services.yml`.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0110-hive-apiary-stat-tiles]] (the service-extraction
  precedent)
- Commits::
