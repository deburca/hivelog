---
type: project
tags: [hivelog/project]
status: active
target:
created: 2026-08-19
---
# Project: Inventory tracking and multi-year asset depreciation

## Goal
Let beekeepers track inventory items (consumables like sugar and varroa
strips, durable equipment like frames and extractors) bought for use
against seasonal calendar actions, recording what was bought and at what
price, associating calendar actions with the items/quantities they
require, and — for durable items — depreciating their cost over a
configurable useful life so that a future cost report reflects honey
production becoming cheaper (and so more profitable) once equipment is
paid off. See [[0027-inventory-tracking-and-depreciation]] (accepted) for
the full data model and architecture.

## Scope
- In scope:
  - `InventoryItem` (apiary-scoped catalog): name, category, unit,
    `consumable`/`durable` type, useful life (durable only), status.
  - `InventoryPurchase` (apiary-scoped ledger): item, date, quantity,
    unit price, auto-derived total cost, supplier, notes.
  - `CalendarActionItemRequirement` (the "recipe"): links a
    `CalendarAction` to one or more items with a quantity — per-hive for
    hive-scoped actions, per-apiary for apiary-scoped ones.
  - `InventoryUsage` (the "actual" — consumables only): links a
    `HiveActionLog`/`ApiaryActionLog` "done" report to the item(s) and
    quantity actually consumed, with a cost snapshot taken at creation.
  - Stock-on-hand and depreciation are always computed from the ledger,
    never stored as running totals.
  - UI: catalog + purchase ledger CRUD pages (per apiary); recipe
    management embedded on the `CalendarAction` canonical/edit page;
    usage pre-fill (from the recipe, editable) wired into the
    `HiveActionLog`/`ApiaryActionLog` "done" report flow; a per-apiary,
    per-year cost report summing consumables used + active depreciation.
- Out of scope (for now):
  - Any revenue/harvest-quantity/honey-sales model — this project covers
    the **cost** side only. True profitability (revenue − cost) needs a
    future project/ADR to define how harvested and sold honey is
    recorded.
  - Unit conversion (kg vs lb, L vs mL) — one unit per item, chosen
    consistently by the beekeeper.
  - FIFO/lot costing — weighted-average cost only.
  - Cross-apiary aggregate inventory views (e.g. "40kg of sugar across
    all my apiaries") — a consequence of the confirmed per-apiary scoping
    decision; could be a future report if it turns out to matter.
  - Asset disposal/write-off tracking — a durable item, once purchased,
    is assumed usable through (at least) its full depreciation window;
    no "retire this asset early" workflow yet.
  - Usage tracking for durable items — they appear in recipes as a
    checklist reminder only, never consumed by an `InventoryUsage` row.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0028-inventory-item-and-purchase-entities]] — done (`InventoryItem`
  and `InventoryPurchase` entities, basic forms/list builders,
  permissions, `hivelog_update_10020`; verified end-to-end against a
  real Drupal site, full suite at 318 tests/4550 assertions/0 failures)
- [[0029-inventory-catalog-and-purchase-ledger-ui]] — done (routes,
  access control, sectioned canonical pages, self-built-heading +
  `hivelog:entity-table` list builders, `getStockOnHand()`, apiary page
  Inventory heading; verified end-to-end against a real Drupal site,
  full suite at 340 tests/4767 assertions/0 failures)
- [[0030-calendar-action-item-requirement-and-recipe-ui]] — done (the
  recipe entity, apiary-scoped access control, and the embedded
  "Required Items" table on the calendar action canonical page; verified
  end-to-end against a real Drupal site, full suite at 354
  tests/4966 assertions/0 failures)
- [[0031-inventory-usage-and-action-log-reporting-integration]] — done
  (`InventoryUsage` entity, shared `InventoryUsageFormTrait` wiring the
  "done" report flow on both `HiveActionLogForm` and
  `ApiaryActionLogForm` to pre-fill from the recipe and record actual
  usage with a snapshotted weighted-average unit cost; verified
  end-to-end against a real Drupal site, full suite at 376 tests/0
  failures attributable to this change)
- [[0032-inventory-cost-and-depreciation-report]] — done
  (`InventoryItem::getAnnualDepreciation()`, `InventoryReportController`
  aggregating consumable cost + durable depreciation per apiary/year with
  a previous/current/next year selector and per-item breakdown table;
  verified end-to-end against a real Drupal site, full suite at 383
  tests/0 failures attributable to this change)
- [[0033-inventory-test-coverage]] — backlog

Suggested build order matches the numbering: the two ledger entities first
(nothing else can be built without them), then their own CRUD UI, then the
recipe (depends on `InventoryItem` existing), then usage/reporting
integration (depends on the recipe for pre-fill, and on `InventoryUsage`
existing), then the cost report (depends on everything above), then test
coverage threaded through rather than saved entirely to the end — mirroring
how [[seasonal-calendar-and-hive-action-tracking]] sequenced its own build,
except that project deferred all kernel tests to one final task
([[0024-calendar-test-coverage]]); this project's [[0033-inventory-test-coverage]]
should be treated as a backstop for coverage gaps, not the only place tests
get written — each task's acceptance criteria should include its own tests
per the task template.

## Open questions
- Should `InventoryItem.category` be a hard-coded allowed-value list (like
  `CalendarAction.category`) or free text? A fixed list keeps filtering
  simple but the four categories drafted in the ADR (`feed`, `treatment`,
  `packaging`, `equipment`, `other`) are a guess — worth confirming
  against real purchase history once some exists.
- Should `InventoryItem.status = discontinued` actually hide the item from
  new `CalendarActionItemRequirement`/`InventoryPurchase` selection
  widgets, or just be informational? `CalendarAction.enabled` has a
  precedent for "hidden going forward, but existing references untouched"
  — likely the right model here too, but not yet confirmed.
- Should there be a low-stock warning (e.g. surfaced on the apiary page)
  once stock-on-hand queries exist? Not requested yet, but a natural,
  low-cost addition once [[0029-inventory-catalog-and-purchase-ledger-ui]]
  computes stock-on-hand anyway.

## Related decisions
- [[0027-inventory-tracking-and-depreciation]] (accepted — core
  architecture for this project)
- [[0025-seasonal-calendar-and-hive-action-tracking]] (the plan/log
  pattern this project extends, and the `CalendarAction`/
  `HiveActionLog`/`ApiaryActionLog` entities it attaches to)
- [[0003-code-defined-entity-schema]] (baseFieldDefinitions + update hooks
  — every new entity here needs a paired `hivelog_update_NNNN` hook)
- [[0019-authorisation-model]] / [[0020-access-parity-custom-routes]] /
  [[0021-field-level-access]] (permission and access-control parity —
  `InventoryItem`/`InventoryPurchase` need the same apiary-membership-scoped
  access model as `Hive`/`CalendarAction`, via `ApiaryAccessTrait`)
