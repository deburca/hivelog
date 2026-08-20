---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[honey-wax-propolis-yield-and-potential-income]]"
area: entity
created: 2026-08-20
branch: feature/0037-harvest-yield-and-action-log-reporting-integration
release:
depends-on: ["[[0036-calendar-action-product-yield-and-recipe-ui]]"]
blocked-by:
---
# Task: `HarvestYield` entity and action-log reporting integration

## Context
The "actual" half of yield tracking, per
[[0034-honey-wax-propolis-yield-and-potential-income]]: when a beekeeper
reports a `HiveActionLog`/`ApiaryActionLog` as `done`, this is where
products and quantities actually produced get recorded, pre-filled from
[[0036-calendar-action-product-yield-and-recipe-ui]]'s recipe but
editable. Mirrors
[[0031-inventory-usage-and-action-log-reporting-integration]] exactly —
same conditional-side-effect-on-save shape, same form, applied to yield
instead of usage.

## Acceptance criteria
- [ ] `src/Entity/HarvestYield.php` — base table
      `hivelog_harvest_yield`, entity keys (`id`, `uuid`, `owner` →
      `uid`); `label()` composed as `"@product — @quantity @unit"`. No
      `form`/`list_builder` handlers — no dedicated UI, rows are
      system-managed via the action-log forms, matching `InventoryUsage`.
- [ ] Fields: `product` (required entity_reference → `product`),
      `quantity` (required decimal, precision 10/scale 3),
      `hive_action_log` (optional entity_reference → `hive_action_log`),
      `apiary_action_log` (optional entity_reference →
      `apiary_action_log`), `unit_price_snapshot` (decimal, precision
      12/scale 4, view-only — auto-derived in `preSave()` from
      `product.expected_unit_price` at creation time, never
      recalculated), plus `uid`/`created`/`changed`.
- [ ] `preSave()` validation: exactly one of `hive_action_log` /
      `apiary_action_log` must be set (not both, not neither) — mirrors
      `InventoryUsage::preSave()`'s identical guard.
- [ ] `unit_price_snapshot` is only set `if ($this->isNew() ||
      $this->get('unit_price_snapshot')->isEmpty())` — proves a later
      change to `product.expected_unit_price` does not retroactively
      change an already-recorded yield's snapshot, matching
      `InventoryUsage.unit_cost_snapshot`'s immutability guarantee
      exactly.
- [ ] `HiveActionLogForm`/`ApiaryActionLogForm`: when the report's
      `status` is set to `done`, show the `calendar_action`'s
      `CalendarActionProductYield` rows as a pre-filled, editable list of
      product + quantity (defaulting to the recipe's quantity), and on
      save, create one `HarvestYield` row per non-zero-quantity line,
      linked to the newly-saved log. Implemented as a shared
      `HarvestYieldFormTrait`, structurally identical to
      `InventoryUsageFormTrait` — both traits are used together on the
      same two forms (a "done" harvest report can show inventory-usage
      fields for jars *and* yield fields for honey/wax on the same page).
- [ ] Editing an already-`done` log's yield rows: re-saving updates
      existing `HarvestYield` rows in place (not append-only), matching
      `InventoryUsage`'s resolved behaviour. Changing status away from
      `done` on an already-reported log deletes its previously recorded
      yield rows, matching `InventoryUsageFormTrait::syncInventoryUsage()`'s
      identical edge-case handling.
- [ ] `hivelog.permissions.yml`: seven permissions for `harvest yield`
      (view own/any, add, edit own/any, delete own/any).
- [ ] `hivelog_update_NNNN` installs the new entity type; added to
      `hivelog_uninstall()`'s cleanup list before `hive_action_log`/
      `apiary_action_log`/`product`, all of which it references.
- [ ] Kernel tests: CRUD, the exactly-one-of-hive/apiary-log guard,
      `unit_price_snapshot` derivation and its immutability after
      creation (even after `product.expected_unit_price` changes), the
      report-form pre-fill-from-recipe behaviour (both
      `HiveActionLogForm` and `ApiaryActionLogForm`), resaving updates in
      place, changing status away from `done` removes yield rows, and
      that inventory-usage and yield fields both appear correctly on the
      same "done" report form when a calendar action has both a
      requirement and a yield recipe.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/HarvestYield.php`,
  `src/HarvestYieldAccessControlHandler.php`,
  `src/Form/HarvestYieldFormTrait.php`, `src/Form/HiveActionLogForm.php`,
  `src/Form/ApiaryActionLogForm.php`, `hivelog.install`.
- Study `src/Form/InventoryUsageFormTrait.php` closely before starting —
  this task's trait should be a structural copy with `item`/`quantity`/
  `unit_cost_snapshot`/`consumable-filtered-requirements` renamed to
  `product`/`quantity`/`unit_price_snapshot`/`yield-recipe-rows`. The two
  traits are deliberately kept as parallel siblings rather than unified
  into one generic trait (see the parent project's open question on
  this) — consistent with how `HiveActionLog`/`ApiaryActionLog` are
  themselves parallel siblings rather than a shared polymorphic base.
- Both `InventoryUsageFormTrait::buildInventoryUsageFields()` and this
  task's `HarvestYieldFormTrait::buildYieldFields()` need distinct
  fieldset `#weight`s and field-name prefixes (`inventory_usage_*` vs.
  `harvest_yield_*`) so they can coexist on the same form without
  collision — a calendar action can have both a `CalendarActionItemRequirement`
  and a `CalendarActionProductYield` (jars needed *and* honey produced by
  the same harvest report).
- No unit-conversion concern here either — `quantity` is always in the
  referenced `Product.unit`.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits::
