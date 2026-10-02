---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: tests
created: 2026-10-02
branch: feature/0170-filter-and-nav-test-coverage-gaps
release:
depends-on:
blocked-by:
---
# Task: Close the filter and nav test coverage gaps

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]] and
[[in-app-navigation-restructuring]] (both done).
- `FullListFilterTest` only exercises Hives, Inspections and Queen
  Observations. The Apiary, Queen, Inventory Item/Purchase, Product and
  submodule filters each have their own test, but not one that drives the
  full list page through the real list builder `render()`.
- Nothing asserts that filter parameters survive pagination on the full
  list pages. `EmbeddedTableFilterPaginationTest` covers the embedded
  tables only.
- The nav strip has no test for hover-less/touch behaviour, and
  `tests/src/Functional` has only Dashboard, CRUD journey and Permission
  Matrix tests. Functional tests are also advisory in CI.

## Acceptance criteria
- [ ] Full-list render tests (filter applied, Reset, empty-filtered state)
      for Apiaries, Queens, Inventory Items, Inventory Purchases, Products,
      AI Provider Configs, Sensor Devices and API Clients — in the owning
      module's own test directory for the submodule ones.
- [ ] A test that the pager links on a full list page carry the active
      filter parameters, with more rows than one page (`$limit`).
- [ ] A test on the nav render array asserting hub/child structure and
      active-state cascade (`has-active-child`) for a child route, if not
      already covered — check `HivelogAppNavBuilder` tests first and add only
      what's missing.
- [ ] No new Functional tests (advisory in CI); kernel/unit only.
- [ ] Count of new tests and the full-suite total recorded in the
      implementation notes.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Follow the existing `FullListFilterTest` and `EmbeddedTableFilterPaginationTest`
  fixture patterns. Reuse their helpers rather than adding a new base class.
- Coordinate with [[0168-action-log-list-filters]] and
  [[0169-filter-form-date-range-validation]], which add their own tests —
  this task covers only the existing gaps.

## Related
- Project:: [[collection-page-filter-coverage]], [[in-app-navigation-restructuring]] (both done; follow-up)
- Tasks:: [[0140-controller-and-form-test-gaps]], [[0168-action-log-list-filters]]
- Commits::
