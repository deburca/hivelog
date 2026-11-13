---
type: task
tags: [hivelog/task]
status: review
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
- [x] Per-apiary report: tile strip of Cost, Income, Net (critical sub-line when negative, ok when positive), with Consumables and Depreciation as supporting tiles
- [x] Combined report: the same strip for all apiaries; the per-apiary table beneath it stays
- [x] Year selector, breakdown and trend tables unchanged
- [x] Figures identical to the previous table (compare against `computeApiaryYearTotals()`); money formatting unchanged
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` (`costReport()`, `combinedReport()`)
- Totals are already computed in one place; this changes presentation only.

- Decisions: the strip keeps the old table's column order (consumables, depreciation, cost, income, net) so figures read left to right as before, with the net last and tinted. A negative net says "Loss" (critical), a positive one "Surplus" (ok), zero says nothing.
- The combined report's table is unchanged, including its "All apiaries" total row, so with one visible apiary the strip repeats the table's figures; dropping that total row is a one-line follow-up if it feels redundant (the existing combined-report test pins it, so it was left alone).
- `buildTileStrip()` now accepts a NULL entity for computed-only strips.
- Phone-width visual check outstanding (the in-app browser would not load the site stylesheets); both reports' markup checked on hivelog.ddev.site.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
