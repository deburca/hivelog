---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0043-hide-discontinued-items-and-products-from-selection
release:
depends-on:
blocked-by:
---
# Task: Hide discontinued items/products from new selection

## Context
Another open question carried over from
[[inventory-tracking-and-depreciation]], never resolved:
`InventoryItem.status`/`Product.status` support `discontinued`, but
nothing actually hides a discontinued item/product from being picked in
a new `InventoryPurchase`, `CalendarActionItemRequirement`, or
`CalendarActionProductYield`. `CalendarAction.enabled` already has the
right precedent to copy: hidden going forward, existing references
untouched.

## Acceptance criteria
- [ ] New `InventoryPurchase`/`CalendarActionItemRequirement` records can
      no longer select a `discontinued` `InventoryItem`; new
      `CalendarActionProductYield` records can no longer select a
      `discontinued` `Product`.
- [ ] Existing records referencing an item/product that is *later*
      marked discontinued are completely unaffected — they keep
      rendering and reporting normally (mirrors how disabling a
      `CalendarAction` doesn't touch its existing logs).
- [ ] Editing an existing record that already references a now-discontinued
      item/product: the widget must still show the current value (not
      silently blank it out) even though it wouldn't be offered as a new
      choice — same UX core gives `options_select` widgets for a value
      outside the current allowed-values list.
- [ ] Kernel tests: a discontinued item doesn't appear in a fresh add
      form's autocomplete/options, an existing record referencing a
      since-discontinued item still saves and renders correctly on edit.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Form/InventoryPurchaseForm.php`,
  `src/Form/CalendarActionItemRequirementForm.php`,
  `src/Form/CalendarActionProductYieldForm.php` (widget-level filtering).
- Likely overlaps with [[0041-scope-item-and-product-autocomplete-to-current-apiary]]'s
  selection-handler work — both filter the same three widgets — worth
  sequencing or combining the two if 0041's chosen mechanism (custom
  selection handler vs. `handler_settings`) makes it natural to add a
  status filter at the same time.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
