---
type: task
tags: [hivelog/task]
status: in-progress
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
- [ ] `src/Entity/InventoryItem.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_inventory_item`, entity
      keys (`id`, `label` → `name`, `uuid`, `owner` → `uid`).
- [ ] `InventoryItem` fields: `apiary` (required entity_reference →
      `apiary`), `name` (required string), `category` (list_string:
      `feed`, `treatment`, `packaging`, `equipment`, `other` — see the
      project's open question on whether this should be free text
      instead), `unit` (required string, free text), `item_type`
      (required list_string: `consumable` | `durable`), `useful_life_years`
      (integer, only meaningful when `item_type = durable` — validate in
      `preSave()` that it's set for durable items, following the
      `CalendarAction::preSave()` guard-clause precedent), `status`
      (list_string, default `active`: `active` | `discontinued`), plus
      `uid`/`created`/`changed`.
- [ ] `src/Entity/InventoryPurchase.php` — base table
      `hivelog_inventory_purchase`, entity keys (`id`, `uuid`, `owner` →
      `uid`; no natural `label` field — implement `label()` as
      `"@item — @quantity @unit (@date)"`, mirroring how
      `HiveActionLog::label()` composes a label from its references).
- [ ] `InventoryPurchase` fields: `apiary` (required entity_reference →
      `apiary`), `item` (required entity_reference → `inventory_item`),
      `purchase_date` (required datetime, date only), `quantity`
      (required decimal), `unit_price` (required decimal), `total_cost`
      (decimal, NOT displayed on the form — auto-derived in `preSave()`
      as `quantity × unit_price`, same pattern as `Queen::preSave()`
      deriving `queen_colour`), `supplier` (optional string), `notes`
      (optional string_long), plus `uid`/`created`/`changed`.
- [ ] Validation: `InventoryPurchase.item` must belong to the same
      `apiary` as the purchase itself — guard in `preSave()`, matching the
      `week_end >= week_start` guard style already used by
      `CalendarAction::preSave()`.
- [ ] `hivelog.permissions.yml`: `view own inventory item`,
      `view any inventory item`, `add inventory item`,
      `edit own inventory item`, `edit any inventory item`,
      `delete own inventory item`, `delete any inventory item`, and the
      same six-permission set for `inventory purchase`.
- [ ] `hivelog_update_NNNN` in `hivelog.install` installs both new entity
      types (net-new tables, no data migration needed), following the
      `hivelog_update_10009`/`10014` precedents exactly. Add both entity
      type IDs to `hivelog_uninstall()`'s child-first cleanup list
      (`inventory_purchase` before `inventory_item`, since purchases
      reference items).
- [ ] Kernel tests: CRUD for both entities, the same-apiary validation
      guard, `total_cost` auto-derivation, `useful_life_years` required
      when `item_type = durable`.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php`,
  `src/Entity/InventoryPurchase.php`, `hivelog.permissions.yml`,
  `hivelog.install`.
- Follow `src/Entity/CalendarAction.php` as the structural template for
  `InventoryItem` (single required apiary reference + list_string enums),
  and `src/Entity/HiveActionLog.php` for `InventoryPurchase` (multiple
  entity references + a composed `label()`).
- List builders, forms, routes, and access control handlers are deferred
  to [[0029-inventory-catalog-and-purchase-ledger-ui]] — this task is
  schema only, matching how [[0017-calendar-action-entity-and-schema]]
  and [[0019-calendar-routing-controllers-and-access]] were split.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0003-code-defined-entity-schema]]
- Commits::
