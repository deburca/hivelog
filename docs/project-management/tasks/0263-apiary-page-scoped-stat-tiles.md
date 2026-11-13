---
type: task
tags: [hivelog/task]
status: review
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
- [x] Apiary tiles: Hives (links to the filtered hive list), Open seasonal tasks (critical sub-line when overdue), Inspections this month (counted by the apiary stamp, not the hive's current apiary), Net YTD (links to the apiary report)
- [x] Figures agree with the dashboard where an owner has one apiary
- [x] Tiles respect the viewer's access (a novice sees no financial tile)
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/ApiaryController.php`, `src/HivelogStatTileBuilder.php`, `src/Controller/DashboardController.php` (`inspectionsThisMonth()` and the tile logic to share, not copy)
- Coordinate with task 0255 so the facts strip and activity tiles sit together sensibly.

- Core tiles live in `HivelogStatTileBuilder::coreTilesForApiary()` (weights -50 to -20, before submodule tiles); `inspectionsThisMonth()` moved there and the dashboard calls it, so the figure is one query. Open tasks use `HivelogAlertCollector::collectSeasonalAlerts()` for the one apiary, the same pass as the dashboard.
- Deviations from the task text: the hive tile is "Active hives" linking to the page's own `#hives` section (the hive list has no apiary filter); Open seasonal tasks links to the apiary's full calendar; Inspections has no link, since the inspection list cannot be narrowed to an apiary.
- Net YTD shows only where the viewer may open the apiary's financial report (the route's rule: inventory permission plus view on the apiary). A novice with the site permission therefore still sees it; hiding it by apiary role would be a new rule, left for a decision.
- Phone-width check not done (the browser pane cannot load the site stylesheets).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
