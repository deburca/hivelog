---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0045-warn-before-deleting-referenced-items-and-products
release:
depends-on:
blocked-by:
---
# Task: Warn before deleting items/products with historical usage

## Context
Deleting an `InventoryItem` or `Product` that already has
`InventoryUsage`/`HarvestYield`/`InventoryPurchase` rows referencing it
proceeds with no warning today. The historical rows survive (entity
reference fields just go empty), and existing report/list code already
has a graceful `"Unknown item"`/`"Unknown product"` fallback label for
this case — so nothing crashes — but a beekeeper cleaning up a
mislabeled catalog entry has no way to know they're about to blank out
the item/product name on every past report and log line that used it.

## Acceptance criteria
- [ ] `InventoryItemDeleteForm`/`ProductDeleteForm` show an explicit
      warning (count of affected historical records, at minimum) when
      the item/product being deleted has any `InventoryUsage`/
      `HarvestYield`/`InventoryPurchase` rows referencing it — the
      delete itself still proceeds if confirmed; this is a warning, not
      a hard block (durable catalog cleanup is a legitimate need, and
      blocking it entirely would just push users toward deleting via
      `drush`/API instead).
- [ ] No warning shown for an item/product with zero historical
      references — the common case (a mistakenly created, never-used
      entry) stays a single-step delete with no added friction.
- [ ] Kernel tests: the warning text appears when historical references
      exist, is absent when they don't, and deletion still succeeds
      either way once confirmed.

## Implementation notes
- Key files: `src/Form/InventoryItemDeleteForm.php`,
  `src/Form/ProductDeleteForm.php` — override `getDescription()` (or
  equivalent) to append the reference-count warning, querying
  `inventory_usage`/`inventory_purchase` (for `InventoryItem`) and
  `harvest_yield` (for `Product`) with `accessCheck(FALSE)->count()`.
- Keep it a warning appended to the existing confirm form, not a new
  intermediate step — matches how Drupal's own core delete confirmations
  handle "N nodes reference this term" style warnings.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
