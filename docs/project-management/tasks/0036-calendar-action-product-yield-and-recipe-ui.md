---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[honey-wax-propolis-yield-and-potential-income]]"
area: entity
created: 2026-08-20
branch: feature/0036-calendar-action-product-yield-and-recipe-ui
release:
depends-on: ["[[0035-product-catalog-entity-and-ui]]"]
blocked-by:
---
# Task: `CalendarActionProductYield` entity and recipe management UI

## Context
The "plan" half of yield tracking — lets a `CalendarAction` (e.g. "Harvest
Summer Honey") declare which products and roughly how much of each it
typically produces, per
[[0034-honey-wax-propolis-yield-and-potential-income]]. This is what
[[0037-harvest-yield-and-action-log-reporting-integration]] pre-fills its
report form from. Mirrors
[[0030-calendar-action-item-requirement-and-recipe-ui]] exactly, one level
removed (outputs instead of inputs).

## Acceptance criteria
- [ ] `src/Entity/CalendarActionProductYield.php` — base table
      `hivelog_calendar_action_product_yield`, entity keys (`id`, `uuid`,
      `owner` → `uid`). No `collection` or `canonical` link — managed
      entirely from the embedded table, matching
      `CalendarActionItemRequirement`.
- [ ] Fields: `calendar_action` (required entity_reference →
      `calendar_action`), `product` (required entity_reference →
      `product`), `quantity` (required decimal, precision 10/scale 3),
      plus `uid`/`created`/`changed`. `quantity`'s unit is implicitly the
      referenced product's `unit` (no redundant field).
- [ ] `preSave()` guard: `product.apiary` must equal
      `calendar_action.apiary` — throws, mirroring
      `CalendarActionItemRequirement::preSave()`'s identical guard;
      enforced as a proper form error too.
- [ ] Recipe management embedded on the `CalendarAction` canonical page
      via a new `CalendarActionController::buildYieldSection()`, placed
      alongside (not replacing) `buildRequirementsSection()` — a
      calendar action can need items (jars) and yield products (honey)
      at once, e.g. "Harvest Summer Honey" needs jars *and* produces
      honey/wax. An "Expected Yield" table (product, quantity, unit,
      operations) with Add/Edit/Delete, scoped to that calendar action.
- [ ] `CalendarActionForm` unchanged — no new fields added there.
- [ ] `hivelog.permissions.yml`: seven permissions (view own/any, add,
      edit own/any, delete own/any).
- [ ] `hivelog_update_NNNN` installs the entity type; added to
      `hivelog_uninstall()`'s cleanup list ahead of both
      `calendar_action` and `product` (it references both).
- [ ] Kernel tests: `CalendarActionProductYieldTest` (CRUD, the
      same-apiary guard, required-field/validation coverage),
      `CalendarActionProductYieldAccessTest` (apiary-scoped access
      parity, owner-only delete), and coverage in `CalendarActionTest`
      for the embedded yield table (populated rows + empty-state
      message) alongside the existing requirements-table coverage — both
      sections must render correctly together on the same page.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/CalendarActionProductYield.php`,
  `src/CalendarActionProductYieldAccessControlHandler.php`,
  `src/Form/CalendarActionProductYieldForm.php`,
  `src/Form/CalendarActionProductYieldDeleteForm.php`,
  `src/Controller/CalendarActionController.php`
  (`addYieldForm()` + `buildYieldSection()`), `src/ApiaryAccessTrait.php`
  (new `resolveApiary()` branch), `hivelog.routing.yml`,
  `hivelog.permissions.yml`, `hivelog.install`.
- No standalone list builder — same rationale as
  `CalendarActionItemRequirement`: the embedded table is the only UI this
  entity needs.
- `CalendarActionProductYieldForm`/`DeleteForm` redirect to
  `entity.calendar_action.canonical`, matching
  `CalendarActionItemRequirementForm`'s redirect-to-embedding-parent
  choice.
- Watch the calendar action canonical page's cache metadata
  (`ControllerCacheMetadataTest`) and any other kernel test that renders
  `CalendarActionController::view()` directly without the new schema
  installed — task 0030 hit this exact regression
  (`installEntitySchema('calendar_action_item_requirement')` missing from
  two pre-existing tests); expect the same class of fix here for
  `calendar_action_product_yield`.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits::
