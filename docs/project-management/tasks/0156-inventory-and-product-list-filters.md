---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[collection-page-filter-coverage]]"
area: routing
created: 2026-09-28
branch: feature/0156-inventory-and-product-list-filters
release:
depends-on: ["[[0155-apiary-and-queen-list-filters]]"]
blocked-by:
---
# Task: Filters on the Inventory Items, Inventory Purchases and Products lists

## Context
The second group of [[collection-page-filter-coverage]]'s seven
target pages — the three "catalog" entity types. Sequenced after
[[0155-apiary-and-queen-list-filters]] only so the pattern is
established once, not because of any real dependency between them.

## Acceptance criteria
- [x] `HivelogInventoryItemFilterForm` (new): **Category**, **Item
      Type** (Consumable/Durable) and **Status** (selects, from
      `inventory_item`'s own field `allowed_values`), plus **Name
      contains** (text, `LIKE`).
- [x] `HivelogInventoryPurchaseFilterForm` (new): **Purchase Date**
      (From / To range on `purchase_date`, matching
      `HivelogInspectionFilterForm`'s own date-range shape — a purchase
      ledger's primary axis is *when*, not a status enum) and
      **Supplier** (text, `LIKE`).
- [x] `HivelogProductFilterForm` (new): **Status** (select) and **Name
      contains** (text, `LIKE`).
- [x] `InventoryItemListBuilder` / `InventoryPurchaseListBuilder` /
      `ProductListBuilder` each override `getFilterForm()` /
      `applyFilters()` / `hasActiveFilters()` per the established
      pattern.
- [x] Kernel tests: for each of the three lists, at least one filter
      narrows the rows, Reset clears the query string, empty-state
      message distinguishes "no records yet" from "no records match."
- [x] Verified live on `cms2`: each of the three lists filtered by at
      least one field, then Reset.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Form/HivelogInventoryItemFilterForm.php` (new),
  `src/Form/HivelogInventoryPurchaseFilterForm.php` (new),
  `src/Form/HivelogProductFilterForm.php` (new),
  `src/InventoryItemListBuilder.php`, `src/InventoryPurchaseListBuilder.php`,
  `src/ProductListBuilder.php`, `tests/src/Kernel/InventoryProductFilterTest.php`
  (new, 5 tests).
- No entity schema change → **no update hook required**.
- For the date-range shape, followed `HivelogInspectionFilterForm`'s
  own `date_from`/`date_to` field naming and query-condition pattern
  exactly (a `BETWEEN`-style pair of `>=`/`<=` conditions), not a fresh
  design. `HivelogInventoryPurchaseFilterForm` has no
  `entity_field.manager` dependency — neither `purchase_date` nor
  `supplier` reads an `allowed_values` list — unlike the other two new
  forms, which both need it for their select options.
- phpstan: the three new `FormBase` subclasses each triggered the same
  `new.static` finding task 0155 already established the precedent
  for — `FormBase::create()` legitimately keeps `new static()` (late
  static binding for real subclassing), so the fix is baselining, not
  switching to `new self()`. Baseline regenerated 433 → 436 entries;
  diffed old vs. new to confirm only those three additions changed.
- Regression check before writing the new test: ran the four
  pre-existing suites that touch these three list builders directly
  (`InventoryItemTest`, `InventoryPurchaseTest`, `ProductTest`,
  `HivelogListBuilderPaginationTest`, `ListBuilderAccessFilterTest` —
  103 tests) with the new filter hooks wired in but before adding any
  new test of my own — all green, confirming the generic
  `getFilterForm()`/`applyFilters()`/`hasActiveFilters()` wiring
  introduced no regression in the unfiltered case.
- Live-verified on `cms2` (kbg site — the default `drupal-cms2.ddev.site`
  hostname in this multisite resolves to `vdg`, which doesn't have
  hivelog enabled; had to reissue the `drush uli` login link with
  `--uri=kragebaekgaard.ddev.site` and redo the throwaway fixtures
  against that site's own database):
  `/hivelog/inventory-items?item_type=durable` narrowed to the durable
  item only, Reset cleared the query string; `?name=ZZZNoMatch` showed
  the "no records match" empty state;
  `/hivelog/inventory-purchases?date_from=2026-05-01` excluded the
  earlier purchase; `/hivelog/products?status=active` excluded the
  discontinued product. Fixtures (one throwaway apiary + its inventory
  item/purchase/product children) deleted afterward; confirmed gone via
  an entity query.
- Full suite (all core + submodule Kernel/Unit/Functional dirs, per
  AGENTS.md's "CI Pipeline" discovery loops): 1,085 tests, 0 failures
  (up from 1,080 before this task). Only pre-existing third-party
  deprecation notices (Symfony `EventDispatcher::getSubscribedEvents()`,
  the `key` module's attribute-discovery deprecation) — both unrelated
  to this change and already present before it.
- Mid-verification hiccup (process note, not a code issue): an earlier
  full-suite run was killed prematurely after ~27 minutes on a
  mistaken "hung" diagnosis (low CPU time in `ps` while genuinely
  blocked on Functional/ChromeDriver HTTP round-trips, not actually
  stuck) — it had reached 854/1085 with zero failures when killed. Re-ran
  clean to completion rather than trusting the partial result.

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]],
  [[0155-apiary-and-queen-list-filters]]
- Commits::
