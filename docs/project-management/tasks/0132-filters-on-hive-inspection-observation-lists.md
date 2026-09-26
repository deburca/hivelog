---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0132-filters-on-hive-inspection-observation-lists
release:
depends-on: ["[[0126-unified-list-page-base]]"]
blocked-by:
---
# Task: Same filters on the full Hives, Inspections and Queen Observations lists

## Context
User decision, 2026-09-23, answering the open question raised by the
page-structure review ([[page-structure-consistency]]): the full-page
lists should get the same filters their embedded versions already have.

| Full list | Embedded version today | Filter form | Filter fields |
|---|---|---|---|
| `/hivelog/hives` | Apiary page, Hives section | `HivelogHiveFilterForm` | status, breed (active queen's), temperament, name |
| `/hivelog/inspections` | Hive page, Inspections | `HivelogInspectionFilterForm` | date from / to, queen seen, brood pattern, … |
| `/hivelog/queen-observations` | Hive page, Queen Observations | `HivelogQueenObservationFilterForm` | date from / to (`obs_`-prefixed), health, … |

Today the three full-page lists have no filters. All three forms are
tied to their parent page:
- `buildForm()` takes a required-in-practice `Apiary` / `Hive`
  argument. The Reset button links to `entity.apiary.canonical` /
  `entity.hive.canonical` and is only built when that parent is set.
- The query logic lives in the parent controllers as protected methods:
  `ApiaryController::extractHiveFilters()` / `applyHiveFilters()` /
  `hiveIdsForActiveQueenBreed()` / `escapeLike()`, and
  `HiveController::extractInspectionFilters()` /
  `applyInspectionFilters()` / `extractObservationFilters()` /
  `applyObservationFilters()`. List builders can't reuse them.

## Acceptance criteria
- [x] Each filter form works with **or without** a parent. With no
      parent, Reset links to the current route (the collection) with the
      query string cleared. Embedded behaviour is unchanged.
- [x] Filter extraction and query application move out of the two
      controllers into one reusable place per form (e.g. static
      `extract()` / `apply(QueryInterface $query, array $filters)`
      methods on the form class, or a small `hivelog.list_filters`
      service). `ApiaryController` / `HiveController` call the moved
      code; their copies are deleted.
- [x] `HiveListBuilder`, `HiveInspectionListBuilder` and
      `QueenObservationListBuilder` render the form above the table and
      apply the filters in the entity query (`getEntityIds()`), **before**
      paging and before [[0124-list-page-row-access-filter]]'s row
      access filter. Paged results then reflect the filter, and pager
      links keep the filter query string.
- [x] Same fields, labels, option lists, query-string keys (including
      the `obs_` prefix) and Filter / Reset button styling as the
      embedded versions. A filtered URL means the same thing on both
      pages.
- [x] Empty state distinguishes "no records yet" from "no records match
      these filters", mirroring the embedded lists if they already do.
- [x] Kernel tests: for each of the three lists, a filter narrows the
      rows, Reset targets the collection route, and the embedded
      versions still pass their existing tests unchanged.
- [x] Verified live on `cms2`: each list filtered by at least one field,
      then Reset.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **`HivelogListBuilder` gained three small hooks** (default no-op/`[]`,
  matching `getHeadingActions()`'s existing pattern): `getFilterForm()`
  (rendered above the table, weight -80), `applyFilters(QueryInterface
  $query)` (called from `load()` before `execute()`, so filtering
  happens before paging and before task 0124's row-access filter, per
  this task's own ordering requirement), and `hasActiveFilters()` (for
  the empty-state message). Every *other* list builder (Apiary, Queen,
  Product, …) is unaffected — the defaults reproduce exactly what
  `render()` did before this task.
- **`extract()` / `apply()` became `public static` methods on the three
  filter form classes themselves** (not a separate service) — the
  option the task text listed first, and the simpler of the two since
  there's no shared state or DI need between the three forms. Both
  controllers' own copies (`extractHiveFilters()`/`applyHiveFilters()`/
  `hiveIdsForActiveQueenBreed()` on `ApiaryController`;
  `extract`/`applyInspectionFilters()` and
  `extract`/`applyObservationFilters()` on `HiveController`) are
  deleted; both controllers and the three new list-builder overrides
  now call the same static methods. `escapeLike()` stays on
  `ApiaryController` (moved only the copy the hive filter used) since
  the calendar-actions filter — not in this task's scope — still needs
  its own.
- **`buildForm()` itself now calls its own class's `extract()`** to set
  every field's `#default_value`, instead of reading `$request->query`
  ad hoc per field as before. Not required by any AC, but it closes off
  a class of bug the old code was one edit away from: extraction and
  defaults could silently drift apart since they were two independent
  copies of "read this query key." Now there's exactly one.
- **Reset without a parent uses `Url::fromRoute('<current>')`**, Drupal's
  pseudo-route that resolves to the current route match with its query
  string dropped — no route name needs passing in, and it's already
  correct for whichever of the three collection routes is live. Reset
  is now unconditionally built (previously `if ($apiary)`/`if ($hive)`
  guarded it away entirely with no parent).
- **`<current>` needed a real route match to resolve in kernel tests**,
  not just a bare pushed `Request` — `RouteProcessorCurrent` reads the
  `current_route_match` service, which in turn reads route attributes
  off the current request, and a `Request::create()` alone carries
  none. `FullListFilterTest::pushRoutedRequest()` runs the request
  through `\Drupal::service('router')->matchRequest($request)` first
  and merges the result onto the request's attributes before pushing —
  the missing step is what made the very first version of this test
  resolve Reset to `/` (the `<current>` processor's own no-route
  fallback) instead of the real collection path.
- **The table's own `#cache.contexts` gained `url.query_args`** whenever
  a filter form is present — the filter form already declared this
  cache context on itself, but the table is a sibling render element,
  not nested inside the form, so it needed its own copy or a page-level
  cache of unfiltered results could serve a filtered URL its unfiltered
  rows.
- **Verification**: `EmbeddedTableFilterPaginationTest` (pre-existing,
  15 tests covering the embedded tables) still green unchanged, proving
  the moved extract/apply logic behaves identically. New
  `FullListFilterTest` (4 tests) covers all three full lists: a filter
  narrows rows, Reset clears the query string back to the bare
  collection path, and the empty-state message switches between
  "There are no hives yet." and "No hives match the current filters."
  Full `hivelog` suite: 706 tests, 12,053 assertions, only the 3
  pre-existing, already-documented, unrelated `DashboardTest`
  Functional errors. phpstan baseline regenerated (still 436 total —
  the count moved between files as expected, none net-new). Verified
  live on `cms2`: `/hivelog/hives?temperament=calm` correctly narrowed
  10 hives to the 4 with that temperament with a working Reset back to
  `/hivelog/hives`; `/hivelog/inspections?queen_seen=1` and
  `/hivelog/queen-observations?obs_health=good` both narrowed
  correctly with the same Reset behaviour.
- Out of scope, possible follow-up: filters that only make sense on the
  cross-apiary lists, such as an **Apiary** filter on `/hivelog/hives`
  or an Apiary / **Hive** filter on the inspections and observations
  lists. The decision was "the same filters", so none are added here.
- Out of scope: Calendar Actions' existing filter form
  (`HivelogCalendarActionsFilterForm`) is not a `HivelogListBuilder`
  subclass — its collection page is controller-built
  (`CalendarActionController::collection()`) — so putting it on the
  same `getFilterForm()` hook (as this task's own notes once suggested)
  isn't applicable without first converting that page to a list
  builder, which is [[0130-shared-calendar-checklist-helpers]]'s "move
  controller helpers somewhere reusable" territory, not this task's.
- Key files: `src/HivelogListBuilder.php` (new
  `getFilterForm()`/`applyFilters()`/`hasActiveFilters()` hooks),
  `src/Form/HivelogHiveFilterForm.php`,
  `src/Form/HivelogInspectionFilterForm.php`,
  `src/Form/HivelogQueenObservationFilterForm.php` (static
  `extract()`/`apply()`, unconditional Reset), `src/HiveListBuilder.php`,
  `src/HiveInspectionListBuilder.php`,
  `src/QueenObservationListBuilder.php` (filter hook overrides),
  `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php` (dead extract/apply methods
  removed), new `tests/src/Kernel/FullListFilterTest.php`,
  `phpstan-baseline.neon` (regenerated).

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0126-unified-list-page-base]],
  [[0124-list-page-row-access-filter]],
  [[0130-shared-calendar-checklist-helpers]] (the same "move controller
  helpers somewhere reusable" pattern)
- Commits::
