---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-28
supersedes:
---
# ADR-0106: Hive component weight tracking + net colony/honey weight

## Status
accepted, 2026-09-28, at the user's request, made now that task 0079's
weight-sensor hardware has physically arrived. This ADR is a
design/planning artifact only — no code changes yet; see
[[hive-component-weight-tracking]] for the implementation tasks.

## Context
A weight sensor under a hive reports the *total* weight on the scale:
hive structure + bees + brood + stores. That raw number is what
`nanoprobe`'s `SensorReading.weight_kg` already stores and what the
existing weight histogram/chart already plots — but it is not, on its
own, a useful signal for "how much honey is in this hive" or "did the
colony's population change," because it conflates a large, mostly
*fixed* quantity (the hive's own empty structure) with the smaller,
*varying* quantity a beekeeper actually cares about.

The user's own framing: a hive is built from a small number of
physical components — a base plate, one or more brood chambers
(empty, or with frames and wax drawn), one or more honey chambers
(same two states) — and two hives of the same nominal size (e.g.
10×12) can still weigh differently, because one might be wood and
another styrofoam. `InventoryItem` (ADR-0027) is already the module's
general-purpose apiary-scoped catalog of physical things — the
natural place for "10×12 Brood Chamber (Wood, empty)" and "10×12
Brood Chamber (Styrofoam, empty)" to exist as two distinct catalog
rows, each with its own weight.

What's missing is two things: a weight value on a catalog item at
all, and a way to say *which* items, and how many of each, a specific
hive is actually built from — so an "empty weight" can be computed
rather than guessed.

## Decision

### 1. `weight_kg` on `InventoryItem`
A new optional field, `decimal` (`precision: 10, scale: 3`, matching
`low_stock_threshold`'s exact existing shape — kg, to gram
precision). Not gated to `category` or `item_type` — any catalog item
may carry a weight, but in practice only `category: equipment` rows
will. Migrated via a straightforward `installFieldStorageDefinition()`
update hook, the same shape task 0042's `low_stock_threshold` field
already used (`hivelog.install:830-847`) — no existing data to
migrate, since the field is new and optional.

### 2. A new entity, `HiveComponent`
The composition join, mirroring `CalendarActionItemRequirement`'s
existing two-entity-reference-plus-quantity "recipe" shape almost
exactly (`src/Entity/CalendarActionItemRequirement.php:102-171`):
- `hive` (entity_reference → hive, required)
- `item` (entity_reference → inventory_item, required)
- `quantity` — **integer, not decimal**, `min: 1`, `default: 1`,
  required. This is the one deliberate divergence from
  `CalendarActionItemRequirement`'s own `quantity` (decimal,
  precision 10/scale 3) — that field represents a *consumable*
  amount, which can be fractional (0.5kg sugar). A hive's components
  are discrete, countable equipment; "1.5 brood chambers" is not a
  real quantity, so `HiveComponent.quantity` is a plain integer.
- Same same-apiary `preSave()` guard as
  `CalendarActionItemRequirement::preSave()`
  (`src/Entity/CalendarActionItemRequirement.php:84-97`), adapted:
  `hive.apiary` must equal `item.apiary`, or throw
  `\InvalidArgumentException`. Also re-checked in the form's own
  `validateForm()`, matching the existing pattern.
- No canonical/collection route of its own — managed as an embedded
  list on the Hive canonical page, added via a scoped route
  `/hivelog/hive/{hive}/component/add`
  (`hivelog.hive_component.add`), mirroring
  `hivelog.calendar_action_item_requirement.add`'s exact shape
  (`hivelog.routing.yml:833-845`): `_entity_access: 'hive.update'` +
  `_entity_create_access: 'hive_component'`.
- Installed via its own `hivelog_update_N` calling
  `installEntityType()`, the same shape every prior new entity type
  in this module has used (`hivelog.install`, e.g. update hook 10021
  for `CalendarActionItemRequirement` itself).

### 3. `Hive::getEmptyWeightKg(): ?float`
Sums `quantity × item.weight_kg` across the hive's `HiveComponent`
rows, following `getActiveQueen()`/`getQueens()`'s existing style
(query the child storage by `hive`, no caching). **If any referenced
component's item has no `weight_kg` set, the method returns `NULL`
— not a partial sum.** A number that looks complete but silently
excludes an unweighed component is worse than an honest "unknown";
callers (the Hive page's own display, and the new nanoprobe stat
tile below) must handle the `NULL` case explicitly, not treat it as
zero or omit it silently.

