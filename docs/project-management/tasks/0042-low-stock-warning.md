---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[inventory-and-yield-improvements]]"
area: routing
created: 2026-08-20
branch: feature/0042-low-stock-warning
release:
depends-on:
blocked-by:
---
# Task: Low-stock warning

## Context
Flagged as an open question back in
[[inventory-tracking-and-depreciation]] and never implemented, even
though `InventoryItem::getStockOnHand()` has existed since task 0029. A
beekeeper currently has no way to know they're low on jars/sugar/strips
without opening every item's own page and reading the number.

## Acceptance criteria
- [x] A definition of "low" — a fixed threshold field on `InventoryItem`
      (e.g. `low_stock_threshold`, optional decimal, only meaningful for
      consumables) is the most flexible option (different items need
      different thresholds — 2kg of sugar left might be fine, 2 jars
      left might not); a single module-wide constant is simpler but
      almost certainly wrong for some items. Confirm which before
      implementing — this task's biggest open design question. Went with
      the per-item field, as recommended.
- [x] The apiary page's embedded Inventory table (or the Products/
      Inventory heading) surfaces which items are at/below their
      threshold — exact placement (inline row highlight vs. a
      "Low Stock" summary line in the heading) is an implementation
      decision, not pre-specified here. Went with an inline "(Low Stock)"
      suffix appended to the Stock on Hand cell text, mirroring
      `HiveController::pendingActionTimingLabel()`'s established
      "@status (@timing)" inline-suffix pattern rather than a separate
      summary line.
- [x] Items/products with no threshold set (or durable items, which have
      no stock-on-hand concept at all) never trigger a warning.
- [x] Kernel tests: an item below threshold is flagged, one above is not,
      one with no threshold set is never flagged, durable items are
      never considered.
- [x] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php` (new
  `low_stock_threshold` base field, decimal precision 10/scale 3, plus
  `isLowStock(): bool` — inherits `getStockOnHand()`'s existing `NULL`
  guard for durable/unsaved items, so no separate `item_type` check was
  needed), `src/Form/InventoryItemForm.php` (field added to the
  "Overview" section), `src/Controller/InventoryItemController.php`
  (Stock on Hand section + `buildFieldValue()` decimal-formatting case),
  `src/Controller/ApiaryController.php` (embedded Inventory table's
  Stock on Hand cell), `hivelog.install`
  (`hivelog_update_10026()`, following the existing `geolocation`
  field-storage-migration pattern for `installFieldStorageDefinition()`).
- `isLowStock()` deliberately does not duplicate `getStockOnHand()`'s
  durable/unsaved guard — since that method already returns `NULL` for
  both cases, checking `$stock === NULL` after the threshold-empty check
  covers everything in one place.
- Rendering `ApiaryController::view()` directly (this task's new
  `testApiaryPageFlagsLowStockItem`) needs `hive`, `calendar_action`,
  `calendar_action_item_requirement`, `hive_action_log`,
  `apiary_action_log`, and `product` schema installed even though the
  test itself only cares about inventory items — matched
  `InventoryItemTest::setUp()`'s schema list to the established list
  already used by `ProductTest.php` (which renders the same controller
  method) to fix a `Base table ... doesn't exist` kernel-test failure
  caught before commit.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 452 tests, 0 failures/errors (matches the
  test count from before this task, since one new test file's worth of
  coverage — `InventoryItemTest`'s five new tests — was offset by no
  other change; net five tests added within that file).
- `ddev drush updb -y` applied `hivelog_update_10026` cleanly; `ddev
  drush cr` clean.
- End-to-end smoke test via `drush php:eval`: created a below-threshold
  item, an above-threshold item, a durable item with a threshold set,
  and a no-threshold item — confirmed `isLowStock()` returns `TRUE` only
  for the below-threshold item, and that both
  `InventoryItemController::view()` and `ApiaryController::view()`'s
  real rendered output contain the "(Low Stock)" text only for the
  flagged item's row/section.
- CI run 32402207086: success.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: 09ace81 (field, `isLowStock()`, UI wiring on both the item
  page and apiary page, install hook, tests)
