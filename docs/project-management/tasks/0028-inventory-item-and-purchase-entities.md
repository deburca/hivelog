---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[inventory-tracking-and-depreciation]]"
area: entity
created: 2026-08-19
branch: feature/0028-inventory-item-and-purchase-entities
release:
depends-on:
blocked-by:
---
# Task: Define the `InventoryItem` and `InventoryPurchase` entities

## Context
Foundational pair for [[inventory-tracking-and-depreciation]] — the
catalog and the acquisition ledger. Nothing else in this project can be
built until these exist, per
[[0027-inventory-tracking-and-depreciation]].

## Acceptance criteria
- [x] `src/Entity/InventoryItem.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_inventory_item`, entity
      keys (`id`, `label` → `name`, `uuid`, `owner` → `uid`).
- [x] `InventoryItem` fields: `apiary` (required entity_reference →
      `apiary`), `name` (required string), `category` (list_string:
      `feed`, `treatment`, `packaging`, `equipment`, `other` — still the
      project's open question on free text vs. fixed list, unchanged by
      this task), `unit` (required string, free text), `item_type`
      (required list_string: `consumable` | `durable`, default
      `consumable`), `useful_life_years` (integer, only meaningful when
      `item_type = durable` — validated in `preSave()`), `status`
      (list_string, default `active`: `active` | `discontinued`), plus
      `uid`/`created`/`changed`.
- [x] `src/Entity/InventoryPurchase.php` — base table
      `hivelog_inventory_purchase`, entity keys (`id`, `uuid`, `owner` →
      `uid`); `label()` composed as `"@item — @quantity @unit (@date)"`.
- [x] `InventoryPurchase` fields: `apiary` (required entity_reference →
      `apiary`), `item` (required entity_reference → `inventory_item`),
      `purchase_date` (required datetime, date only), `quantity`
      (required decimal, precision 10/scale 3), `unit_price` (required
      decimal, precision 10/scale 2), `total_cost` (decimal, hidden on
      the form via `InventoryPurchaseForm` — auto-derived in `preSave()`
      as `quantity × unit_price`), `supplier` (optional string), `notes`
      (optional string_long), plus `uid`/`created`/`changed`.
- [x] Validation: `InventoryPurchase.item` must belong to the same
      `apiary` as the purchase itself — guarded in `preSave()` (throws)
      and in `InventoryPurchaseForm::validateForm()` (proper form error).
      `InventoryItem`'s durable-requires-useful-life invariant is
      similarly guarded in both `preSave()` and `InventoryItemForm::
      validateForm()`.
- [x] `hivelog.permissions.yml`: seven permissions each for
      `inventory item` and `inventory purchase` (view own/any, add, edit
      own/any, delete own/any).
- [x] `hivelog_update_10020` in `hivelog.install` installs both new
      entity types (net-new tables, no data migration needed). Both
      entity type IDs added to `hivelog_uninstall()`'s child-first
      cleanup list (`inventory_purchase` before `inventory_item`, both
      before `apiary`).
- [x] Kernel tests (`InventoryItemTest`, `InventoryPurchaseTest`): CRUD,
      field defaults, the same-apiary validation guard, `total_cost`
      auto-derivation (including re-derivation on update), the
      durable-requires-useful-life guard (and that consumables never
      need it), required-field and allowed-value validation for both
      entities.
- [x] `ddev drush updb -y && ddev drush cr` clean — verified against
      `cms2` (`hivelog/hivelog` pinned to `dev-main`): `hivelog_update_
      10020` installs both tables, `drush cr` completes with no errors.
- [x] Full kernel + unit suite (`--group hivelog`) re-run against
      `cms2`: 318 tests, 4550 assertions, 0 failures/errors (up from the
      305/4415 baseline before this task). One real issue was caught and
      fixed along the way: both new "invalid input rejected" tests
      initially asserted `\InvalidArgumentException` directly, but
      `EntityInterface::save()` wraps a `preSave()` exception in
      `EntityStorageException` — fixed to expect `\Exception`, matching
      `CalendarActionTest::testWeekEndBeforeWeekStartRejected`'s existing
      pattern exactly.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php`,
  `src/Entity/InventoryPurchase.php`, `src/Form/InventoryItemForm.php`,
  `src/Form/InventoryItemDeleteForm.php`, `src/InventoryItemListBuilder.php`,
  `src/Form/InventoryPurchaseForm.php`,
  `src/Form/InventoryPurchaseDeleteForm.php`,
  `src/InventoryPurchaseListBuilder.php`, `hivelog.permissions.yml`,
  `hivelog.install`. Tests: `tests/src/Kernel/InventoryItemTest.php`,
  `tests/src/Kernel/InventoryPurchaseTest.php`.
- Followed `src/Entity/CalendarAction.php` as the structural template for
  `InventoryItem` (single required apiary reference + list_string enums),
  and `src/Entity/HiveActionLog.php` for `InventoryPurchase` (multiple
  entity references + a composed `label()`).
- Re-reading [[0017-calendar-action-entity-and-schema]] while
  implementing this task showed its "entity/schema task" scope actually
  *did* include a basic `ContentEntityForm`/`ContentEntityDeleteForm`/
  `EntityListBuilder` trio (plain default-table list builder, not the
  self-built-heading + `hivelog:entity-table` upgrade) — only the custom
  controllers/access-control-handler/routing layer was deferred to
  [[0019-calendar-routing-controllers-and-access]]. This task followed
  that same split, so basic forms/list builders for both entities exist
  now; **no `AccessControlHandler` is wired into either `#[ContentEntityType]`
  attribute yet** (Drupal defaults to `EntityAccessControlHandler`) —
  that, along with scoped-add routes/controllers and the
  `hivelog:entity-table` list builder upgrade, remains
  [[0029-inventory-catalog-and-purchase-ledger-ui]]'s job.
- No routes exist yet for either entity (no `hivelog.routing.yml`
  changes in this task) — also [[0029-inventory-catalog-and-purchase-ledger-ui]]'s
  scope, matching how `CalendarAction` had no routes until
  [[0019-calendar-routing-controllers-and-access]] either.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0003-code-defined-entity-schema]]
- Commits:: 56b3a97 (entities, forms, list builders, permissions, install
  hook, tests), bf7e3f4 (test-expectation fix for wrapped
  `EntityStorageException`)
