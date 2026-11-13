---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/hive-page-core-stat-tiles
release: 4.3.0
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Hive page: core fact tiles and empty weight

## Context
The hive page tile row holds only submodule tiles (nexus insight, nanoprobe sensors). Beneath it the default entity view renders Name, Type, Material, Temperament and Status as label/value rows, the Queen section is a "Field / Value" table (ID, breed, colour, year, introduced), and the empty weight is a sentence above the components table ("Empty weight: incomplete — N of M components missing a weight"). That is two tables and a prose line for facts that are short and enumerated.

## Acceptance criteria
- [x] Core tiles join the existing row: Status, Type · Material, Temperament
- [x] Queen tile: value "year · colour", sub-line breed, linked to the queen; "No queen" in warning variant when none, linking to the add-queen route
- [x] Empty weight tile: value in kg; "Incomplete" in warning variant, linking to `#components`; "Not yet composed" when there are no components
- [x] The Queen section keeps its heading and buttons but loses the field table
- [x] Tile order stable and weighted before the submodule tiles (-50 to -10). The default entity view is site display config (hvg ships none, so it shows no fields); nothing to remove in code
- [x] Sensor-less hives still render sensibly
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/HiveController.php` (`view()`, `buildQueenSection()`, `buildComponentsSection()`), `src/HivelogStatTileBuilder.php`
- Decision: core tiles are **web-only** (`coreTilesForHive()` merged in `buildForHive()`); `tilesForHive()` and the app's computed endpoint are unchanged, so no API contract change. Add them to the endpoint later only if the app asks.
- `Hive::getEmptyWeightKg()` returns NULL for none/incomplete: reuse that, do not recompute.

- Dropped from the hive page with the queen table: the queen's Introduced date (still on her page). The Queen tile's sub-line carries her name instead of the old "Queen ID" row.
- The tiles use the normal tile size, not `hivelog-stat-tiles--facts`, so they match the sensor and insight tiles in the same row.
- Phone-width visual check outstanding (the in-app browser would not load the site stylesheets); markup verified on hivelog.ddev.site hive 1.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
