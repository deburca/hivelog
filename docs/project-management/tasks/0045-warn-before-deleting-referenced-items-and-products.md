---
type: task
tags: [hivelog/task]
status: done
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
- [x] `InventoryItemDeleteForm`/`ProductDeleteForm` show an explicit
      warning (count of affected historical records, at minimum) when
      the item/product being deleted has any `InventoryUsage`/
      `HarvestYield`/`InventoryPurchase` rows referencing it — the
      delete itself still proceeds if confirmed; this is a warning, not
      a hard block (durable catalog cleanup is a legitimate need, and
      blocking it entirely would just push users toward deleting via
      `drush`/API instead).
- [x] No warning shown for an item/product with zero historical
      references — the common case (a mistakenly created, never-used
      entry) stays a single-step delete with no added friction.
- [x] Kernel tests: the warning text appears when historical references
      exist, is absent when they don't, and deletion still succeeds
      either way once confirmed.

## Implementation notes
- Key files: `src/Form/InventoryItemDeleteForm.php`,
  `src/Form/ProductDeleteForm.php` — both override `getDescription()`
  to append the reference-count warning (via `formatPlural()`, reusing
  the existing "Unknown item"/"Unknown product" fallback-label wording
  already used elsewhere in the module, e.g.
  `InventoryPurchase::label()`), querying `inventory_purchase` +
  `inventory_usage` (for `InventoryItem`) and `harvest_yield` (for
  `Product`) with `accessCheck(FALSE)->count()->execute()`.
- Kept it a single warning appended to the existing confirm form's
  description, not a new intermediate step — matches how Drupal's own
  core delete confirmations handle "N nodes reference this term" style
  warnings, as the task suggested.
- Used `$this->entityTypeManager` (already injected on `EntityForm`, the
  base class both delete forms descend from) rather than a static
  `\Drupal::entityTypeManager()` call.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 461 tests, 0 failures/errors.
- New kernel tests in `InventoryItemTest.php`/`ProductTest.php`: no
  warning for a never-referenced item/product, correct warning text
  (including the reference count and "Unknown item"/"Unknown product"
  wording) when a purchase/usage or harvest-yield record exists, and
  deletion still succeeding afterward.
- End-to-end smoke test via `drush php:eval` for both entity types:
  built the real delete form object via
  `entityTypeManager->getFormObject()` and confirmed
  `getDescription()`'s actual rendered text in both the no-reference and
  with-reference cases.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits:: e92ff11 (`getDescription()` overrides on both delete forms,
  tests)
