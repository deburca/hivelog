---
type: task
tags: [hivelog/task]
status: done
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
- [x] `src/Entity/CalendarActionItemRequirement.php` — base table
      `hivelog_calendar_action_item_requirement` (kept matching the
      class name exactly, following the module's usual 1:1 convention),
      entity keys (`id`, `uuid`, `owner` → `uid`). No `collection` or
      `canonical` link — managed entirely from the embedded table.
- [x] Fields: `calendar_action` (required entity_reference →
      `calendar_action`), `item` (required entity_reference →
      `inventory_item`), `quantity` (required decimal, precision
      10/scale 3), plus `uid`/`created`/`changed`. `quantity`'s unit is
      implicitly the referenced item's `unit` (no redundant field here).
- [x] `preSave()` guard: `item.apiary` must equal `calendar_action.apiary`
      — throws, mirroring `CalendarAction::preSave()`'s
      `week_end >= week_start` guard; also enforced as a proper form
      error in `CalendarActionItemRequirementForm::validateForm()`.
- [x] Recipe management embedded on the `CalendarAction` canonical page
      via `CalendarActionController::buildRequirementsSection()`,
      mirroring `HiveController::buildInspectionsColumn()`'s
      heading+table shape (no pagination — recipes are short lists): a
      "Required Items" table (item, quantity, unit, operations) with
      Add/Edit/Delete, scoped to that calendar action.
- [x] `CalendarActionForm` unchanged — no new fields added there.
- [x] `hivelog.permissions.yml`: all seven permissions (view own/any,
      add, edit own/any, delete own/any) — the task's own acceptance
      text said "six" but listed seven; went with seven to match every
      other entity's pattern exactly.
- [x] `hivelog_update_10021` installs the entity type; added to
      `hivelog_uninstall()`'s cleanup list ahead of both `calendar_action`
      and `inventory_item` (it references both).
- [x] Kernel tests: `CalendarActionItemRequirementTest` (CRUD, the
      same-apiary guard, required-field/validation coverage),
      `CalendarActionItemRequirementAccessTest` (apiary-scoped access
      parity, owner-only delete), and two new tests in
      `CalendarActionTest` for the embedded requirements table
      (populated rows + the empty-state message).
- [x] `ddev drush updb -y && ddev drush cr` — `hivelog_update_10021`
      applied cleanly against `cms2`.
- [x] Full kernel + unit suite re-run against `cms2`: 354 tests, 4966
      assertions, 0 failures/errors (up from the 340/4767 baseline
      after task 0029). Two pre-existing tests
      (`ControllerCacheMetadataTest::testCalendarActionViewCacheMetadata`,
      `HiveCalendarChecklistTest::testDescriptionBulletRenderingIsEscaped`)
      broke because they render `CalendarActionController::view()`
      directly without the new entity schema installed — fixed by
      adding `installEntitySchema('calendar_action_item_requirement')`
      to both.
- [x] `drush php:eval` smoke test against `cms2`: created a real
      `CalendarAction` + `InventoryItem` + `CalendarActionItemRequirement`,
      confirmed the "Required Items" heading, "Add Required Item"
      button (with a correctly calendar-action-scoped href), and the
      requirement row all render — then cleaned up.

## Implementation notes
- Key files: `src/Entity/CalendarActionItemRequirement.php`,
  `src/CalendarActionItemRequirementAccessControlHandler.php`,
  `src/Form/CalendarActionItemRequirementForm.php`,
  `src/Form/CalendarActionItemRequirementDeleteForm.php`,
  `src/Controller/CalendarActionController.php` (`addRequirementForm()`
  + `buildRequirementsSection()`), `src/ApiaryAccessTrait.php` (one new
  `resolveApiary()` branch), `hivelog.routing.yml`,
  `hivelog.permissions.yml`, `hivelog.install`.
- No standalone `CalendarActionItemRequirementListBuilder` was built —
  the embedded table on the calendar action's canonical page is the only
  UI this entity needs; a standalone collection page would just
  duplicate it with no added value. Consistent with there being no
  `entity.calendar_action_item_requirement.collection` or `.canonical`
  route at all — only `edit_form`/`delete_form` (standard) and the
  calendar-action-scoped `hivelog.calendar_action_item_requirement.add`.
- `quantity`'s unit is implicitly the referenced `InventoryItem.unit` —
  the requirements table looks it up for display (`$item->get('unit')`)
  rather than duplicating it as its own field.
- `CalendarActionItemRequirementForm`/`DeleteForm` redirect to
  `entity.calendar_action.canonical`, not a generic collection — that
  page embeds the requirements table, so it's where the saved/deleted
  record is actually visible, matching how `HiveForm`/`CalendarActionForm`
  redirect to their embedding parent (unlike `InventoryItemForm`/
  `InventoryPurchaseForm` from task 0029, whose parent apiary page does
  *not* embed their tables).

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0003-code-defined-entity-schema]]
- Commits:: b5b54c0 (entity, access control, forms, embedded UI,
  permissions, install hook, tests), 1c89d6a (schema-install fix for two
  pre-existing tests)
