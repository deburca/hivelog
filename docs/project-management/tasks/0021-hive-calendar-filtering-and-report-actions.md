---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: theme
created: 2026-07-03
branch: feature/0021-hive-calendar-filtering-and-report-actions
release:
depends-on: ["[[0020-apiary-and-hive-calendar-ui]]"]
blocked-by:
---
# Task: Hive checklist filtering + "Report Done" / "Report Ignored" actions

## Context
Builds on the base checklist from [[0020-apiary-and-hive-calendar-ui]] (which
hard-codes "unreported, current year"). This task adds the interactive
controls: a filter form to switch status/year, and one-click reporting.
Two explicit user-facing requirements land here:
- "It should be possible to filter the calendar items for a hive, such that
  only un-reported items should be shown — the default view."
- "At the beginning of the year, it should be possible to view all the
  pending items for the coming year."

## Acceptance criteria
- [x] New `HivelogCalendarFilterForm` (GET-submitted `FormBase`, following
      `HivelogInspectionFilterForm` exactly: `#method = 'get'`,
      `hivelog/filter_form` library, `url.query_args` cache context, a
      "Reset" button linking back to the plain hive canonical URL).
- [x] `status` filter: select with options "Unreported" (default, maps to
      the `pending`/no-row state), "Done", "Ignored", "All".
- [x] `year` filter: select spanning previous year, current year (default),
      and next year.
- [x] `HiveController::view()` reads both filters via a new
      `extractCalendarFilters()` method (mirroring
      `extractInspectionFilters()`) and passes them straight into
      `buildCalendarChecklist()` from [[0020-apiary-and-hive-calendar-ui]] —
      no duplication of the cross-referencing logic, exactly as planned.
- [x] "Report Done" and "Report Ignored" buttons on each unreported
      checklist row: plain links (safe `GET` navigation, `hivelog:button`
      SDC via `Url::fromRoute(..., ['query' => ['status' => 'done']])`) to
      `hivelog.hive_action_log.add`. The actual write happens through that
      form's normal `POST` submission — verified this remains true (no
      bespoke mutation endpoint was added).
- [x] Once a row is reported, it drops out of the default "Unreported" view
      but remains visible under "Done"/"Ignored"/"All", showing
      `week_completed` and `notes` (via `nl2br(Html::escape())`, matching
      the module's established sanitisation convention) — two new table
      columns added for this. Reported rows show "View Log" (unconditional)
      and "Edit" (gated by `$log->access('update')`) instead of the report
      buttons.
- [x] Cache metadata: `url.query_args` was **already present** on the hive
      page render (shared with the pre-existing inspection filter) — no
      code change needed, just confirmed and documented.

## Verification (against `/Users/paddy/Development/cms2`)
- **Regression found and fixed**: `HivelogCalendarFilterForm` initially
  redeclared `protected RequestStack $requestStack;` as a typed property,
  which fatals at runtime — `FormBase` already declares this property
  untyped (it backs `FormBase::getRequest()`), and PHP forbids
  redeclaring an inherited property with a type. This broke 12 kernel
  tests that render the hive page (any test touching
  `HiveController::view()`). Fixed by removing the property declaration
  and assigning `$this->requestStack` directly in the constructor,
  matching `HivelogInspectionFilterForm`'s existing (correct) pattern.
  Full suite re-run afterwards: **124 kernel tests / 2186 assertions and
  54 unit tests / 196 assertions, all pass.**
- Filter form itself: default `status` value is `pending`, options are
  exactly `pending`/`done`/`ignored`/`all`; default `year` value is the
  current year, options are `[current-1, current, current+1]`; `#method`
  is `get`; a Reset button and `url.query_args` cache context are both
  present — checked directly on the built form array.
- End-to-end filtering, via real `Request` objects pushed onto the request
  stack (not just unit-level method calls): default view (no query args)
  shows only a pending action and hides a done one, with both "Report
  Done"/"Report Ignored" buttons present; `?status=done` shows only the
  done action (with its `week_completed` and `notes` visible) and a "View
  Log" button, hiding the pending one; `?status=all` shows both; an
  invalid `?status=nonsense` falls back to the pending view rather than
  erroring or showing nothing.
- **Year-preview requirement, verified concretely**: reported this year's
  occurrence of a `recurring` action as `done`; the default (this-year)
  view correctly hides it; switching to `?year=<next year>` shows the same
  action as pending again (no log exists for that year yet) — this is the
  literal "view all pending items for the coming year" requirement working
  end-to-end. An out-of-range year (current + 10) correctly falls back to
  the current year rather than being accepted.
- Confirmed the actual generated "Report Done" link href
  (`/hivelog/hive/{hive}/calendar-action/{action}/log/add?status=done`)
  matches what `HiveActionLogController::addForm()` (built in
  [[0019-calendar-routing-controllers-and-access]]) expects to read.
- All smoke-test entities cleaned up; both calendar tables empty in
  `cms2` again; `drush cr` clean.

## Implementation notes
- Key files: `src/Form/HivelogCalendarFilterForm.php` (new),
  `src/Controller/HiveController.php` (added `extractCalendarFilters()`
  and `calendarChecklistEmptyMessage()`, updated the calendar section of
  `view()`).
- The empty-state message now distinguishes four cases rather than two:
  no calendar actions at all; none pending; none done; none ignored (the
  `all` filter never produces an empty table when calendar actions exist,
  since every enabled action has *some* effective status). This goes
  slightly beyond the task's literal two-message ask but was a natural,
  low-cost extension once the status filter existed.
- `HivelogCalendarFilterForm` does not inject `EntityFieldManagerInterface`
  (unlike `HivelogInspectionFilterForm`, which pulls `brood_pattern`'s
  allowed values dynamically) — the calendar filter's option labels
  deliberately diverge from the raw `HiveActionLog.status` field's own
  allowed-value labels (`pending` → "Unreported" here, plus a synthetic
  `all` option the field doesn't have), so hardcoding the options was
  simpler and clearer than fetching-then-relabelling.
- No schema changes — no update hook added.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0018-csrf-and-safe-http-methods]], [[0009-render-cacheability-discipline]]
- Commits::
