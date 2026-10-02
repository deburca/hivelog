---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: routing
created: 2026-10-02
branch: feature/0169-filter-form-date-range-validation
release:
depends-on:
blocked-by:
---
# Task: Validate "From" and "To" date ranges in filter forms

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]] (done).
`HivelogInspectionFilterForm` applies `date_from` (`>=`) and `date_to`
(`<=`) as independent conditions with no check between them. A "From" later
than "To" silently produces an empty table and the "no rows match your
filters" message, with nothing telling the beekeeper the range is the
problem. The Queen Observation and Inventory Purchase filters and the
calendar filters probably behave the same way — to be confirmed in this
task, since only the inspection form was inspected.

## Acceptance criteria
- [x] Audit every filter form with a from/to pair (inspection, queen
      observation, inventory purchase, plus any others found) and list them
      in the implementation notes.
- [x] A "From" later than "To" produces a visible message on the form and
      does not silently return zero rows. Decide: reject (show an error and
      apply no date filter) or swap the two values.
- [x] Because these are GET forms with a static `extract()`/`apply()` split,
      the check must work from the query string without a form-submit
      round-trip, and must not break Reset.
- [x] Shared helper for the check, not a copy per form.
- [x] Kernel tests: reversed range, equal dates, one side empty, both empty.
- [x] Verified live on `cms2`.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Form/HivelogInspectionFilterForm.php`,
  `HivelogQueenObservationFilterForm.php`,
  `HivelogInventoryPurchaseFilterForm.php`.
- No update hook.

## Implementation notes (as built)
- **Audit (all filter forms with a From/To pair):** Inspections
  (`date_from`/`date_to`), Queen Observations (`obs_date_from`/`obs_date_to`,
  prefixed because it shares the hive page's query string with
  Inspections), Inventory Purchases (`date_from`/`date_to`), Sensor
  readings in `nanoprobe` (`date_from`/`date_to`, applied by
  `SensorDeviceController`, not the form), and Calendar Actions
  (`week_from`/`week_to`). The calendar and full-calendar *filters*
  (`HivelogCalendarFilterForm`, `HivelogFullCalendarFilterForm`) have no
  pair. The task guessed at three; there were five, and the guess about
  "the others probably behave the same" was only half right:
  - Inspections, Observations and Purchases applied the two ends as
    independent conditions, with no format check either.
  - Sensor readings validated the *format* of each date (a private
    `extractValidDate()`) but not their order.
  - Calendar Actions already swapped a reversed pair, silently — and the
    form kept showing the reversed values the user typed, so the fields
    and the results disagreed.
- **Decision: swap, with a visible notice** (not reject). It matches what
  Calendar Actions already did, and a reversed pair is nearly always a
  slip, so keeping the evident range beats discarding it. A malformed date
  is dropped (treated as absent) with a notice saying so.
- New `src/HivelogRangeFilter.php` (final, static): `normaliseDates()`,
  `normaliseWeeks()`, `datesFromRequest()`, `isValidDate()`,
  `noticeElement()`. Each `normalise*` returns `[from, to, notices]` rather
  than emitting anything, because the forms' static `extract()` is also
  called by list builders and controllers, which must not print. This one
  class replaced `SensorDeviceController::extractValidDate()` (deleted)
  and the inline week logic in `CalendarActionController` (now delegates).
- Works from the query string with no form-submit round trip, and doesn't
  touch Reset (the notice is not part of any URL). The notice is a
  `p.hivelog-filter-form__notice[role=status]` using the existing warning
  tokens, added to `css/hivelog.filter-form.css`, and is only added to the
  form when there is a notice.
- Behaviour change worth knowing: the displayed From/To fields now show the
  *corrected* values, so after a swap the fields no longer echo what was
  typed. Intentional — they match what is being filtered.
- Tests: new `tests/src/Unit/HivelogRangeFilterTest.php` (ordered, equal,
  reversed, one-sided, both empty, seven malformed shapes, invalid-then-
  reversed ordering, truncation of a huge value, weeks incl. clamping and
  `-5`/`2.5`/`1e1`, custom keys, notice element, HTML escaping) plus
  integration tests added to `FullListFilterTest` (reversed, malformed and
  valid-no-notice for Inspections; reversed Observations),
  `InventoryProductFilterTest` (reversed Purchases),
  `CalendarActionCollectionTest` (swapped form values + notice; valid range
  has no notice) and nanoprobe's `SensorDeviceReadingsTest` (reversed
  range). A mutation check (disabling the swap in the cms2 copy) failed 8
  tests, so they do detect it.
- Verified on `cms2` (`vdg`) through the real HTTP kernel as admin with
  throwaway fixtures: reversed Inspections range returned only the in-range
  row with the "swapped" notice; a bad date returned all rows with "not a
  valid date"; a clean range showed no notice; and Purchases, Calendar
  Actions and sensor readings all showed the swapped notice. Not clicked
  through in a browser (the pane was logged out and I did not sign in), so
  the notice's *appearance* is unchecked. Fixtures, including child
  records, deleted and confirmed gone.
- phpcs and phpstan clean; no baseline change.
- AGENTS.md: the filter-hook paragraph now says a From/To filter must use
  `HivelogRangeFilter`.
- **Full suite: 1,157 tests / 19,193 assertions, 0 failures, 0 errors**
  (1,131 before this task + 26 new: 18 unit, 8 integration), only the usual
  third-party deprecations and notices. Run as seven separately-completed
  chunks again (six balanced non-Functional chunks of 170–228 tests, then
  Functional), since a single invocation exceeds the background time
  limit.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]], [[0156-inventory-and-product-list-filters]]
- Commits::
