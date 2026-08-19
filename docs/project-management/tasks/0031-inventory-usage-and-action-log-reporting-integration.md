---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-tracking-and-depreciation]]"
area: entity
created: 2026-08-19
branch: feature/0031-inventory-usage-and-action-log-reporting-integration
release:
depends-on: ["[[0030-calendar-action-item-requirement-and-recipe-ui]]"]
blocked-by:
---
# Task: `InventoryUsage` entity and action-log reporting integration

## Context
The "actual" half of inventory usage, per
[[0027-inventory-tracking-and-depreciation]]: when a beekeeper reports a
`HiveActionLog`/`ApiaryActionLog` as `done`, this is where consumable
items and quantities actually used get recorded, pre-filled from
[[0030-calendar-action-item-requirement-and-recipe-ui]]'s recipe but
editable. Mirrors the existing "Also create a hive inspection record"
checkbox pattern from
[[0023-link-hive-action-log-to-inspection]]/`HiveActionLogForm::
createLinkedInspection()` — a `done` report optionally creating related
records as a side effect of saving.

## Acceptance criteria
- [ ] `src/Entity/InventoryUsage.php` — base table
      `hivelog_inventory_usage`, entity keys (`id`, `uuid`, `owner` →
      `uid`); `label()` composed as `"@item — @quantity @unit"`.
- [ ] Fields: `item` (required entity_reference → `inventory_item`, form
      widget restricted to `item_type = consumable` items only — see
      validation below for the durable case), `quantity` (required
      decimal), `hive_action_log` (optional entity_reference →
      `hive_action_log`), `apiary_action_log` (optional entity_reference
      → `apiary_action_log`), `unit_cost_snapshot` (decimal, NOT on the
      form — auto-derived in `preSave()` as the weighted average:
      `Σ(purchase.quantity × purchase.unit_price) / Σ(purchase.quantity)`
      across all `InventoryPurchase` rows for `item` as of save time),
      plus `uid`/`created`/`changed`.
- [ ] `preSave()` validation: exactly one of `hive_action_log` /
      `apiary_action_log` must be set (not both, not neither); `item.
      item_type` must be `consumable` — reject (throw
      `\InvalidArgumentException`) an attempt to record usage against a
      `durable` item, per the ADR's explicit decision that durable items
      are never consumed by a usage record.
- [ ] `HiveActionLogForm`/`ApiaryActionLogForm`: when the report's
      `status` is set to `done`, show the `calendar_action`'s
      `CalendarActionItemRequirement` rows as a pre-filled, editable list
      of item + quantity (defaulting to the recipe's quantity, per the
      ADR's "estimate, not enforced formula" decision), and on save,
      create one `InventoryUsage` row per non-zero-quantity line, linked
      to the newly-saved log. Mirror
      `HiveActionLogForm::createLinkedInspection()`'s
      conditional-side-effect-on-save structure.
- [ ] Editing an already-`done` log's usage rows: decide and implement
      whether re-saving updates existing `InventoryUsage` rows in place
      or creates new ones — recommend updating in place (matching a
      single log having a stable, editable set of usage rows) unless a
      concrete reason emerges to prefer an append-only usage history per
      log.
- [ ] `hivelog.permissions.yml`: `view own inventory usage`, `view any …`,
      `add …`, `edit own …`, `edit any …`, `delete own …`, `delete any …`.
- [ ] `hivelog_update_NNNN` installs the new entity type; add to
      `hivelog_uninstall()`'s cleanup list before `hive_action_log`/
      `apiary_action_log`.
- [ ] Kernel tests: CRUD, the exactly-one-of-hive/apiary-log guard, the
      consumable-only guard (durable item rejected), weighted-average
      `unit_cost_snapshot` derivation with multiple purchases at
      different prices, the report-form pre-fill-from-recipe behaviour,
      and `InventoryItem::getStockOnHand()` (from
      [[0029-inventory-catalog-and-purchase-ledger-ui]]) correctly
      reflecting usage once these rows exist.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryUsage.php`,
  `src/Form/HiveActionLogForm.php`, `src/Form/ApiaryActionLogForm.php`,
  `hivelog.install`.
- Study `HiveActionLogForm::createLinkedInspection()` closely before
  starting — this task's "create InventoryUsage rows from a done report"
  is structurally the same feature (optional side-effect entities created
  from one form's save handler) and should reuse its shape rather than
  inventing a new one.
- The weighted-average cost calculation needs `InventoryPurchase` history
  for the item up to (and including) the purchases made before this
  usage's creation `created` timestamp — not literally "all purchases
  ever", if purchases can be backdated after some usage already happened.
  Decide at implementation time whether this level of precision is worth
  it or whether "all purchases as of now" is an acceptable simplification
  for a first pass (recommend the simpler version initially, since
  backdated purchases after usage already occurred should be rare for
  this tool's scale).

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0023-link-hive-action-log-to-inspection]]
- Commits::
