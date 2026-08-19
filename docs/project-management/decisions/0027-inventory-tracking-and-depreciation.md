---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-08-19
supersedes:
---
# ADR-0027: Inventory tracking and multi-year asset depreciation

## Status
accepted

## Context
Seasonal calendar actions (ADR-0025) often consume physical inventory:
sugar and fondant for feeding, varroa treatment strips, jars and labels for
bottling honey, foundation and frames for equipment prep. Some of these are
**consumable** — sugar, once fed, cannot be reused — while others are
**durable** — frames, supers, an extractor — bought once and used across
many seasons. The user asked for:

1. A catalog of inventory items.
2. A purchase ledger recording amount bought and unit price.
3. The ability to associate one or more calendar actions with the items
   (and quantities) they require.
4. Multi-year depreciation for durable items, on the premise that once a
   durable asset is paid off, honey production should become more
   profitable in later years (no more of that asset's cost is being
   charged against that year's output).

This follows the same **plan vs. log** split ADR-0025 already established
for the calendar itself (`CalendarAction` = plan, `HiveActionLog`/
`ApiaryActionLog` = log of what actually happened) — this ADR extends that
same shape into inventory rather than inventing a new one:
`CalendarActionItemRequirement` is the "recipe" (plan) half, `InventoryUsage`
is the "actually consumed" (log) half.

## Decision

### 1. `InventoryItem` (apiary-scoped — the catalog)
- `apiary` (`entity_reference` → `apiary`, required)
- `name` (`string`, required) — e.g. "Granulated Sugar", "Apivar Strips",
  "500g Honey Jars"
- `category` (`list_string`) — `feed`, `treatment`, `packaging`,
  `equipment`, `other`
- `unit` (`string`) — free text (`kg`, `L`, `strip`, `jar`, `roll`,
  `frame`, `each`); no unit-conversion system — a beekeeper picks one unit
  per item and stays consistent, same simplicity trade-off already made
  for `Hive`/`Queen` allowed-value lists.
- `item_type` (`list_string`, required) — `consumable` | `durable`. The
  key branch: `consumable` items track stock and get consumed by
  `InventoryUsage`; `durable` items depreciate and are never consumed by a
  usage record (see decision 4 below).
- `useful_life_years` (`integer`) — required when `item_type = durable`,
  irrelevant otherwise. Straight-line depreciation window (frames: ~5,
  extractor: ~10).
- `status` (`list_string`, default `active`) — `active` | `discontinued`,
  so a catalog entry can be retired without losing purchase history.

### 2. `InventoryPurchase` (apiary-scoped — the acquisition ledger)
- `item` (`entity_reference` → `inventory_item`, required)
- `purchase_date` (`datetime`, required)
- `quantity` (`decimal`, required)
- `unit_price` (`decimal`, required)
- `total_cost` (`decimal`, auto-derived in `preSave()` as
  `quantity × unit_price` — same auto-derive pattern already used by
  `Queen::preSave()` for `queen_colour`)
- `supplier` (`string`, optional), `notes` (`string_long`, optional)

This is the single source of truth for "amount bought and unit price." It
is never edited by consumption — only by correcting a mis-entered purchase.

### 3. `CalendarActionItemRequirement` (the recipe — plan half)
- `calendar_action` (`entity_reference` → `calendar_action`, required)
- `item` (`entity_reference` → `inventory_item`, required) — **must belong
  to the same apiary as the calendar action** (enforced in `preSave()`,
  mirroring how `CalendarAction::preSave()` already guards
  `week_end >= week_start`)
- `quantity` (`decimal`, required) — per hive occurrence for a
  hive-scoped `CalendarAction`, per apiary occurrence for an
  apiary-scoped one (scope is inherited from the parent `CalendarAction`,
  not duplicated here)

A `CalendarAction` has zero or more requirement rows — e.g. "Varroa
Treatment (Spring)" → `{Apivar Strips: 2}`; "Autumn Feeding" →
`{Granulated Sugar: 2.5 kg}`. For actions like "Harvest Summer Honey"
where the real quantity of jars/labels needed depends on yield, the
requirement quantity is a **default estimate** that pre-fills the report
form — not an enforced formula. The beekeeper adjusts the actual amount
at report time, the same way `week_completed` already overrides the
planned week without needing to match it.

### 4. `InventoryUsage` (the actual consumption — log half, consumables only)
- `item` (`entity_reference` → `inventory_item`, required — restricted to
  `item_type = consumable` items only)
- `quantity` (`decimal`, required)
- `hive_action_log` (`entity_reference` → `hive_action_log`, optional) /
  `apiary_action_log` (`entity_reference` → `apiary_action_log`,
  optional) — exactly one set, mirroring the parallel-siblings style
  `HiveActionLog`/`ApiaryActionLog` already use instead of a polymorphic
  reference
- `unit_cost_snapshot` (`decimal`, auto-derived in `preSave()` from the
  item's weighted-average purchase cost at that moment:
  `Σ(purchase.quantity × purchase.unit_price) / Σ(purchase.quantity)`
  across all `InventoryPurchase` rows for that item to date)

**Durable items never get an `InventoryUsage` row.** When a durable item
appears in a `CalendarActionItemRequirement`, it's a checklist reminder
("you'll need the extractor") surfaced on the report form, not a
transaction — its cost is already fully accounted for via depreciation
regardless of how often it's used, so per-use tracking would add
bookkeeping with no accounting payoff.

### Costing and depreciation are computed, never stored as running state
Consistent with this module's existing preference for derived facts over
duplicated state (`Hive::getActiveQueen()`, `Hive::getQueens()`):

- **Stock on hand** (consumables) = `Σ InventoryPurchase.quantity −
  Σ InventoryUsage.quantity` for that item — a query, not a field.
- **Depreciation**: a durable purchase costing `C`, bought in year `Y0`,
  with `useful_life_years = N`, contributes `C / N` to each year from
  `Y0` through `Y0 + N − 1`, and `0` after that. A future cost report
  sums, per apiary per year: `Σ InventoryUsage.quantity ×
  unit_cost_snapshot` (consumables actually used that year) + `Σ`
  active depreciation across every durable purchase whose window covers
  that year.

**This is the entire mechanism behind "more profitable as years go by"** —
once a durable asset's `N`-year window closes, its annual cost
contribution drops to zero while it keeps producing, so the same output
costs less to produce and margins improve. Nothing needs to actively
*create* that trend; correctly modeling depreciation and summing it per
year is sufficient. No revenue/harvest-quantity/sales model exists yet in
Hivelog, so a *true* profitability figure (revenue − cost) is out of scope
here — this ADR only covers the cost side. A future ADR would need to
define how harvested/sold honey is recorded before profit itself can be
reported.

### Confirmed design decisions
Three genuine forks were resolved by the user directly (2026-08-19):

1. **Scope: per-apiary, not shared.** `InventoryItem` and
   `InventoryPurchase` both carry a required `apiary` reference, matching
   `Hive`/`CalendarAction`. A beekeeper with multiple apiaries maintains a
   separate catalog and purchase ledger per apiary. (This does mean
   re-entering common items like "Granulated Sugar" per apiary if bought
   for more than one — accepted trade-off for accurate per-apiary
   budgets.)
2. **Cost snapshot, not live recompute.** `InventoryUsage.unit_cost_snapshot`
   is fixed at creation time. Past cost reports stay stable even if a
   purchase record is edited or a backdated purchase is added later —
   standard bookkeeping practice, and consistent with `InventoryPurchase.
   total_cost` also being a preSave-derived, not live-computed, value.
3. **Durable items: consumables-only usage tracking.** No `InventoryUsage`
   row is ever created for a `durable` item (see decision 4 above).

## Consequences
- Positive: reuses an already-proven plan/log architecture rather than
  inventing new shape; depreciation and stock levels stay correct by
  construction since they're computed from an append-only ledger, not
  maintained as mutable running totals; per-apiary scoping matches how
  `Hive` and `CalendarAction` already work, so access control
  (`ApiaryAccessTrait`) extends with no new concepts.
- Negative / trade-offs: four new content entities is a meaningful chunk
  of schema; per-apiary scoping means no cross-apiary "I have 40kg of
  sugar total" view without a future aggregate report; weighted-average
  costing (not FIFO/lot-tracking) is simpler but less precise than true
  lot costing — acceptable for a small-scale/hobbyist tool, revisit only
  if it proves insufficient in practice.
- Follow-up tasks: see the
  [[inventory-tracking-and-depreciation]] project note for the task
  breakdown. A future ADR is needed before true profitability (not just
  cost) can be reported, since Hivelog has no harvest-quantity or
  honey-sales model yet.
