---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: ui
created: 2026-09-20
branch: feature/0080-hive-apiary-sensors-panel
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]"]
blocked-by: ["[[0076-sensor-device-and-reading-entities]]"]
---
# Task: "Sensors" panel on the hive/apiary canonical pages

## Context
Read-only surfacing of `SensorReading` data where a beekeeper already
looks, per [[0074-sensor-data-ingestion-architecture]] §7 — extends the
existing weight-histogram pattern rather than adding a new charting
mechanism.

## Acceptance criteria
- [ ] `HiveController::view()` gains a "Sensors" section listing each
      attached, `enabled` hive-scoped `SensorDevice` with its latest
      reading per `metric`. `ApiaryController::view()` gains the
      equivalent for apiary-scoped devices.
- [ ] A trend chart per device/metric, built the same way as
      `HiveController::buildWeightHistogram()` — inline SVG, no
      charting-library dependency — fed by daily-aggregated points
      (min/max/avg) rather than raw readings, so the SVG stays small
      regardless of sampling frequency. Decide during implementation
      whether Phase-1 data volume is small enough to aggregate on read
      without a persisted rollup, or whether this task needs to borrow a
      minimal version of [[0082-sensor-reading-retention-and-rollup]]'s
      rollup early.
- [ ] `HiveInspection.weight`'s existing histogram is left completely
      untouched — the manual (hefted-by-hand) and sensor-driven weight
      data sources render as two separate charts side by side, never
      merged, per [[0074-sensor-data-ingestion-architecture]] §7.
- [ ] No add/edit UI for `SensorReading` anywhere on this panel — strictly
      read-only, matching that entity's "machine-written only" design.
- [ ] A hive/apiary with no attached devices shows an appropriate empty
      state (or the section is simply omitted) rather than an error.
- [ ] Kernel tests: panel renders the latest reading + chart for a hive
      with an attached device; correct empty behaviour for one without;
      respects `ApiaryAccessTrait` (a beekeeper without access to the
      hive/apiary cannot see its sensor data, mirroring existing access-
      parity test patterns).
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0004-custom-controllers-over-view-builders]]
- Commits::
