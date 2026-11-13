---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/cost-report-totals-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Cost and yield report: totals as stat tiles

## Context
The per-apiary and combined cost and yield reports open with a one-row table whose headers are "Total consumable cost, Total active depreciation, Total cost, Total potential income, Net". It wastes width, wraps badly on a phone, and is a table of one row. The dashboard already presents "Net YTD" as a tile.

## Acceptance criteria
- [ ] Per-apiary report: tile strip of Cost, Income, Net (critical sub-line when negative, ok when positive), with Consumables and Depreciation as supporting tiles
- [ ] Combined report: the same strip for all apiaries; the per-apiary table beneath it stays
- [ ] Year selector, breakdown and trend tables unchanged
- [ ] Figures identical to the previous table (compare against `computeApiaryYearTotals()`); money formatting unchanged
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` (`costReport()`, `combinedReport()`)
- Totals are already computed in one place; this changes presentation only.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
