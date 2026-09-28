---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-component-weight-tracking]]"
area: entity
created: 2026-09-28
branch: feature/0163-hive-component-entity-and-empty-weight
release:
depends-on: ["[[0162-inventory-item-weight-field]]"]
blocked-by:
---
# Task: `HiveComponent` entity, embedded UI, and `Hive::getEmptyWeightKg()`

## Context
The core of [[hive-component-weight-tracking]]
([[0106-hive-component-weight-tracking]] Decisions §2–5 + the
2026-09-28 Amendment): a new entity recording which `InventoryItem`s
(and how many of each) a `Hive` is physically built from, a computed
empty-weight sum, the Hive page's own UI to manage it, and — per the
Amendment — a hard stock-availability ceiling: a component can never
be assigned past how many units of that catalog item actually exist
(purchased minus already assigned to any hive), and the item picker
only offers items with at least one unit still available.

## Acceptance criteria
- [ ] New `HiveComponent` content entity
      (`src/Entity/HiveComponent.php`), mirroring
      `CalendarActionItemRequirement`'s exact shape: `hive`
      (entity_reference → hive, required), `item` (entity_reference →
      inventory_item, required), `quantity` (**integer**, not decimal
      — see ADR-0106 §2 for why this diverges from
      `CalendarActionItemRequirement.quantity`; `min: 1, default: 1`,
      required), plus `uid`/`created`/`changed`
      (`EntityOwnerInterface`/`EntityChangedInterface`, matching the
      analog).
- [ ] `preSave()` same-apiary guard: `hive.apiary` must equal
      `item.apiary`, throwing `\InvalidArgumentException` on
      mismatch — copy `CalendarActionItemRequirement::preSave()`'s
      exact logic and message shape. Re-checked in the add/edit
      form's own `validateForm()` too, same as the analog.
- [ ] Two new `InventoryItem` computed methods (Amendment):
      `getTotalPurchasedQuantity(): float` (sums
      `InventoryPurchase.quantity` for this item, any `item_type` —
      no such reusable total exists today) and
      `getAssignedToHivesQuantity(?int $excludeHiveComponentId =
      NULL): float` (sums `quantity` across every `HiveComponent` row
      referencing this item, across every hive, optionally excluding
      one row). A third, `getAvailableForHiveAssignmentQuantity(?int
      $excludeHiveComponentId = NULL): float`, is the first minus the
      second — always a real float, never `NULL`.
- [ ] `preSave()` stock-availability guard (Amendment): if `quantity`
      exceeds `item.getAvailableForHiveAssignmentQuantity($this->id())`,
      throw `\InvalidArgumentException` naming the shortfall. Re-checked
      in `HiveComponentForm::validateForm()` too — `setErrorByName()`
      naming how many units are actually available — same two-layer
      pattern as the same-apiary guard above, not a single-layer check.
- [ ] New selection plugin `default:hivelog_hive_component_item`
      (`src/Plugin/EntityReferenceSelection/HiveComponentAvailableItemSelection.php`,
      Amendment): extends `ApiaryScopedSelection` for its existing
      apiary/discontinued query scoping, overrides
      `getReferenceableEntities()` to post-filter out any item whose
      `getAvailableForHiveAssignmentQuantity()` is `<= 0` (a computed
      value, not expressible as a query `condition()`) and appends an
      "(N available)" suffix to each remaining option's label,
      matching `InventoryItemController::view()`'s existing "(Low
      Stock)" suffix idiom. **Do not add this filtering to the shared
      `ApiaryScopedSelection` plugin directly** — it's also used by
      `InventoryPurchaseForm`/`CalendarActionItemRequirementForm`/
      `CalendarActionProductYieldForm`, where a hive-assignment
      availability filter would be wrong. `HiveComponentForm` sets
      `#selection_settings['exclude_hive_component_id']` to the
      entity being edited's own id (`NULL`/omitted on the add form),
      mirroring how `apiary_id` is already passed today.
- [ ] Access control handler mirroring
      `CalendarActionItemRequirementAccessControlHandler`'s shape.
      Scoped add route `hivelog.hive_component.add` at
      `/hivelog/hive/{hive}/component/add`, requiring
      `_entity_access: 'hive.update'` +
      `_entity_create_access: 'hive_component'` — copy
      `hivelog.calendar_action_item_requirement.add`'s exact route
      shape. Standard edit/delete routes/forms for a single
      `HiveComponent` row.
- [ ] Update hook (next `hivelog_update_N` after task 0162's) installs
      the new entity type via `installEntityType()`, copying
      `hivelog_update_10021`'s (or any later same-shape hook's) exact
      pattern.
