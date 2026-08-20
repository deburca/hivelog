---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-and-yield-improvements]]"
area: routing
created: 2026-08-20
branch: feature/0044-multi-year-cost-and-income-trend-view
release:
depends-on:
blocked-by:
---
# Task: Multi-year cost & income trend view

## Context
The entire premise behind
[[inventory-tracking-and-depreciation]]/[[honey-wax-propolis-yield-and-potential-income]]
was "production becomes more profitable as years go by" as durable
assets finish depreciating. The financial report
(`InventoryReportController::costReport()`) only ever shows one year at
a time — the trend it was built to demonstrate is invisible without
manually clicking through years and comparing numbers by hand.
`HiveController` already has a chart pattern to reuse
(`buildWeightHistogram()`/`collectHistogramPoints()`), so this isn't a
from-scratch build.

## Acceptance criteria
- [ ] The financial report gains a multi-year view — either a chart
      (mirroring the hive weight histogram's rendering approach) or a
      simple year-by-year table, showing cost/income/net across, e.g.,
      the last 5 years plus the current one. Chart vs. table is an
      implementation decision; a table is the lower-risk starting point
      if chart infrastructure doesn't generalise cleanly from the
      single-hive, single-metric histogram to this three-series,
      per-apiary case.
- [ ] Years with no activity at all render as zero/empty rather than
      being skipped, so the trend line/table doesn't visually
      misrepresent a gap as "no data collected" vs. "genuinely zero
      that year".
- [ ] Kernel test: an apiary with activity across three different years
      shows all three in the trend view with correct per-year totals.
- [ ] `ddev drush updb -y && ddev drush cr` clean (read-side aggregation
      only, like the rest of `InventoryReportController`).

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` — extend
  `costReport()` or add a sibling method; reuses
  `consumableCostBreakdown()`/`depreciationBreakdown()`/`yieldBreakdown()`
  per year, called in a loop across the year range rather than once.
- Watch performance: this multiplies the existing per-year query cost by
  however many years are shown. Fine at hobbyist scale (a handful of
  years, a handful of apiaries) but worth a sanity check if it turns out
  slow in practice.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
