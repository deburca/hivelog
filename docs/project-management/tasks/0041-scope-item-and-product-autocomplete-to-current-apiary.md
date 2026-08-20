---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0041-scope-item-and-product-autocomplete-to-current-apiary
release:
depends-on:
blocked-by:
---
# Task: Scope item/product autocomplete widgets to the current apiary

## Context
`InventoryPurchase.item`, `CalendarActionItemRequirement.item`, and
`CalendarActionProductYield.product` all use a plain
`entity_reference_autocomplete` widget with no `handler_settings`
filtering. A beekeeper with more than one apiary — exactly the users
per-apiary scoping (ADR-0027's confirmed decision) was built for — gets
autocomplete suggestions spanning every item/product across every apiary
they can see, not just the one they're on. The same-apiary guard only
fires in `preSave()`/`validateForm()` at submit time, as a generic error
— a silent trap rather than a filtered picker.

## Acceptance criteria
- [x] Each of the three fields' `entity_reference_autocomplete` widget is
      scoped to the current apiary at render time. Confirmed
      `handler_settings.target_bundles` doesn't apply — `InventoryItem`/
      `Product` aren't bundled entities — so a custom
      `EntityReferenceSelection` plugin was needed. Went with
      form-build-time scoping (based on whatever apiary/calendar_action
      value the entity has when the form renders), not a live
      AJAX-dependent widget: verified via code archaeology that
      `apiary`/`calendar_action` are always either pre-filled (the
      apiary/calendar-action-scoped add routes, the normal navigation
      path) or already saved (edit forms) — a live-reactive widget would
      have added real complexity for a case (user changes the apiary
      dropdown mid-form) that doesn't actually occur in the shipped UI.
- [x] The existing `preSave()`/`validateForm()` same-apiary guards on all
      three entities stay in place unchanged — this task narrows what the
      widget *offers*, it doesn't replace the guard that protects
      programmatic creation too.
- [x] Kernel test: `ApiaryScopedAutocompleteTest` builds the add form for
      each of the three entities within a specific apiary context and
      drives the widget's own `#selection_handler`/`#selection_settings`
      through the real selection plugin manager (the exact mechanism
      `EntityAutocompleteMatcher` uses in a live request) — proving what
      an actual autocomplete request would return, not just asserting on
      form structure. A fourth test confirms the standalone add-form
      route (no apiary pre-filled) correctly stays unfiltered.
- [x] `ddev drush cr` clean — no schema change, as expected for
      widget-level config only.

## Implementation notes
- Key files: `src/Plugin/EntityReferenceSelection/ApiaryScopedSelection.php`
  (new — one generic selection plugin, `id: "default:hivelog_apiary_scoped"`,
  covering both `inventory_item` and `product` since both carry a direct
  `apiary` reference field), `src/Form/ApiaryScopedAutocompleteTrait.php`
  (new — the shared widget-override helper the task suggested), and one
  `form()` override each in `InventoryPurchaseForm.php`,
  `CalendarActionItemRequirementForm.php`,
  `CalendarActionProductYieldForm.php`.
- The actual widget-override path took some archaeology: a single-value
  `entity_reference_autocomplete` field's real form structure is
  `$form[$field_name]['widget'][0]['target_id']` (not `$form[$field_name]['widget'][0]`
  directly, and not `$form[$field_name]` directly either) — confirmed by
  dumping a real rendered form's array keys via `drush php:eval` before
  writing any override code, rather than guessing.
- `ContentEntityForm`-based forms that render an `entity_autocomplete`
  widget need `$this->installConfig(['system'])` in kernel test `setUp()`
  even when the entity itself has no config dependency — the sibling
  `purchase_date` datetime widget on `InventoryPurchaseForm` needs
  `system.date` format config to render at all, and fails with a null
  pointer otherwise. Caught by `ApiaryScopedAutocompleteTest` itself (the
  first test in this project to actually render these three forms, not
  just their entities) — not a regression in any pre-existing test.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 447 tests, 0 failures/errors (up from 443
  before this task).
- End-to-end smoke test via `drush php:eval`: two apiaries, each with its
  own inventory item of the same shape; confirmed
  `ApiaryScopedSelection::getReferenceableEntities()` returns only the
  current apiary's item for `InventoryPurchase`, `CalendarActionItemRequirement`,
  and `CalendarActionProductYield`'s forms, and that the standalone
  no-apiary-known add form correctly still offers both — then rendered
  `InventoryPurchaseController::addForm()` (the real controller, not a
  test double) to confirm the whole page still builds without error.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: 5b1cf94 (selection plugin, shared trait, wiring into all
  three forms, tests)