- [ ] Two new `HivelogDeleteDependencyRegistry` rows (ADR-0106 §4):
      CASCADE `hive` → `hive_component` (field `hive`); BLOCK
      `inventory_item` → `hive_component` (field `item`).
- [ ] `Hive::getEmptyWeightKg(): ?float` — sums `quantity ×
      item.weight_kg` across the hive's `HiveComponent` rows (query
      style matching `getActiveQueen()`/`getQueens()`). **Returns
      `NULL` if any referenced item has no `weight_kg` set** — not a
      partial sum. Returns `NULL` (not `0.0`) when the hive has no
      components at all, distinguishing "empty weight is genuinely
      zero" (impossible for a real hive) from "not yet composed."
- [ ] "Hive Components" section on the Hive canonical page
      (`src/Controller/HiveController.php`, weight ~6, between the
      entity-fields section at weight 5 and the weight histogram at
      weight 7): a table (item / quantity / unit weight / subtotal),
      an Add button (the scoped route above), per-row Edit/Delete
      (`hivelog:button-group`, matching every other embedded list in
      the module). Below the table: "Empty weight: X kg", or, when
      `getEmptyWeightKg()` is `NULL`, "Empty weight: incomplete — N
      of M components missing a weight" (count components whose
      `item.weight_kg` is unset).
- [ ] Kernel tests: `HiveComponent` CRUD, the same-apiary guard
      (create + form validation), `getEmptyWeightKg()`'s three cases
      (complete sum, `NULL` on a missing component weight, `NULL` on
      no components at all), the two delete-dependency rows (deleting
      a hive cascades its components; deleting a referenced inventory
      item is blocked while a `HiveComponent` still references it),
      the three new `InventoryItem` availability methods, the
      stock-availability guard (create at the limit succeeds; one
      over the limit fails both at `preSave()` and at
      `validateForm()`; editing a row's own quantity up to its prior
      value + remaining availability succeeds — the
      `excludeHiveComponentId` exemption), and the new selection
      plugin (an item with zero availability is excluded from
      results; one with some availability shows the "(N available)"
      suffix).
- [ ] Verified live on `cms2`: compose a real test hive from real
      catalog items (including at least one item with no weight set,
      to exercise the "incomplete" state), confirm the section and
      the empty-weight figure render correctly in both states; delete
      one component and confirm the sum updates; attempt to assign
      more units of an item than are actually purchased and confirm
      it's rejected with a clear message; confirm a fully-assigned
      item (zero remaining) no longer appears in the item picker for
      a *different* hive's new component, but editing its own
      existing row still offers its own already-assigned quantity as
      the ceiling, not zero.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Entity/HiveComponent.php` (new),
  `src/HiveComponentAccessControlHandler.php` (new),
  `src/Form/HiveComponentForm.php` (new, add/edit),
  `src/Form/HiveComponentDeleteForm.php` (new, or reuse the shared
  delete-form base per task 0127), `src/Entity/Hive.php`
  (`getEmptyWeightKg()`), `src/Entity/InventoryItem.php` (the three
  new availability methods), `src/Plugin/EntityReferenceSelection/HiveComponentAvailableItemSelection.php`
  (new), `src/Controller/HiveController.php` (new section),
  `hivelog.routing.yml`, `hivelog.install` (new update hook),
  `src/Delete/HivelogDeleteDependencyRegistry.php`.
- Schema change (new entity type) → **update hook required**.
- No standalone list builder / collection route for `HiveComponent`
  — same "managed only from its parent's page" precedent
  `CalendarActionItemRequirement` already established; do not add one
  speculatively.
- **The availability ceiling applies regardless of `item_type`**
  (Amendment) — it's computed from `InventoryPurchase` totals minus
  `HiveComponent` assignments, not from `getStockOnHand()` (which is
  consumable-only and would return `NULL` for the realistic
  `durable`-typed hive-component case). Do not attempt to reuse or
  branch on `getStockOnHand()` for this — it's a deliberately separate
  computation.
- **Availability is apiary-wide across hives, not hive-specific** —
  an item purchased for one apiary can be assigned to any hive in
  that same apiary (the existing same-apiary guard already ensures
  this), and its availability pool is shared/competed-for across all
  of that apiary's hives, not partitioned per hive.
- Consider whether `getTotalPurchasedQuantity()` is worth factoring
  the existing near-identical purchase-summing loops in
  `getStockOnHand()`/`getWeightedAverageUnitCost()`/
  `getAnnualDepreciation()` to reuse, **but do not refactor those
  existing methods as part of this task** unless it's trivial and
  risk-free — this task's scope is the new capability, not a cleanup
  of pre-existing, working, tested code.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0106-hive-component-weight-tracking]],
  [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0162-inventory-item-weight-field]],
  [[0164-net-colony-honey-weight-stat-tile]]
- Commits::