### 4. Delete-dependency registration (ADR-0103's registry)
Two new rows in `HivelogDeleteDependencyRegistry`:
- CASCADE `hive` → `hive_component` (field `hive`) — a hive's
  composition rows are meaningless without it, same treatment
  `apiary` → `calendar_action` already gets.
- BLOCK `inventory_item` → `hive_component` (field `item`) — can't
  delete a catalog item some hive's composition still references,
  same treatment `inventory_item` → `calendar_action_item_requirement`
  already gets (an item can legitimately be reused across many
  hives' compositions, so CASCADE would be wrong here — deleting one
  hive's use of "10×12 Brood Chamber (Wood, empty)" must not affect
  another hive still using the same catalog row).

### 5. UI: "Hive Components" section + empty-weight display
A new section on the Hive canonical page
(`src/Controller/HiveController.php`), between the existing
entity-fields section (weight 5) and the weight histogram (weight 7)
— e.g. weight 6. A small table (item / quantity / unit weight /
subtotal) with its own Add button (the scoped route above) and
per-row Edit/Delete (the existing `hivelog:button-group` pattern),
plus the computed total: "Empty weight: X kg", or, when
`getEmptyWeightKg()` returns `NULL`, an explicit "Empty weight:
incomplete — N of M components missing a weight" state rather than
hiding the section or showing a wrong number.

