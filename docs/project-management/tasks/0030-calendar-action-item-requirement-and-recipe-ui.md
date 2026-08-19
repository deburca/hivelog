---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-tracking-and-depreciation]]"
area: entity
created: 2026-08-19
branch: feature/0030-calendar-action-item-requirement-and-recipe-ui
release:
depends-on: ["[[0028-inventory-item-and-purchase-entities]]"]
blocked-by:
---
# Task: `CalendarActionItemRequirement` entity and recipe management UI

## Context
The "plan" half of inventory usage — lets a `CalendarAction` (e.g. "Varroa
Treatment (Spring)") declare which items and quantities it typically
requires, per [[0027-inventory-tracking-and-depreciation]]. This is what
[[0031-inventory-usage-and-action-log-reporting-integration]] pre-fills
its report form from.

## Acceptance criteria
- [ ] `src/Entity/CalendarActionItemRequirement.php` — base table
      `hivelog_calendar_action_item_requirement` (or a shorter machine
      name decided at implementation time — the entity ID doesn't need to
      match the class name exactly, e.g. `calendar_action_requirement`),
      entity keys (`id`, `uuid`, `owner` → `uid`).
- [ ] Fields: `calendar_action` (required entity_reference →
      `calendar_action`), `item` (required entity_reference →
      `inventory_item`), `quantity` (required decimal — per hive for a
      hive-scoped action, per apiary for an apiary-scoped one; scope is
      read from the parent `calendar_action`, not duplicated on this
      entity), plus `uid`/`created`/`changed`.
- [ ] `preSave()` guard: `item.apiary` must equal `calendar_action.apiary`
      — throw `\InvalidArgumentException`, mirroring
      `CalendarAction::preSave()`'s `week_end >= week_start` guard.
- [ ] Recipe management embedded on the `CalendarAction` canonical page
      (mirroring how `HiveController` embeds the Inspections table on the
      hive page): a "Required Items" table (item, quantity, unit) with
      Add/Edit/Delete, scoped to that calendar action.
- [ ] `CalendarActionForm` gains no new fields itself (requirements are
      managed on the canonical page, not the edit form, matching how
      `QueenObservation` rows are managed from the `Queen`/`Hive` pages
      rather than from `QueenForm`).
- [ ] `hivelog.permissions.yml`: `view own calendar action item
      requirement`, `view any …`, `add …`, `edit own …`, `edit any …`,
      `delete own …`, `delete any …` (six permissions, matching the
      existing pattern).
- [ ] `hivelog_update_NNNN` installs the new entity type; add it to
      `hivelog_uninstall()`'s cleanup list before `calendar_action`.
- [ ] Kernel tests: CRUD, the same-apiary guard, recipe table rendering
      on the calendar action canonical page, empty-state when a calendar
      action has no requirements yet.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/CalendarActionItemRequirement.php`,
  `src/CalendarActionItemRequirementListBuilder.php` (if a standalone
  collection page is wanted — likely low priority since the canonical use
  is the embedded table; could be deferred if the embedded UI alone is
  sufficient), `src/Controller/CalendarActionController.php` (add the
  embedded requirements table, mirroring
  `HiveController::buildInspectionsColumn()`'s shape), `hivelog.install`.
- Decide at implementation time whether `quantity`'s unit is implicitly
  the `InventoryItem.unit` (recommended — avoids a redundant field here)
  or needs its own display-only echo of the unit next to the number for
  clarity on the requirements table.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0003-code-defined-entity-schema]]
- Commits::
