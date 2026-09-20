---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: dashboard
created: 2026-09-20
completed: 2026-09-20
branch: main
release: 1.8.7
depends-on: ["[[0072-upcoming-panel-hive-scope-completion]]", "[[0056-dashboard-landing-page]]"]
blocked-by:
---
# Task: Calendar Actions week-range filter + "Open seasonal tasks" footnote

## Context
Follow-up to [[0072-upcoming-panel-hive-scope-completion]]. Once the
"Upcoming" panel / "Open seasonal tasks" tile relationship was explained,
a second, related inconsistency remained:

- The "Upcoming" panel only shows tasks due in the next 4 weeks, while the
  "Open seasonal tasks" stat tile counts every pending task for the rest
  of the year — two different windows with nothing on the dashboard
  explaining the difference.
- The stat tile's count (e.g. 9) had no matching destination: its link,
  `/hivelog/calendar-actions`, showed every calendar action across every
  apiary with no filter and no indication of which rows were actually
  pending.

## Resolution
- `entity.calendar_action.collection` now routes through a new
  `CalendarActionController::collection()` instead of Drupal's default
  entity list, adding a From week / To week filter
  (`HivelogCalendarActionsFilterForm`) plus an Apiary column and per-row
  Edit/Delete operations. Filtering matches on `week_start` only — the
  same simplified "does the action start in this window" semantic
  `DashboardController::buildUpcoming()` already uses, rather than a full
  window-overlap check against `week_end`.
- Preserves `CalendarActionListBuilder`'s original "show disabled rows
  too" default (this is the page a beekeeper uses to find and re-enable a
  disabled action) — a deliberate difference from the per-apiary Full
  Calendar page, which defaults to "Enabled only".
- `CalendarActionListBuilder` itself, and the entity type's `list_builder`
  handler, are unchanged — `ListCollectionTitleTest` instantiates the list
  builder directly regardless of which controller the route uses.
- The "Open seasonal tasks" stat tile now links with `week_from`/`week_to`
  pre-filled to "this week through week 53" — the closest a filtered table
  can get to matching a count that has no week restriction at all.
- Added a footnote to the collection page explaining the count: every
  enabled calendar action not yet reported Done/Ignored for the year,
  for any week — apiary-scoped counts once, hive-scoped counts once per
  hive — while the table itself lists each action once (not fanned per
  hive).

## Acceptance criteria
- [x] `/hivelog/calendar-actions` has a From week / To week filter that
      narrows the listed actions by `week_start`.
- [x] A reversed range (`week_from` > `week_to`) is swapped rather than
      producing an empty result.
- [x] The page still lists every apiary's actions, enabled and disabled,
      with Edit/Delete operations, by default (unchanged from before).
- [x] The dashboard's "Open seasonal tasks" tile links to
      `/hivelog/calendar-actions?week_from=<current week>&week_to=53`.
- [x] A footnote on the collection page explains the tile's calculation.
- [x] `CalendarActionCollectionTest` (5 tests) added; `ListCollectionTitleTest`,
      `HiveCalendarChecklistTest`, `ApiaryCalendarChecklistTest`,
      `DashboardTest` (56 tests) and the breadcrumb unit test (105 tests)
      still green; full `tests/src/Kernel` suite green from cms2
      (439 tests).
- [x] phpcs clean (`--standard=Drupal,DrupalPractice --warning-severity=0`).
- [x] Pushed directly to `main` — commit `c7db2eb`. Shipped in release
      1.8.7.

## Implementation notes
- Key files: `hivelog.routing.yml`, `src/Controller/CalendarActionController.php`
  (`collection`, `extractCollectionFilters`, `applyCollectionFilters`),
  `src/Form/HivelogCalendarActionsFilterForm.php`,
  `src/Controller/DashboardController.php` (`buildStatTiles`),
  `css/hivelog.tables.css` (`.hivelog-list-footnote`),
  `tests/src/Kernel/CalendarActionCollectionTest.php`.
- No entity schema change — no update hook.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Follows:: [[0072-upcoming-panel-hive-scope-completion]], [[0056-dashboard-landing-page]]
- Commits:: `c7db2eb` (fix + tests); release 1.8.7 bump