### 6. Net colony + honey weight (nanoprobe)
Nothing in `nanoprobe` currently nets hive structure out of a raw
`SensorReading` — `weight_kg` is used as-is everywhere it appears
today (`SensorAlertCollector::checkWeightDrop()`'s swarm-drop
threshold, the weight histogram/`SensorTrendChartBuilder`). This ADR
adds one new computed figure: **latest raw `weight_kg` reading for
the hive's own weight-scope `SensorDevice`, minus
`Hive::getEmptyWeightKg()`** — surfaced as a new stat tile via the
existing `hook_hivelog_hive_stat_tiles()` extensibility hook (task
0110's own mechanism — no new hook needed), alongside the per-device
tiles that hook already contributes. When `getEmptyWeightKg()` is
`NULL`, or no weight-metric reading exists yet, the tile shows an
explicit "Net weight unavailable" state rather than a wrong or
missing number. **Charting a net-weight trend line (subtracting a
constant baseline across the existing histogram/chart's whole time
series) is out of scope for this ADR** — a real enhancement, but
materially harder than a single current-value tile, and not needed
for the tile itself to be useful.

## Consequences
- Positive:
  - Reuses three existing patterns end-to-end
    (`CalendarActionItemRequirement`'s entity shape, ADR-0103's
    delete-dependency registry, task 0110's stat-tile hook) rather
    than inventing new mechanisms — the smallest change that gets a
    genuinely new capability.
  - The wood-vs-styrofoam case (and any other real-world hive
    variation) falls out of the existing `InventoryItem` catalog for
    free — no new "material" concept needed on `Hive` itself.
  - The `NULL`-on-incomplete rule makes "we don't actually know this
    hive's weight yet" a first-class, visible state instead of a
    silent wrong number — the same instinct behind this module's
    existing "no data yet" vs. "no data matches" empty-state
    distinction on filtered lists (task 0132 onward).
- Negative / trade-offs:
  - A beekeeper must manually declare a hive's composition once per
    hive (and once per catalog item's weight) before any net-weight
    figure appears — no auto-detection is possible or proposed.
  - `HiveComponent.quantity`'s integer type is a narrower field type
    than `CalendarActionItemRequirement.quantity`'s decimal — a
    deliberate inconsistency between two structurally similar
    entities, documented here so it isn't mistaken for an oversight
    later.
  - The net-weight tile is a point-in-time figure only (latest
    reading), not a trend — a real limitation until/unless the
    deferred chart overlay is built.
- Follow-up tasks: see [[hive-component-weight-tracking]].

## Open questions
- **Should `HiveComponent` support a `notes` or `condition` field**
  (e.g. "this specific brood chamber is water-damaged, weighs more
  than catalog")? Not proposed here — the catalog-item weight is
  assumed representative of every unit of that catalog row; a
  per-instance override would need its own design if real-world drift
  turns out to matter. Left as a future extension, not blocking this
  ADR.
- **Should the net-weight stat tile eventually feed
  `SensorAlertCollector::checkWeightDrop()`'s swarm-drop threshold**
  (net weight is arguably a cleaner signal than raw weight for a
  sudden-drop alert, once available)? Deferred — that alert already
  exists and works reasonably on raw weight; revisit once real net-
  weight data exists to compare against, not speculatively now.

## Amendment — 2026-09-28
Before any implementation started, the user added a real-world
constraint §2's original shape missed: a `HiveComponent` row must not
be creatable/editable past how many units of that catalog item
actually exist — a beekeeper with 2 real brood chambers cannot assign
3. Folded into task 0163 directly (nothing has shipped yet), not a
new task.

**Two new `InventoryItem` computed methods**, both type-agnostic
(work the same for `consumable`/`durable`, unlike the existing
`getStockOnHand()`, which is consumable-only and returns `NULL` for
durable items — the realistic case for hive components):
- `getTotalPurchasedQuantity(): float` — sums `InventoryPurchase.quantity`
  for this item across every purchase, regardless of `item_type`. No
  such reusable total exists today; `getStockOnHand()`,
  `getWeightedAverageUnitCost()` and `getAnnualDepreciation()` each
  independently re-sum purchases for their own purpose. This is a
  genuinely new helper, not a rename of an existing one.
- `getAssignedToHivesQuantity(?int $excludeHiveComponentId = NULL): float`
  — sums `quantity` across every `HiveComponent` row referencing this
  item, across **every** hive (an item is apiary-scoped, not
  hive-scoped, so its assignments compete across every hive in that
  apiary), optionally excluding one row's own id — needed so editing
  an existing row's quantity doesn't count that row's *prior* value
  against itself.
- `getAvailableForHiveAssignmentQuantity(?int $excludeHiveComponentId = NULL): float`
  = the first minus the second. Always a real number, never `NULL`
  (unlike `getEmptyWeightKg()`'s deliberate `NULL`-on-incomplete rule
  from §3 above) — an item nobody has ever purchased has a
  perfectly meaningful availability of `0.0`, not an unknown state.

**Two-layer validation**, matching
`CalendarActionItemRequirement`'s own established same-apiary-guard
pattern exactly (`HiveComponentForm::validateForm()` +
`HiveComponent::preSave()`, not just one): if `quantity` exceeds
`item.getAvailableForHiveAssignmentQuantity($this->id())`, the form
sets a friendly inline error naming how many are actually available,
and `preSave()` throws `\InvalidArgumentException` as the defensive
guard against programmatic creation, identical in shape to the
existing same-apiary check both layers already carry.

**Item-picker scoping**: "available > 0" cannot be expressed as an
entity-query `condition()` — it's computed, not a stored field — so
it can't be folded into the existing shared `ApiaryScopedSelection`
plugin (`default:hivelog_apiary_scoped`) the way apiary-scoping and
discontinued-filtering are; that plugin is also shared with
`InventoryPurchaseForm`/`CalendarActionItemRequirementForm`/
`CalendarActionProductYieldForm`, where an availability filter would
be actively wrong (recording a purchase must never be blocked by
hive-assignment availability). A new, dedicated selection plugin,
`default:hivelog_hive_component_item`, extends
`ApiaryScopedSelection` for its existing apiary/discontinued query
scoping, then overrides `getReferenceableEntities()` to post-filter
out any item whose `getAvailableForHiveAssignmentQuantity()` is
`<= 0` and appends an "(N available)" suffix to each remaining
option's label — the same inline-hint idiom
`InventoryItemController::view()`'s existing "(Low Stock)" suffix
already establishes, extended to a new context rather than invented
fresh. The form passes the entity being edited's own id via
`#selection_settings['exclude_hive_component_id']` (mirroring how
`apiary_id` is already passed today), `NULL`/omitted on the add form.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]],
  [[0103-delete-policy-for-records-with-children]],
  [[0074-sensor-data-ingestion-architecture]],
  [[0075-sensor-hardware-and-connectivity-selection]]
- Tasks:: [[0162-inventory-item-weight-field]],
  [[0163-hive-component-entity-and-empty-weight]],
  [[0164-net-colony-honey-weight-stat-tile]]
