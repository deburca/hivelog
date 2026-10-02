---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] New `HivelogHiveActionLogFilterForm` and
      `HivelogApiaryActionLogFilterForm` (static `extract()`/`apply()`, GET
      method, `hivelog-filter-form` class), matching the pattern in
      [[0155-apiary-and-queen-list-filters]].
- [ ] Filter fields proposed (confirm before building): status
      (pending / done / ignored), year, calendar action (select, scoped to
      the apiary where relevant), and for the hive log list, hive. Only
      fields already on the entities — no schema change.
- [ ] Both list builders wire the three hook methods; filter state lives in
      the query string; Reset targets `<current>`.
- [ ] Empty-state message distinguishes "no rows" from "no rows match your
      filters" (as `HivelogListBuilder` already does for the others).
- [ ] Filters survive pagination on these lists.
- [ ] Kernel tests per form, following `ApiaryQueenFilterTest` /
      `InventoryProductFilterTest`.
- [ ] Verified live on `cms2` with throwaway fixtures, cleaned up afterward.
- [ ] `collection-page-filter-coverage`'s project note gets a line recording
      that the two action-log lists were added after it closed, and that
      Calendar Actions remains excluded.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HiveActionLogListBuilder.php`,
  `src/ApiaryActionLogListBuilder.php`, new filter form classes under
  `src/Form/`.
- AGENTS.md's list-builder description may need a line if it enumerates
  which lists have filters.
- No update hook.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]], [[0155-apiary-and-queen-list-filters]]
- Commits::
