---
type: task
tags: [hivelog/task]
status: done
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
- [x] `src/Entity/InventoryUsage.php` — base table
      `hivelog_inventory_usage`, entity keys (`id`, `uuid`, `owner` →
      `uid`); `label()` composed as `"@item — @quantity @unit"`.
- [x] Fields: `item` (required entity_reference → `inventory_item`, form
      widget restricted to `item_type = consumable` items only — see
      validation below for the durable case), `quantity` (required
      decimal), `hive_action_log` (optional entity_reference →
      `hive_action_log`), `apiary_action_log` (optional entity_reference
      → `apiary_action_log`), `unit_cost_snapshot` (decimal, NOT on the
      form — auto-derived in `preSave()` as the weighted average:
      `Σ(purchase.quantity × purchase.unit_price) / Σ(purchase.quantity)`
      across all `InventoryPurchase` rows for `item` as of save time),
      plus `uid`/`created`/`changed`.
- [x] `preSave()` validation: exactly one of `hive_action_log` /
      `apiary_action_log` must be set (not both, not neither); `item.
      item_type` must be `consumable` — reject (throw
      `\InvalidArgumentException`) an attempt to record usage against a
      `durable` item, per the ADR's explicit decision that durable items
      are never consumed by a usage record.
- [x] `HiveActionLogForm`/`ApiaryActionLogForm`: when the report's
      `status` is set to `done`, show the `calendar_action`'s
      `CalendarActionItemRequirement` rows as a pre-filled, editable list
      of item + quantity (defaulting to the recipe's quantity, per the
      ADR's "estimate, not enforced formula" decision), and on save,
      create one `InventoryUsage` row per non-zero-quantity line, linked
      to the newly-saved log. Mirror
      `HiveActionLogForm::createLinkedInspection()`'s
      conditional-side-effect-on-save structure. Implemented as a shared
      `InventoryUsageFormTrait` used by both forms.
- [x] Editing an already-`done` log's usage rows: implemented updating
      existing `InventoryUsage` rows in place on re-save (not
      append-only), per the recommendation. Also handles the edge case
      the ADR didn't explicitly spell out: changing status away from
      `done` on an already-reported log deletes its previously recorded
      usage rows, since they no longer represent a real consumption
      event.
- [x] `hivelog.permissions.yml`: `view own inventory usage`, `view any …`,
      `add …`, `edit own …`, `edit any …`, `delete own …`, `delete any …`.
- [x] `hivelog_update_10022` installs the new entity type; added to
      `hivelog_uninstall()`'s cleanup list before `hive_action_log`/
      `apiary_action_log`/`inventory_item`, all of which it references.
- [x] Kernel tests: CRUD, the exactly-one-of-hive/apiary-log guard, the
      consumable-only guard (durable item rejected), weighted-average
      `unit_cost_snapshot` derivation with multiple purchases at
      different prices (including proof the snapshot is immutable after
      creation even if a later, more expensive purchase is recorded),
      the report-form pre-fill-from-recipe behaviour (both
      `HiveActionLogForm` and `ApiaryActionLogForm`), and
      `InventoryItem::getStockOnHand()` (from
      [[0029-inventory-catalog-and-purchase-ledger-ui]]) correctly
      reflecting usage once these rows exist. 34 new tests across
      `InventoryUsageTest`, `InventoryUsageAccessTest`, and
      `InventoryUsageReportingIntegrationTest`.
- [x] `ddev drush updb -y && ddev drush cr` clean.

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

## Verification
- Full kernel+unit suite against `cms2` (MySQL): 376 tests, 0 failures
  attributable to this change. CI (`gh run list`, MySQL-backed) green on
  the fix commit. Two local-sqlite-only anomalies
  (`QueenTest::testCreateQueen` decimal formatting,
  `ApiaryCalendarChecklistTest::testFullCalendarFiltersNarrowResults`)
  are pre-existing, untouched by any commit in this session, and don't
  reproduce under CI's MySQL backend — out of scope for this task.
- End-to-end smoke test via `drush php:eval`: created an `InventoryItem`
  + `InventoryPurchase` + `CalendarActionItemRequirement`, submitted a
  `HiveActionLog` "done" report through the real
  `HiveActionLogForm::save()` path, confirmed an `InventoryUsage` row was
  created with the correct weighted-average `unit_cost_snapshot`, and
  confirmed `InventoryItem::getStockOnHand()` reflected the consumption
  (20 → 17).

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0023-link-hive-action-log-to-inspection]]
- Commits:: 144b878 (entity, access control, form trait, wiring into both
  action-log forms, permissions, install hook, tests), d763c35
  (schema-install fix for two pre-existing tests + own new test file)
