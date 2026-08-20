---
type: task
tags: [hivelog/task]
status: done
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
- [x] New `InventoryPurchase`/`CalendarActionItemRequirement` records can
      no longer select a `discontinued` `InventoryItem`; new
      `CalendarActionProductYield` records can no longer select a
      `discontinued` `Product`.
- [x] Existing records referencing an item/product that is *later*
      marked discontinued are completely unaffected — they keep
      rendering and reporting normally (mirrors how disabling a
      `CalendarAction` doesn't touch its existing logs).
- [x] Editing an existing record that already references a now-discontinued
      item/product: the widget must still show the current value (not
      silently blank it out) even though it wouldn't be offered as a new
      choice — same UX core gives `options_select` widgets for a value
      outside the current allowed-values list. Confirmed this falls out
      of `EntityReferenceAutocompleteWidget::formElement()`'s own
      architecture for free: `#default_value` is set directly from the
      field's referenced entity, never re-queried through the selection
      handler, so status-filtering the query never touches it.
- [x] Kernel tests: a discontinued item doesn't appear in a fresh add
      form's autocomplete/options, an existing record referencing a
      since-discontinued item still saves and renders correctly on edit.
- [x] `ddev drush updb -y && ddev drush cr` clean — no schema change,
      widget/query-level filtering only.

## Implementation notes
- Sequenced directly on top of
  [[0041-scope-item-and-product-autocomplete-to-current-apiary]] as that
  task's own notes suggested — the same `ApiaryScopedSelection` plugin
  now carries an unconditional `status <> 'discontinued'` condition in
  `buildEntityQuery()`, alongside its existing (optional) apiary
  condition. No new files needed.
- One behavior change to `ApiaryScopedAutocompleteTrait::scopeAutocompleteToApiary()`:
  it used to no-op entirely when no apiary id was known (standalone add
  forms), leaving the widget on plain `default` with no filtering at
  all. Since status filtering must apply even without an apiary in
  scope, the trait now always applies the custom selection handler; the
  apiary condition inside `ApiaryScopedSelection` is still skipped when
  no `apiary_id` is set. Updated
  `ApiaryScopedAutocompleteTest::testStandaloneAddFormWithNoApiaryStaysUnfiltered`
  (renamed …`StaysApiaryUnfiltered`) to match: handler is now always
  `default:hivelog_apiary_scoped`, not `default`.
- Key files: `src/Plugin/EntityReferenceSelection/ApiaryScopedSelection.php`,
  `src/Form/ApiaryScopedAutocompleteTrait.php`.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 461 tests, 0 failures/errors (up from 452
  before this task).
- End-to-end smoke test via `drush php:eval`: an active and a
  discontinued item in the same apiary; confirmed the add form's real
  selection handler offers the active item and excludes the
  discontinued one.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: 0b99f1a (status filter on `ApiaryScopedSelection`, trait
  behavior change, tests)
