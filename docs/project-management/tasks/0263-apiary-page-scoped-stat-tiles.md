---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/apiary-page-scoped-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Apiary page: scoped activity tiles

## Context
The dashboard shows global figures (Apiaries, Active hives, Inspections this month, Open seasonal tasks, Net YTD). The apiary page's tile row holds only the facts strip (task 0255) and submodule tiles, so a beekeeper cannot see the same figures for one apiary. `hook_hivelog_apiary_stat_tiles()` already exists for this.

## Acceptance criteria
- [ ] Apiary tiles: Hives (links to the filtered hive list), Open seasonal tasks (critical sub-line when overdue), Inspections this month (counted by the apiary stamp, not the hive's current apiary), Net YTD (links to the apiary report)
- [ ] Figures agree with the dashboard where an owner has one apiary
- [ ] Tiles respect the viewer's access (a novice sees no financial tile)
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/ApiaryController.php`, `src/HivelogStatTileBuilder.php`, `src/Controller/DashboardController.php` (`inspectionsThisMonth()` and the tile logic to share, not copy)
- Coordinate with task 0255 so the facts strip and activity tiles sit together sensibly.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
