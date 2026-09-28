---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-28
---
# Project: Hive Component Weight Tracking

## Goal
Track the weight of the physical components a hive is built from
(base plate, brood chamber empty/with frames+wax, honey chamber
empty/with frames+wax, …) as `InventoryItem` catalog entries, let each
`Hive` declare its own composition (which components, how many of
each), and compute that hive's empty (tare) weight as the sum — so
that once a real weight sensor is reporting (task 0079, hardware now
in hand), HiveLog can net the hive structure back out of a raw scale
reading and show real colony + honey weight, not just raw total
weight. See [[0106-hive-component-weight-tracking]] for the full
design.

## Scope
- In scope: an optional `weight_kg` field on `InventoryItem`; a new
  `HiveComponent` entity (hive + item + quantity, mirroring
  `CalendarActionItemRequirement`'s existing "recipe" shape); a
  computed `Hive::getEmptyWeightKg()` that returns `NULL` — not a
  partial, misleadingly-precise number — when any referenced
  component's item has no `weight_kg` set; an embedded "Hive
  Components" section on the Hive canonical page; a new `nanoprobe`
  stat tile computing net colony + honey weight (latest raw
  `weight_kg` `SensorReading` minus `getEmptyWeightKg()`) alongside
  the existing per-device tiles from task 0110.
- Out of scope: decomposing "brood chamber with frames and wax" into
  its own separate frames/wax catalog entries — the catalog holds
  each assembled state as its own item, per the user's own framing;
  overlaying a net-weight *trend line* on the existing weight
  histogram/chart (a real enhancement, but a harder one — charting a
  constant baseline subtraction across a time series — left as a
  future task, not required for this project's own payoff); any
  change to how `SensorReading` itself is stored or ingested.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

## Open questions
- None currently; see [[0106-hive-component-weight-tracking]]'s own
  Open questions for the design-level ones already resolved.

## Related decisions
- [[0106-hive-component-weight-tracking]]
