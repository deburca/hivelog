---
type: task
tags: [hivelog/task]
status: review
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
- [x] `SensorPanelBuilder::buildHiveStatTiles(Hive $hive)` gains one
      additional tile, keyed `nanoprobe_net_weight`, computed from:
      the hive's own `weight`-`device_type`, `scope: hive` accessible
      `SensorDevice`'s latest `weight_kg` `SensorReading` (reuse
      `getLatestReadingPerMetric()`/`loadAccessibleDevices()`'s
      existing helpers — do not duplicate that query), minus
      `Hive::getEmptyWeightKg()`.
- [x] Three distinct states, each with its own explicit `sublabel`
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
- [x] Kernel tests covering all three states.
- [x] Verified live on `cms2`: a hive with both a real weight-sensor
      reading and a complete composition shows a correct net figure;
      a hive with a reading but incomplete composition shows the
      warning state; a hive with no weight sensor shows no tile at
      all (unchanged from today).
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key file: `modules/nanoprobe/src/SensorPanelBuilder.php` —
  `buildHiveStatTiles()` now merges in a `nanoprobe_net_weight` tile
  from a new protected `buildNetWeightTile(Hive $hive, array $devices)`,
  reusing the already-loaded, access-filtered `$devices` array
  `buildHiveStatTiles()` itself builds (no extra `loadAccessibleDevices()`
  call) and `getLatestReadingPerMetric()` for the weight device's
  latest `weight_kg` reading (no new query pattern). Weight `11` —
  right after the raw weight device's own tile (weight `10`), so the
  two read together on the page.
- `src/Controller/HiveController.php`'s `buildComponentsSection()`
  gained `'#attributes' => ['id' => 'components']` on the section's
  outer container, so the "composition incomplete" tile's
  `#components` fragment link actually resolves to something on the
  page.
- The three states map directly onto the acceptance criteria: no
  weight-scope device/reading → `NULL`, filtered out by
  `buildHiveStatTiles()` exactly like `buildDeviceStatTile()`'s own
  "nothing to show" case; a reading but `Hive::getEmptyWeightKg() ===
  NULL` → an "Unknown" tile, `sublabel_variant: 'warning'`, linking to
  `entity.hive.canonical#components`; both present → the net figure
  (`reading − empty weight`), `sublabel_variant: 'default'`, linking to
  the weight device's own `entity.sensor_device.readings` page (same
  target `buildDeviceStatTile()`'s tile for that device already links
  to). Deliberately no fourth "stale" state — the raw-weight tile for
  the same device already surfaces staleness on its own sublabel.
- No entity schema change, no new hook → **no update hook required**.
- **Explicitly out of scope** (ADR-0106 §6): overlaying a net-weight
  trend line on the existing weight histogram/`SensorTrendChartBuilder`
  chart — this task is the single current-value tile only.
- `nanoprobe` already depends on `hivelog` (core), so calling
  `Hive::getEmptyWeightKg()` (task 0163, core) from this submodule
  needs no new dependency — same relationship every other
  `nanoprobe`→`hivelog` core call already has.
- Tests: 3 new methods in `modules/nanoprobe/tests/src/Kernel/SensorPanelBuilderTest.php`
  (`testNetWeightTileAbsentWithoutWeightReading`,
  `testNetWeightTileShowsUnknownWhenCompositionIncomplete`,
  `testNetWeightTileShowsNetFigureWhenComplete`), one per state —
  the last one adds `InventoryItem`/`InventoryPurchase`/`HiveComponent`
  fixtures (schema installs for `inventory_item`, `inventory_purchase`,
  `hive_component` added to the class's own `setUp()`), following the
  same fixture shapes `HiveComponentTest.php` (core) already
  establishes. All 21 tests in that file pass (18 pre-existing + 3
  new); no regressions in the rest of the Hive-related suite.
- phpcs clean, phpstan clean — no baseline changes needed (this
  task's changes triggered zero new findings).
- Full suite (kernel + unit + functional, core + all four submodules):
  1,121 tests / 18,860 assertions / 0 failures / 0 errors (up from
  1,118 before this task) — only pre-existing deprecation noise
  (`key` module's attribute-discovery deprecation, unrelated to this
  change).
- Live-verified on `cms2` (`vdg` site, `drupal-cms2.ddev.site`, where
  `nanoprobe` is enabled): built a throwaway Apiary → Hive → weight
  `SensorDevice` → `SensorReading` (`weight_kg: 42.0`). With no Hive
  Components yet, the hive page showed "Unknown / Net Colony Weight /
  Hive composition incomplete" (warning) linking to
  `/hivelog/hive/34#components`. Added an `InventoryItem`
  (`weight_kg: 4.25`) + `InventoryPurchase` + a `HiveComponent`
  (quantity 2) — the tile then correctly showed "33.5 kg" (42.0 − 2 ×
  4.25 = 33.5), default variant, linking to
  `/hivelog/sensor-device/11/readings`. Deleted the `HiveComponent`
  again and reloaded to reconfirm the warning state returns. All
  throwaway fixtures (apiary, hive, device, reading, item, purchase)
  deleted afterward and confirmed gone by re-loading both entities.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0106-hive-component-weight-tracking]]
- Tasks:: [[0163-hive-component-entity-and-empty-weight]],
  [[0110-hive-apiary-stat-tiles]]
- Commits::
