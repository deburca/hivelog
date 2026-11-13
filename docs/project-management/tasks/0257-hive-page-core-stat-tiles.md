---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/hive-page-core-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Hive page: core fact tiles and empty weight

## Context
The hive page tile row holds only submodule tiles (nexus insight, nanoprobe sensors). Beneath it the default entity view renders Name, Type, Material, Temperament and Status as label/value rows, the Queen section is a "Field / Value" table (ID, breed, colour, year, introduced), and the empty weight is a sentence above the components table ("Empty weight: incomplete — N of M components missing a weight"). That is two tables and a prose line for facts that are short and enumerated.

## Acceptance criteria
- [ ] Core tiles join the existing row: Status, Type · Material, Temperament
- [ ] Queen tile: value "year · colour", sub-line breed, linked to the queen; "No queen" in warning variant when none, linking to the add-queen route
- [ ] Empty weight tile: value in kg; "Incomplete" in warning variant, linking to `#components`; "Not yet composed" when there are no components
- [ ] The Queen section keeps its heading and buttons but loses the field table
- [ ] The default entity view no longer repeats what the tiles show; tile order stable and weighted with the submodule tiles
- [ ] Sensor-less hives still render sensibly
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/HiveController.php` (`view()`, `buildQueenSection()`, `buildComponentsSection()`), `src/HivelogStatTileBuilder.php`
- Core tiles go through `HivelogStatTileBuilder::tilesForHive()` so the API's computed endpoint serves them too; confirm that is wanted for the app before merging (new tile keys reach it).
- `Hive::getEmptyWeightKg()` returns NULL for none/incomplete: reuse that, do not recompute.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
