---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/queen-origin-tile
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Queen page: Origin as a stat tile

## Context
The queen page's facts are a tile strip (task 0258), but a "Details" table below it still holds **Origin** and **Notes**. Origin is a short string (max 255 characters: breeder, swarm, supplier), so it is a fact and fits a tile; Notes is prose (handled by task 0266). Reported by the user on `/app/queen/1`.

## Acceptance criteria
- [x] Origin is a tile in the queen's strip, `omit_empty` so an unset origin leaves no tile; the tile cuts at 80 characters by the strip helper's rule (the full value stays on the edit form)
- [x] Place it after Breed/Temperament and before Introduced, or where it reads best in the strip
- [x] The Details table no longer lists Origin (what remains is Notes, replaced by the prose block in task 0266; if this task lands first, the table shows Notes only and is removed by 0266)
- [x] Tests updated: `QueenTest` strip assertions include the Origin tile, absent when empty
- [x] AGENTS.md queen-page sentence updated (Origin is a tile; only Notes remains as text)
- [ ] Phone width (≤480px) checked

## Implementation notes
- Key file: `src/Controller/QueenController.php` (`view()`, the `buildTileStrip` array and the `$build['details']` section).

- Origin sits after Temperament and before Introduced. The Details table is gone; the queen page is now the strip, the Notes block and the observations list.
- Phone width not checked (the browser pane cannot load the site stylesheets).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
