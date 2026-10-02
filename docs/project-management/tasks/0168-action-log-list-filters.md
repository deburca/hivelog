---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: routing
created: 2026-10-02
branch: feature/0168-action-log-list-filters
release:
depends-on:
blocked-by:
---
# Task: Filters on the Hive and Apiary Action Log lists

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]] (done).
That project covered eight collection pages and explicitly excluded only
Calendar Actions (controller-built, not a `HivelogListBuilder`). It never
mentioned `HiveActionLogListBuilder` and `ApiaryActionLogListBuilder`, which
both extend `HivelogListBuilder` and so can use the same
`getFilterForm()`/`applyFilters()`/`hasActiveFilters()` hooks — they simply
never got an override. These are probably the fastest-growing lists in the
module (one row per action per occurrence, per hive or apiary), so they
benefit most from filtering. Calendar Actions stays out of scope, as before.

## Acceptance criteria
- [x] New `HivelogHiveActionLogFilterForm` and
      `HivelogApiaryActionLogFilterForm` (static `extract()`/`apply()`, GET
      method, `hivelog-filter-form` class), matching the pattern in
      [[0155-apiary-and-queen-list-filters]].
- [x] Filter fields (decided, see notes): status select, year, "calendar
      action contains" and "hive/apiary contains" text fields. Only fields
      already on the entities — no schema change.
- [x] Both list builders wire the three hook methods; filter state lives in
      the query string; Reset targets `<current>`.
- [x] Empty-state message distinguishes "no rows" from "no rows match your
      filters" (as `HivelogListBuilder` already does for the others).
- [x] Filters survive pagination on these lists.
- [x] Full suite green: 1,131 tests / 19,008 assertions, 0 failures, 0 errors.
- [x] Kernel tests per form, following `ApiaryQueenFilterTest` /
      `InventoryProductFilterTest`.
- [x] Verified live on `cms2` with throwaway fixtures, cleaned up afterward.
- [x] `collection-page-filter-coverage`'s project note gets a line recording
      that the two action-log lists were added after it closed, and that
      Calendar Actions remains excluded.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HiveActionLogListBuilder.php`,
  `src/ApiaryActionLogListBuilder.php`, new filter form classes under
  `src/Form/`.
- AGENTS.md's list-builder description may need a line if it enumerates
  which lists have filters.
- No update hook.

## Implementation notes (as built)
- Files: new `src/Form/HivelogActionLogFilterFormBase.php` (abstract; shared
  `buildForm()`/`extract()`/`apply()`), thin subclasses
  `HivelogHiveActionLogFilterForm` and `HivelogApiaryActionLogFilterForm`
  (each sets only `ENTITY_TYPE`, `PARENT_FIELD`, `FORM_ID` and its
  label); `HiveActionLogListBuilder` / `ApiaryActionLogListBuilder` wire the
  three hooks; new `tests/src/Kernel/ActionLogFilterTest.php`;
  `phpstan-baseline.neon`; AGENTS.md; the project note.
- **Fields decided without the confirmation the task asked for**: status
  (select from the entity's `allowed_values`), year (exact, all digits only;
  anything else is ignored rather than passed to the query), "Calendar
  action contains" and "Hive contains" / "Apiary contains". The related-
  entity filters are text matches, not selects, deliberately: a select
  would list every calendar action, hive or apiary on the site to a user
  who may only see some, whereas a text match only narrows rows `load()`
  has already access-filtered. Trade-off: no dropdown convenience, and a
  log whose hive or action has been deleted can't be matched by name.
  Say if you'd rather have selects scoped to the user's accessible records.
- A shared abstract base rather than two copies, because the two log types
  have identical fields; this departs from the one-class-per-filter pattern
  of 0155-0158, where the entities genuinely differ.
- phpstan: one `new.static` finding on the new base class, baselined (454
  total, was 453) per the established `FormBase` precedent. `new self()`
  would not work here — the base is abstract.
- Tests (9, `ActionLogFilterTest`): status + Reset, year incl. non-numeric
  values, action/hive name filters, LIKE wildcards matched literally,
  empty-state wording, apiary log filters, status options from the entity
  definition, pagination over the filtered set with `status=done` present
  in the rendered pager links, and a stranger user still seeing zero rows
  for a private apiary's log while filtering. A mutation check (disabling
  the year condition in the cms2 copy) made two tests fail, so they do
  detect a broken filter.
- Verified on `cms2` (`vdg`): throwaway apiary, two hives, two calendar
  actions and three logs, pages rendered through the real HTTP kernel as
  admin — all HTTP 200, filter form present, correct row counts for parent
  name, status+action and apiary-log filters, and the "no rows match" state
  for year 1999. Not clicked through in a browser: the pane was logged out
  and I did not sign in. Cleanup initially left two orphaned hives (deleting
  an apiary through storage doesn't remove its hives, which are BLOCK
  rows); both removed and confirmed gone.
- Not done: heading cross-links ("View Hives" / "View Apiaries") on these
  two lists, as 0159 gave Hives/Inspections/Queen Observations — out of
  scope here.
- **Full suite: 1,131 tests / 19,008 assertions, 0 failures, 0 errors**
  (only the usual third-party deprecations and notices). It was *not* one
  run: a single `phpunit` invocation over everything hit the background
  time limit at 37%, and four parallel chunks hit it again at 47–78% (the
  parallel runs slow each other down). The final result is seven
  separately-completed runs — the 109 non-Functional test files split into
  six chunks of about 170 tests by method count (167, 270, 167, 178, 173
  and 166 tests, run four at a time then two) plus Functional (10). Every
  file was in exactly one chunk; the chunk totals add up to the 1,131 the
  single run reported. The task was committed in `review` before this
  finished, and flipped to `done` in a follow-up commit.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]], [[0155-apiary-and-queen-list-filters]]
- Commits::
