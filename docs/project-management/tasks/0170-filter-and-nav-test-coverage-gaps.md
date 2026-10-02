---
type: task
tags: [hivelog/task]
status: done
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
- [x] Full-list render tests (filter applied, Reset, empty-filtered state)
      for Apiaries, Queens, Inventory Items, Inventory Purchases, Products,
      AI Provider Configs, Sensor Devices and API Clients — in the owning
      module's own test directory for the submodule ones.
- [x] A test that the pager links on a full list page carry the active
      filter parameters, with more rows than one page (`$limit`).
- [x] A test on the nav render array asserting hub/child structure and
      active-state cascade (`has-active-child`) for a child route, if not
      already covered — check `HivelogAppNavBuilder` tests first and add only
      what's missing.
- [x] No new Functional tests (advisory in CI); kernel/unit only.
- [x] Count of new tests and the full-suite total recorded in the
      implementation notes.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Follow the existing `FullListFilterTest` and `EmbeddedTableFilterPaginationTest`
  fixture patterns. Reuse their helpers rather than adding a new base class.
- Coordinate with [[0168-action-log-list-filters]] and
  [[0169-filter-form-date-range-validation]], which add their own tests —
  this task covers only the existing gaps.

## Implementation notes (as built)
- **The task's premise was only partly right**, so I audited before writing
  anything. Its claim that the filters on the Apiary, Queen, Inventory,
  Product and submodule lists were each tested "one level down" and never
  through the list page's `render()` was wrong: every one of those tests
  already drives `getListBuilder()->render()`. Per list, what actually
  existed before this task:

  | List | Filter applied | Reset | Empty-filtered state |
  |---|---|---|---|
  | Apiaries | yes | yes | yes |
  | Queens | yes | yes | **no** |
  | Hives | yes | yes | yes |
  | Inspections | yes | yes | **no** |
  | Queen Observations | yes | yes | **no** |
  | Inventory Items | yes | yes | yes |
  | Inventory Purchases | yes | yes | **no** |
  | Products | yes | yes | **no** |
  | AI Provider Configs, Sensor Devices, API Clients | yes | yes | yes |
  | Hive / Apiary Action Logs (task 0168) | yes | yes | yes |

  So the "full-list render tests for all eight lists" criterion was
  satisfied for filter and Reset already. The real gaps were the five
  empty-filtered states above and — more important — pagination.
- **Added, 16 tests:**
  - 5 empty-state tests (Queens in `ApiaryQueenFilterTest`; Inspections and
    Queen Observations in `FullListFilterTest`; Purchases and Products in
    `InventoryProductFilterTest`), each asserting "There are no …" with no
    filter and "… match the current filters" with a filter that matches
    nothing.
  - New `tests/src/Kernel/ListFilterPaginationTest.php`, 7 tests (Apiaries,
    Hives, Inspections, Queens, Inventory Items, Purchases, Products): five
    rows, three matching, page size forced to two via reflection. Page one
    must hold two rows, the rendered pager must carry the filter, and page
    two must hold only the remaining *matching* row.
  - 3 more of the same in the owning submodules' own test directories
    (Sensor Devices, AI Provider Configs, API Clients).
  - 1 nav test in nanoprobe's `AppNavItemsTest`: a Sensor Devices route
    marks the **Insights** hub `has-active-child` and its own link
    `is-active` / `aria-current="page"`, and does not mark the Apiaries hub.
- **Nav: the rest was already covered.** `HivelogAppNavBuilderTest` already
  asserts the hub/child structure, `has-active-child` and the
  `is-active` / `aria-current` cascade for the Apiaries hub across collection,
  canonical and edit routes. The only missing case was a hub whose children
  come from a submodule, which is the one test added. Per the task, I added
  only what was missing.
- Quirk worth knowing: the Queens pagination fixture filters by breed, not
  status, because saving an active queen demotes any other active queen on
  the same hive, which would reshuffle the fixture.
- Mutation check: stopping `HivelogListBuilder::load()` from applying
  filters (in the cms2 copy only) failed all 7 new core pagination tests
  (6 failures and 1 error) and the new API Clients one, plus its existing
  filter tests. I ran that mutation against the core and API Clients
  classes only, not the other two submodule ones, which use the same helper.
- No product code changed, so nothing to verify live on `cms2`. phpcs and
  phpstan clean; no baseline change.
- No new Functional tests, as the task said.
- **Full suite: 1,173 tests / 19,427 assertions, 0 failures, 0 errors**
  (1,157 before this task + the 16 new), only the usual third-party
  deprecations and notices. Run as seven separately-completed chunks (six
  balanced non-Functional chunks of 172–288 tests, then Functional), since a
  single invocation exceeds the background time limit. The task was
  committed in `review` before the run finished and flipped to `done` in a
  follow-up commit.

## Related
- Project:: [[collection-page-filter-coverage]], [[in-app-navigation-restructuring]] (both done; follow-up)
- Tasks:: [[0140-controller-and-form-test-gaps]], [[0168-action-log-list-filters]]
- Commits::
