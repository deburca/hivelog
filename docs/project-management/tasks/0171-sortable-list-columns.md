---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: routing
created: 2026-10-02
branch: feature/0171-sortable-list-columns
release:
depends-on:
blocked-by:
---
# Task: Sortable columns on the full collection lists

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]]
(done). Every full collection page can now be narrowed with a filter form,
but not reordered: `HivelogListBuilder` sorts on a single fixed key
(`SORT_KEY`, `src/HivelogListBuilder.php:104`). A beekeeper filtering
Inspections by date still can't sort by weight, or Inventory Items by stock.
This is an enhancement rather than a defect, and some columns are computed
(stock on hand, empty weight) so can't sort in a database query.

## Acceptance criteria
- [ ] Propose which columns are sortable per list and which are not
      (computed columns excluded unless cheap to sort in PHP after loading).
      Confirm the proposal before building.
- [ ] Column headers for sortable columns render as links that toggle
      ascending/descending and carry `sort` and `order` in the query string,
      alongside existing filter parameters.
- [ ] Sort state survives filtering, Reset (Reset clears filters and sort),
      and pagination.
- [ ] Only whitelisted field names are accepted from the query string; an
      unknown value falls back to the default sort, never reaching the
      query.
- [ ] Accessible: `aria-sort` on the active column header.
- [ ] Implemented once in `HivelogListBuilder`, with per-builder opt-in of
      sortable columns — not copied per list.
- [ ] Kernel tests: each direction, unknown field, combined with a filter
      and with a pager.
- [ ] Verified live on `cms2`.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Needs an `hivelog:entity-table` SDC change if header cells can't currently
  render as links. Check the component's props first.
- If the proposal is large, consider an ADR before building.
- No update hook.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0126-unified-list-page-base]]
- Commits::
