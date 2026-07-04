---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: theme
created: 2026-07-03
branch: feature/0020-apiary-and-hive-calendar-ui
release:
depends-on: ["[[0019-calendar-routing-controllers-and-access]]"]
blocked-by:
---
# Task: Surface the calendar on the apiary and hive canonical pages

## Context
This is the user-facing payoff of the project: the apiary page shows the
shared seasonal plan, and each hive's page shows that hive's own progress
against it, defaulting to unreported items only. Depends on the routes/
controllers from [[0019-calendar-routing-controllers-and-access]]. The
interactive filter control and one-click report actions are a separate
follow-on task, [[0021-hive-calendar-filtering-and-report-actions]] — this
task delivers the base tables with the correct default behaviour baked in
(no query-string controls yet).

## Acceptance criteria
- [x] `ApiaryController::view()` renders a "Seasonal Calendar" table (reuses
      the `entity-table` SDC component) listing the apiary's `enabled`
      `CalendarAction` rows only, sorted by `week_start`, with an "Add
      Calendar Action" button next to the existing "Add Hive" button.
      Columns: title (linked, plus an Operations column with Edit/Delete —
      added beyond the task's literal column list to match every other
      embedded table in the module, see Implementation notes), week(s),
      category (if set). `description` is not shown in the table row — the
      title links to the calendar action's canonical page instead.
- [x] `CalendarActionController::view()` already rendered `description` with
      bullet-list support via `SimpleBulletText::render()` — this was
      wired in ahead of schedule while implementing the controller in
      [[0019-calendar-routing-controllers-and-access]]; no further work
      needed here. The helper itself was built in
      [[0017-calendar-action-entity-and-schema]].
- [x] `HiveController::view()` renders a checklist via a new protected
      `buildCalendarChecklist(Hive $hive, int $year, string $status_filter)`
      method: one row per `enabled` `CalendarAction` belonging to the
      hive's apiary, for the **current year**, **defaulting to unreported
      items only** (no `HiveActionLog` row for
      `(hive, calendar_action, current_year)`, or one with
      `status = pending`). Columns: title, planned week(s), status
      ("Pending"), and a "Log" button
      (`hivelog.hive_action_log.add`, scoped to that hive + calendar
      action). Two distinct empty states, verified against a real
      scenario each: "No pending seasonal actions for this hive." when
      calendar actions exist but none are pending; "This apiary has no
      calendar actions set up yet." when the apiary has none at all.
- [x] Buttons use the existing `button`/`button-group` SDC per
      [[0012-action-button-design-system]] — no new CSS classes, no
      framework/utility classes added.
- [x] No new `@media` rules were needed — both additions reuse existing
      `hivelog-list-heading` / `entity-table` styling as-is.
- [x] Cache metadata: both controllers add cacheable dependencies/tags for
      the calendar-action and (on the hive page) hive-action-log entities
      rendered, per [[0009-render-cacheability-discipline]] — verified
      directly (see below), not just by inspection.

## Verification (against `/Users/paddy/Development/cms2`)
- **Regression found and fixed**: adding `calendar_action`/`hive_action_log`
  queries to `ApiaryController::view()` / `HiveController::view()` broke 19
  existing kernel tests across `ApiaryTest`, `ControllerCacheMetadataTest`,
  `EmbeddedTableFilterPaginationTest`, and `HiveTest` — each installs a
  hand-picked subset of entity schemas in `setUp()` and didn't have the two
  new tables, so the query failed with "table doesn't exist". Fixed by
  adding `installEntitySchema('calendar_action')` /
  `installEntitySchema('hive_action_log')` to all four files' `setUp()`,
  following the exact precedent already established for `queen`/
  `queen_observation` when the queen section was added to
  `HiveController::view()`. Full suite re-run afterwards: **124 kernel
  tests / 2186 assertions and 54 unit tests / 196 assertions, all pass.**
- Empty states, both verified against real scenarios (not just code
  reading): an apiary/hive with zero calendar actions shows "This apiary
  has no calendar actions set up yet."; an apiary/hive with calendar
  actions that are all already reported (`done`/`ignored`) shows "No
  pending seasonal actions for this hive." (hive page) — confirmed these
  are two different, correctly-triggered messages, not a coincidental
  match.
- Filtering correctness: a `pending` (unreported) action appears on the
  hive checklist; a `done` action does not; a `disabled` (`enabled =
  FALSE`) action never appears on **either** the apiary table or the hive
  checklist. An `ignored` action is also correctly hidden from the default
  checklist view.
- **Year scoping**: a `HiveActionLog` for a *different* year does not
  suppress this year's pending row — confirms `buildCalendarChecklist()`'s
  year-matching logic is correct, which is the exact mechanism that will
  make "view next year's pending items" in
  [[0021-hive-calendar-filtering-and-report-actions]] work for free.
- Rendering spot-checks: the apiary table shows the correct week range
  (e.g. "15–17") and category label; the calendar action's own canonical
  page (via `CalendarActionController`) renders a `- ` bulleted
  `description` as real `<ul><li>...</li></ul>` markup.
- Cache tags directly inspected on the render array (`$build['#cache']
  ['tags']`), not just the code: apiary page includes the
  `calendar_action` list tag and the specific rendered calendar action's
  own tag; hive page includes both the `calendar_action` and
  `hive_action_log` list tags plus the specific rendered calendar
  action's and log's own tags.
- All smoke-test entities cleaned up; `hivelog_calendar_action` and
  `hivelog_hive_action_log` are empty in `cms2` again; `drush cr` clean.

## Implementation notes
- Key files changed: `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php`. Test files changed (regression fix):
  `tests/src/Kernel/ApiaryTest.php`,
  `tests/src/Kernel/ControllerCacheMetadataTest.php`,
  `tests/src/Kernel/EmbeddedTableFilterPaginationTest.php`,
  `tests/src/Kernel/HiveTest.php`.
- The apiary's calendar table includes an Operations (Edit/Delete) column
  even though the task's acceptance criteria only listed
  title/week(s)/category — every other embedded table in the module
  (hives, inspections, observations) has one, and omitting it here would
  have been a visible inconsistency for no benefit given `->access()`
  checks were trivial to add per row.
- `buildCalendarChecklist()` placed alongside `extractInspectionFilters()`/
  `applyInspectionFilters()` as instructed, so
  [[0021-hive-calendar-filtering-and-report-actions]] can extend its
  `$status_filter`/`$year` parameters from the query string rather than
  duplicating the cross-referencing logic. Deliberately did **not** add a
  `url.query_args` cache context in this task, since nothing here actually
  reads the query string yet (cacheability discipline: don't declare a
  context you don't vary on) — 0021 adds it alongside the real query-string
  reads.
- New sections were appended after existing content (apiary: weight 20-21,
  after the hives table; hive: weight 25-26, after the images grid) rather
  than interleaved into the existing weight sequence, to avoid touching
  established weights that other tests might implicitly depend on.
- Field-rendering/formatting helpers (week range formatting, category
  label lookup) are duplicated between `ApiaryController` and
  `HiveController` rather than extracted into a shared utility — matches
  the module's existing convention of each controller owning its own
  presentation helpers (e.g. `buildFieldValue()` is separately implemented
  per entity controller already).

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0012-action-button-design-system]], [[0009-render-cacheability-discipline]], [[0011-responsive-design-strategy]], [[0017-output-sanitisation-policy]]
- Commits::
