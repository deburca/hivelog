---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] A definition of "low" — a fixed threshold field on `InventoryItem`
      (e.g. `low_stock_threshold`, optional decimal, only meaningful for
      consumables) is the most flexible option (different items need
      different thresholds — 2kg of sugar left might be fine, 2 jars
      left might not); a single module-wide constant is simpler but
      almost certainly wrong for some items. Confirm which before
      implementing — this task's biggest open design question.
- [ ] The apiary page's embedded Inventory table (or the Products/
      Inventory heading) surfaces which items are at/below their
      threshold — exact placement (inline row highlight vs. a
      "Low Stock" summary line in the heading) is an implementation
      decision, not pre-specified here.
- [ ] Items/products with no threshold set (or durable items, which have
      no stock-on-hand concept at all) never trigger a warning.
- [ ] Kernel tests: an item below threshold is flagged, one above is not,
      one with no threshold set is never flagged, durable items are
      never considered.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php` (new field +
  `isLowStock(): bool` or similar), `src/Controller/ApiaryController.php`
  (surfacing the warning on the embedded table).
- Follow the field-definition and `preSave()`/computed-method
  conventions already established by `InventoryItem::getStockOnHand()`/
  `getAnnualDepreciation()` — a derived fact, not stored state, exactly
  as the rest of this module already does it.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
