---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[dashboard-landing-page]]"
area: reporting
created: 2026-09-08
branch: feature/0059-combined-financial-report
release:
depends-on: ["[[0058-dashboard-visual-polish]]"]
blocked-by:
---
# Task: Combined multi-apiary financial report

## Context
Task 0058 made the dashboard "Net YTD" stat tile link to a single
apiary's financial report (`hivelog.apiary.inventory_cost_report`) when
exactly one apiary is visible. With more than one apiary the tile had no
sensible target — it fell back to the apiary list. Review feedback:

> If there is exactly one apiary the Net YTD tile should link to the
> financial report of that apiary. If there are more than one apiaries,
> then combine all apiaries into one report (preferred), or list the
> per-apiary reports one after the other.

The single-apiary half already shipped in 0058. This task adds the
combined report for the multi-apiary case (the preferred option).

## Acceptance criteria
- [x] **New route** `hivelog.apiaries.financial_report` at
      `/hivelog/apiaries/financial-report`. No `{apiary}` param; year via
      `?year=` (same ±1 clamp as the per-apiary report). Same permission
      OR-set as `hivelog.apiary.inventory_cost_report`
      (`view own inventory item+view any inventory item+administer hivelog`),
      global-permission-only, no `_entity_access` — it only ever iterates
      apiaries that pass `access('view')` in the controller.
- [x] **`InventoryReportController::combinedReport()`.** Runs the
      existing `computeApiaryYearTotals()` once per viewable apiary and
      sums it: a summary table with one row per apiary (name linked to
      that apiary's own `costReport()`, with the selected year carried
      through) plus a bold "All apiaries" total row, and a combined
      5-year trend (`buildCombinedTrendRows()`) matching the per-apiary
      report's trend shape. Empty state: "No apiaries to report on."
- [x] **`buildYearSelectorButtons()` made route-agnostic** — now takes
      `(string $route, array $route_params, int $selected_year)` so both
      reports reuse it. `costReport()`'s call updated.
- [x] **`addTotalsCacheDependencies()` helper** — folds a totals
      result's item / product entities into a `CacheableMetadata` or a
      plain list; used by `combinedReport()` and
      `buildCombinedTrendRows()` so the three-loop pattern lives once.
- [x] **`viewableApiaries()` helper** on the controller, mirroring
      `DashboardController::viewableApiaries()` (access-checked query +
      per-entity `access('view')` filter) so the report and the tile
      that links to it always cover the same set.
- [x] **Dashboard tile.** `DashboardController::buildStatTiles()` — Net
      YTD now points at `hivelog.apiaries.financial_report` when
      `count($apiaries) > 1`, still straight to
      `hivelog.apiary.inventory_cost_report` when exactly one.
- [x] Cache metadata (ADR-0009): `url.query_args:year` +
      `user.permissions` contexts; apiary / inventory_purchase /
      inventory_usage / harvest_yield list tags; per-apiary and
      per-item/product dependencies, including those walked for the
      trend.
- [x] Tests: new `CombinedFinancialReportTest` (sum across apiaries +
      "All apiaries" total, per-apiary row links, empty state, 6-row
      trend, access filtering excludes apiaries the user cannot view).
      `DashboardTest` gains `testNetYtdTileLinksToSingleApiaryReport` /
      `testNetYtdTileLinksToCombinedReportWhenMany`. phpcs clean.

## Implementation notes
- Key files: `hivelog.routing.yml`,
  `src/Controller/InventoryReportController.php`,
  `src/Controller/DashboardController.php`,
  `tests/src/Kernel/CombinedFinancialReportTest.php`,
  `tests/src/Kernel/DashboardTest.php`.
- The combined trend is a nested loop (6 years × N apiaries ×
  `computeApiaryYearTotals()`); fine at hobbyist scale. If apiary counts
  ever grow, the trend is the first thing to memoise.
- No new ADR: this is an additive report page following the established
  per-apiary report pattern (ADR-0004 custom controller, ADR-0009 cache
  discipline). ADR-0057 already covers the dashboard IA that routes the
  tile here.
- The "list per-apiary reports one after the other" fallback was not
  built — the combined report is the preferred option and each summary
  row already links to the individual report for the drill-down.

## Related
- Project:: [[dashboard-landing-page]]
- Decisions:: [[0057-dashboard-information-architecture]], [[0004-custom-controllers-over-view-builders]], [[0009-render-cacheability-discipline]], [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Depends on:: [[0058-dashboard-visual-polish]]
- Commits::
