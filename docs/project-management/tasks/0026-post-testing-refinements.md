---
type: task
tags: [hivelog/task]
status: in-progress
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: theme
created: 2026-07-04
branch: feature/0026-post-testing-refinements
release:
depends-on: ["[[0024-calendar-test-coverage]]"]
blocked-by:
---
# Task: Post-testing usability refinements

## Context
An umbrella task for small usability improvements surfaced by the user's
own extensive hands-on testing of the shipped seasonal calendar feature,
after [[0024-calendar-test-coverage]] closed out the project's original
scope. The automated test suite verifies *behaviour* (filters, access
control, seeding, etc.); it doesn't catch *at-a-glance usability* gaps
like this one. Rather than spinning up a new numbered task per small
tweak, this task collects the whole round of feedback as it comes in.

## Items

### 1. Show the current week number, and whether each item is due — done
**Problem:** the calendar tables show each item's planned week(s), but
nowhere on the page is the *current* week shown, so it isn't obvious
whether a given item is due, overdue, or still upcoming without manually
working out today's ISO week and comparing it by hand.

**Fix:**
- Both `ApiaryController::view()` and `HiveController::view()` now show
  "Seasonal Calendar (current week: @week)" in the section heading
  (`(int) date('W')`), rather than adding a new flex child to
  `.hivelog-list-heading` (which is styled for exactly two children via
  `justify-content: space-between`) or introducing any new CSS.
- The apiary's Seasonal Calendar table gains a new **Timing** column:
  `Upcoming` / `Due now` / `Past`, computed by comparing `week_start`/
  `week_end` against the current week
  (`ApiaryController::weekTimingLabel()`). Shown for every row — the
  apiary table has no per-hive reporting status, so timing is the only
  relevant signal there.
- The hive checklist merges the equivalent signal into the existing
  **Status** column instead of adding a new column, since Status is
  already the semantically relevant place: unreported rows become
  `Unreported (Due now)` / `Unreported (Overdue)` / `Unreported
  (Upcoming)` (`HiveController::pendingActionTimingLabel()` — "Overdue"
  rather than "Past", since it's only ever computed for still-`pending`
  rows and is meant to read as actionable). `done`/`ignored` rows are
  left as plain `Done`/`Ignored` with no timing suffix — once reported,
  timing is moot.
- The timing suffix on the hive checklist is only shown when the
  selected year filter equals the current real year
  (`$show_timing = ($calendar_filters['year'] === $current_year)`).
  Previewing a future year would otherwise trivially label every row
  "Upcoming", which isn't useful information — the "current week" text in
  the heading is still always shown regardless of the selected year,
  since that's just a factual statement, not a judgement relative to a
  possibly-different selected year.
- **Cacheability**: both timing computations depend on `date('W')`/
  `date('Y')` ("now"), so both controllers now cap their render's
  `#cache max-age` to the number of seconds remaining until the next ISO
  week boundary (next Monday, midnight) via a new
  `secondsUntilNextIsoWeek()` helper on each controller, so a cached page
  can never show a stale week/timing after the real week changes.
  `CalendarAction`/`HiveActionLog` never wrap across the year boundary
  (`week_end >= week_start` is enforced), so plain integer comparison is
  sufficient for all of the above — no modulo/wraparound arithmetic
  needed anywhere.

**Verified** against `/Users/paddy/Development/cms2`:
- Full existing suite re-run clean: 178 kernel / 3175 assertions, 70 unit
  / 249 assertions — no regressions from the new column/heading text.
- Real scenario with three calendar actions (weeks 10–12, 25–29, 40–42
  against a real current week of 27): apiary table showed exactly
  `Past` / `Due now` / `Upcoming` respectively, with `current week: 27`
  in the heading.
- Same three actions reported as `pending` `HiveActionLog` rows on the
  hive checklist showed `Unreported (Overdue)` / `Unreported (Due now)`
  / `Unreported (Upcoming)`; a `done` row alongside them showed plain
  `Done` with no timing leakage.
- Switching the hive checklist to next year correctly dropped the timing
  suffix entirely on every row (including the previously-`done` one,
  which correctly shows as `Unreported` for a year it has no log for) —
  confirms the year-gating condition works, not just in the abstract.
- `#cache max-age` on both pages was a bounded positive number
  (~108,800 seconds, i.e. the real remaining time to next Monday
  midnight at time of testing), not `-1`/permanent.
- `composer lint` (phpcs): both changed files are error-free;
  `HiveController.php`'s only remaining errors are the same pre-existing
  weight-histogram array issue already documented in
  [[0024-calendar-test-coverage]] (line numbers shifted, not new).
- All test data cleaned up; both calendar tables empty in `cms2`
  afterwards.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0009-render-cacheability-discipline]]
- Commits::
