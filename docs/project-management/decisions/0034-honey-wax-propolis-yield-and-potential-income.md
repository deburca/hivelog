---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-08-20
supersedes:
---
# ADR-0034: Honey/wax/propolis yield and potential income

## Status
accepted

## Context
[[0027-inventory-tracking-and-depreciation]] explicitly scoped revenue out:
"no revenue/harvest-quantity/sales model exists yet in Hivelog... this ADR
only covers the cost side. A future ADR would need to define how
harvested/sold honey is recorded before profit itself can be reported."

The user has since asked for exactly that: the ability to report potential
income from harvesting honey, wax, propolis, etc. (a beekeeper might harvest
honey twice a year), and confirmed that jars and other consumables used
during harvest/bottling should reduce inventory the same way any other
reported action does.

These are two needs of very different sizes:

1. **Consuming inventory during a harvest** (jars for bottling, foundation
   for frames, etc.) needs zero new schema. "Harvest" is already a
   recognized `CalendarAction` category (`harvest_spring`/`harvest_summer`
   — see `CalendarAction.category`'s allowed values), so a
   `CalendarActionItemRequirement` for jars against a harvest action already
   flows through the existing `InventoryUsage` mechanism ([[0027-inventory-tracking-and-depreciation]],
   [[0030-calendar-action-item-requirement-and-recipe-ui]],
   [[0031-inventory-usage-and-action-log-reporting-integration]]) with no
   code changes at all.
2. **Recording what was produced, and its potential income** is new —
   Hivelog has no concept of a sellable product or an income figure.

## Decision

### 1. Jars/consumables used during harvest — no new schema
Confirmed already solved by the existing mechanism: a beekeeper adds a
`CalendarActionItemRequirement` row for "500g Honey Jars" against the
"Harvest Summer Honey" `CalendarAction`, exactly as they would for sugar
against a feeding action. When the harvest is reported `done`, the existing
`InventoryUsageFormTrait` pre-fills and records the quantity used, and
`InventoryItem::getStockOnHand()` reflects it immediately. No new work is
needed for this half of the request.

### 2. `Product` (apiary-scoped catalog — the sellable-output side)
Mirrors `InventoryItem`'s shape, but for things produced and sold rather
than bought and consumed:
- `apiary` (`entity_reference` → `apiary`, required)
- `name` (`string`, required) — e.g. "Honey", "Beeswax", "Propolis"
- `unit` (`string`, required) — free text (`kg`, `jar`, `bar`, `g`); no
  unit-conversion system, the same simplicity trade-off already made for
  `InventoryItem.unit`
- `expected_unit_price` (`decimal`, required) — the beekeeper's current
  best estimate of what a unit sells for. A single mutable "current
  assumption" field, not a priced history — matches the confirmed decision
  below that this models a potential/aggregate figure, not real per-sale
  transactions.
- `status` (`list_string`, default `active`) — `active` | `discontinued`,
  same rationale as `InventoryItem.status`: retire a product from new yield
  recipes without losing its harvest history.

### 3. `CalendarActionProductYield` (the recipe — plan half, mirrors `CalendarActionItemRequirement`)
- `calendar_action` (`entity_reference` → `calendar_action`, required)
- `product` (`entity_reference` → `product`, required) — must belong to the
  same apiary as the calendar action, enforced in `preSave()` the same way
  `CalendarActionItemRequirement` guards its own `item`/`calendar_action`
  apiary match
- `quantity` (`decimal`, required) — the estimated/typical yield per
  occurrence, e.g. "Harvest Summer Honey" → `{Honey: 20 kg, Beeswax:
  0.5 kg}`. Same "default estimate, not enforced formula" status as
  `CalendarActionItemRequirement.quantity` — actual yield varies by season
  and is adjusted at report time, the same way `week_completed` overrides a
  planned week without needing to match it.

Reusing the exact plan/log trigger shape inventory usage already
established means the report form's "what did you actually produce" fields
appear whenever a calendar action declares a yield recipe — no hardcoded
`harvest_spring`/`harvest_summer` category checks needed anywhere in code.
A beekeeper who wants a yield recipe on some other action (e.g. "Cut Comb
Honey" on a custom action) is free to add that row too; the mechanism
doesn't care which category the action belongs to.

### 4. `HarvestYield` (the actual output — log half, mirrors `InventoryUsage`)
- `product` (`entity_reference` → `product`, required)
- `quantity` (`decimal`, required)
- `hive_action_log` / `apiary_action_log` (`entity_reference`, each
  optional) — exactly one set, identical parallel-siblings pattern to
  `InventoryUsage.hive_action_log`/`apiary_action_log`
- `unit_price_snapshot` (`decimal`, auto-derived in `preSave()` from
  `product.expected_unit_price` at creation time, never recalculated
  afterwards) — mirrors `InventoryUsage.unit_cost_snapshot`'s "past reports
  stay stable" rationale exactly: raising the assumed honey price next year
  must not retroactively change what a past season's report says it
  earned.

Wired into `HiveActionLogForm`/`ApiaryActionLogForm` via a new
`HarvestYieldFormTrait`, structurally identical to
`InventoryUsageFormTrait` — a `done` report shows one pre-filled, editable
quantity field per `CalendarActionProductYield` row on the calendar action,
and creates/updates/removes `HarvestYield` rows on save the same way
`InventoryUsage` rows are synced today.

### 5. Potential income joins the existing cost report
`InventoryReportController`'s per-apiary, per-year report
([[0032-inventory-cost-and-depreciation-report]]) already computes total
consumable cost + active depreciation for a year. It gains a third figure:
**potential income** = `Σ HarvestYield.quantity × unit_price_snapshot` for
yield rows whose log's year matches the selected year — computed via the
same hive-log/apiary-log join `consumableCostBreakdown()` already performs
for consumable cost. The report becomes cost vs. income vs. net for the
year: the actual "more profitable as years go by" figure the original
inventory request was framed around, with both halves now in place.

### Confirmed design decision
**Potential income is an aggregate assumption, not a sales ledger.**
Confirmed by the user (2026-08-20): there is no `Sale` entity recording
actual per-transaction sale price, date, or buyer. `HarvestYield` records
*what was produced* and prices it at the product's current expected rate,
giving a *potential* income figure, not an audited *actual* one. This is
deliberately the inverse of the same trade-off already made for
`InventoryItem`/`InventoryPurchase`: a real sales ledger (mirroring
`InventoryPurchase`, recording actual transactions at actual prices over
time) is a plausible future ADR if actual-vs-potential tracking becomes
worth the extra bookkeeping, but is explicitly out of scope here.

## Consequences
- Positive: reuses the plan/log pattern a third time (`CalendarAction` →
  recipe → log, now for outputs as well as consumable inputs) rather than
  inventing new shape; the harvest-consumes-jars need is already fully
  solved by ADR-0027's existing mechanism with zero new code; potential
  income folds directly into the existing per-apiary cost report rather
  than a parallel page, giving a genuine cost-vs-income-vs-net view for the
  first time.
- Negative / trade-offs: two more content entities (`Product`,
  `CalendarActionProductYield`) plus one more log entity (`HarvestYield`)
  — six new entity types across ADR-0027 and this one for a hobbyist-scale
  tool; "potential income" can drift from reality since it's driven by a
  single mutable `expected_unit_price` assumption rather than real sale
  records; nothing here prevents double-counting if a beekeeper reports a
  `done` harvest action more than once for what was really one harvest —
  pre-existing behavior for any action log, not new to this ADR; no true
  profit figure exists without also knowing the cost of goods actually
  *sold* vs. produced, which this ADR doesn't attempt.
- Follow-up tasks: a project note + task breakdown, once requested,
  mirroring [[inventory-tracking-and-depreciation]]'s structure. A future
  ADR would be needed for an actual sales ledger (real transactions, not
  the aggregate assumption this ADR settles for).
