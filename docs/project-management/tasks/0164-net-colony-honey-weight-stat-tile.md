---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-component-weight-tracking]]"
area: entity
created: 2026-09-28
branch: feature/0164-net-colony-honey-weight-stat-tile
release:
depends-on: ["[[0163-hive-component-entity-and-empty-weight]]"]
blocked-by:
---
# Task: Net colony + honey weight stat tile

## Context
The payoff of [[hive-component-weight-tracking]]
([[0106-hive-component-weight-tracking]] Decision §6): a raw
`SensorReading.weight_kg` is total-on-the-scale weight; net it against
`Hive::getEmptyWeightKg()` (task 0163) to get the figure a beekeeper
actually cares about — how much colony + honey is really in the hive.
Surfaced as a new `nanoprobe` stat tile alongside the existing
per-device tiles `SensorPanelBuilder::buildHiveStatTiles()` already
builds (task 0110), reusing `hook_hivelog_hive_stat_tiles()` — no new
hook.

## Acceptance criteria
- [ ] `SensorPanelBuilder::buildHiveStatTiles(Hive $hive)` gains one
      additional tile, keyed `nanoprobe_net_weight`, computed from:
      the hive's own `weight`-`device_type`, `scope: hive` accessible
      `SensorDevice`'s latest `weight_kg` `SensorReading` (reuse
      `getLatestReadingPerMetric()`/`loadAccessibleDevices()`'s
      existing helpers — do not duplicate that query), minus
      `Hive::getEmptyWeightKg()`.
- [ ] Three distinct states, each with its own explicit `sublabel`
      (never a silently wrong or silently missing number):
      - **Normal**: both a raw weight reading and a non-`NULL`
        `getEmptyWeightKg()` exist — tile shows the net figure
        (`value`/`label`/`sublabel`/`weight` shaped like
        `buildDeviceStatTile()`'s own return array), sublabel notes
        how long ago the underlying reading was taken (same
        `formatTimeDiffSince()` pattern).
      - **No weight sensor yet**: no accessible weight-scope
        `SensorDevice`/reading exists for this hive — tile omitted
        entirely (matching `buildDeviceStatTile()`'s own "return NULL,
        get filtered out" convention for "nothing to show" — not an
        error state).
      - **Composition incomplete**: a weight reading exists but
        `getEmptyWeightKg()` is `NULL` — tile still renders (a
        beekeeper with a working sensor and an unfinished hive
        composition should see *why* net weight isn't available, not
        have it silently vanish), `value` reads something like "—" or
        "Unknown", `sublabel` "Hive composition incomplete" linking to
        the Hive Components section (task 0163) `sublabel_variant:
        'warning'`.
- [ ] Kernel tests covering all three states.
- [ ] Verified live on `cms2`: a hive with both a real weight-sensor
      reading and a complete composition shows a correct net figure;
      a hive with a reading but incomplete composition shows the
      warning state; a hive with no weight sensor shows no tile at
      all (unchanged from today).
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key file: `modules/nanoprobe/src/SensorPanelBuilder.php`
  (`buildHiveStatTiles()` + a new protected method for the net-weight
  tile, following `buildDeviceStatTile()`'s own structure).
- No entity schema change, no new hook → **no update hook required**.
- **Explicitly out of scope** (ADR-0106 §6): overlaying a net-weight
  trend line on the existing weight histogram/`SensorTrendChartBuilder`
  chart — this task is the single current-value tile only.
- `nanoprobe` already depends on `hivelog` (core), so calling
  `Hive::getEmptyWeightKg()` (task 0163, core) from this submodule
  needs no new dependency — same relationship every other
  `nanoprobe`→`hivelog` core call already has.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0106-hive-component-weight-tracking]]
- Tasks:: [[0163-hive-component-entity-and-empty-weight]],
  [[0110-hive-apiary-stat-tiles]]
- Commits::
