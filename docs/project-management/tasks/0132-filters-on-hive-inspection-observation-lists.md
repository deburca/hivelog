---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] Each filter form works with **or without** a parent. With no
      parent, Reset links to the current route (the collection) with the
      query string cleared. Embedded behaviour is unchanged.
- [ ] Filter extraction and query application move out of the two
      controllers into one reusable place per form (e.g. static
      `extract()` / `apply(QueryInterface $query, array $filters)`
      methods on the form class, or a small `hivelog.list_filters`
      service). `ApiaryController` / `HiveController` call the moved
      code; their copies are deleted.
- [ ] `HiveListBuilder`, `HiveInspectionListBuilder` and
      `QueenObservationListBuilder` render the form above the table and
      apply the filters in the entity query (`getEntityIds()`), **before**
      paging and before [[0124-list-page-row-access-filter]]'s row
      access filter. Paged results then reflect the filter, and pager
      links keep the filter query string.
- [ ] Same fields, labels, option lists, query-string keys (including
      the `obs_` prefix) and Filter / Reset button styling as the
      embedded versions. A filtered URL means the same thing on both
      pages.
- [ ] Empty state distinguishes "no records yet" from "no records match
      these filters", mirroring the embedded lists if they already do.
- [ ] Kernel tests: for each of the three lists, a filter narrows the
      rows, Reset targets the collection route, and the embedded
      versions still pass their existing tests unchanged.
- [ ] Verified live on `cms2`: each list filtered by at least one field,
      then Reset.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Depends on [[0126-unified-list-page-base]] so the form slots into the
  shared list-page render (e.g. a `getFilterForm()` hook method on
  `HivelogListBuilder`) instead of each builder overriding `render()`
  again. Calendar Actions' existing filter form
  (`HivelogCalendarActionsFilterForm`) should end up on the same hook.
- Out of scope, possible follow-up: filters that only make sense on the
  cross-apiary lists, such as an **Apiary** filter on `/hivelog/hives`
  or an Apiary / **Hive** filter on the inspections and observations
  lists. The decision was "the same filters", so none are added here.
- `hiveIdsForActiveQueenBreed()` (breed is on the active queen, not the
  hive; see AGENTS.md "Content entities") must move with
  `applyHiveFilters()`.
- Key files: `src/Form/HivelogHiveFilterForm.php`,
  `src/Form/HivelogInspectionFilterForm.php`,
  `src/Form/HivelogQueenObservationFilterForm.php`,
  `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php`, `src/HiveListBuilder.php`,
  `src/HiveInspectionListBuilder.php`,
  `src/QueenObservationListBuilder.php`, `src/HivelogListBuilder.php`.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0126-unified-list-page-base]],
  [[0124-list-page-row-access-filter]],
  [[0130-shared-calendar-checklist-helpers]] (the same "move controller
  helpers somewhere reusable" pattern)
- Commits::
