---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0049-asset-disposal-and-write-off-tracking
release:
depends-on:
blocked-by:
---
# Task: Asset disposal & write-off tracking

## Context
A durable `InventoryItem`, once purchased, is currently assumed usable
through (at least) its full `useful_life_years` depreciation window —
there's no "this frame set broke/was retired early" workflow. Explicitly
listed as out of scope in [[inventory-tracking-and-depreciation]].

## Acceptance criteria
- [x] A disposal event (date + optional reason) recordable against a
      durable `InventoryPurchase` — the natural attachment point, since
      depreciation is computed per-purchase, not per-item. Enforced as a
      hard invariant (both `preSave()` and `validateForm()`): a
      disposal date can only be set when the purchase's item is
      `durable`, and can't predate the purchase's own `purchase_date`.
- [x] `InventoryItem::getAnnualDepreciation()` stops counting a disposed
      purchase's contribution for years after its disposal date, even if
      still inside the original `useful_life_years` window — the actual
      point of this task; without it, disposal tracking would be
      informational only with no effect on the cost report. The
      disposal year itself still counts in full (whole-year accounting,
      matching the rest of the method's lack of partial-year
      proration) — only years strictly after it are zeroed.
- [x] Kernel test: a durable purchase disposed of mid-way through its
      useful-life window shows correct depreciation before disposal and
      zero after, contrasted with `InventoryEndToEndTest`'s existing
      full-window boundary test. Landed in `InventoryCostReportTest.php`
      instead (where `testDepreciationWindowBoundaries()` — the
      contrasted full-window test — actually lives).
- [x] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryPurchase.php` (new `disposal_date`/
  `disposal_reason` fields, both optional; `preSave()` invariants),
  `src/Entity/InventoryItem.php` (`getAnnualDepreciation()`'s
  disposal-year check), `src/Form/InventoryPurchaseForm.php` (new
  "Disposal" vertical-tab section; `validateForm()` UI-layer guard),
  `src/Controller/InventoryPurchaseController.php` (a "Disposal"
  section, shown only when a disposal date is actually set — mirrors
  `InventoryItemController`'s consumables-only Stock on Hand section
  pattern).
- `InventoryPurchaseForm::validateForm()`'s disposal check reads the
  submitted date values off a freshly built entity clone
  (`$this->buildEntity($form, $form_state)`) rather than
  `$form_state->getValue()` directly — at that point in the form
  lifecycle the datetime widget's raw form value is still a
  `DrupalDateTime` object, not yet the `'Y-m-d'` string
  `WidgetInterface::extractFormValues()` produces, and `buildEntity()`
  is what actually runs that massaging. Confirmed via `drush php:eval`
  before writing the code, following the same "verify the exact shape
  empirically" practice task 0041 established.
- Deliberately did not touch `CalendarActionItemRequirement`-style
  checklist reminders (the task's own "consider… not the primary
  point" note) — left as a possible future follow-up, not scoped here.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 465 tests, 0 failures/errors (up from 461
  before this task).
- End-to-end smoke test via `drush php:eval`: a durable item purchased
  in 2024 (3-year useful life) disposed of mid-2025 — confirmed 2024
  and 2025 both still depreciate in full, 2026 (still inside the
  original window) depreciates zero; confirmed the view page renders
  both new fields; confirmed a consumable-item purchase with a
  disposal date is rejected with the expected exception; confirmed the
  add form's real rendered output includes the new "Disposal" section
  with both fields correctly grouped.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: f67cd47 (`disposal_date`/`disposal_reason` fields,
  `getAnnualDepreciation()` disposal-year check, form/controller
  wiring, tests)
