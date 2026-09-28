---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `HivelogInventoryItemFilterForm` (new): **Category**, **Item
      Type** (Consumable/Durable) and **Status** (selects, from
      `inventory_item`'s own field `allowed_values`), plus **Name
      contains** (text, `LIKE`).
- [ ] `HivelogInventoryPurchaseFilterForm` (new): **Purchase Date**
      (From / To range on `purchase_date`, matching
      `HivelogInspectionFilterForm`'s own date-range shape — a purchase
      ledger's primary axis is *when*, not a status enum) and
      **Supplier** (text, `LIKE`).
- [ ] `HivelogProductFilterForm` (new): **Status** (select) and **Name
      contains** (text, `LIKE`).
- [ ] `InventoryItemListBuilder` / `InventoryPurchaseListBuilder` /
      `ProductListBuilder` each override `getFilterForm()` /
      `applyFilters()` / `hasActiveFilters()` per the established
      pattern.
- [ ] Kernel tests: for each of the three lists, at least one filter
      narrows the rows, Reset clears the query string, empty-state
      message distinguishes "no records yet" from "no records match."
- [ ] Verified live on `cms2`: each of the three lists filtered by at
      least one field, then Reset.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Form/HivelogInventoryItemFilterForm.php` (new),
  `src/Form/HivelogInventoryPurchaseFilterForm.php` (new),
  `src/Form/HivelogProductFilterForm.php` (new),
  `src/InventoryItemListBuilder.php`, `src/InventoryPurchaseListBuilder.php`,
  `src/ProductListBuilder.php`, a new kernel test file.
- No entity schema change → **no update hook required**.
- For the date-range shape, follow `HivelogInspectionFilterForm`'s own
  `date_from`/`date_to` field naming and query-condition pattern
  exactly (a `BETWEEN`-style pair of `>=`/`<=` conditions), not a fresh
  design.

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]],
  [[0155-apiary-and-queen-list-filters]]
- Commits::
