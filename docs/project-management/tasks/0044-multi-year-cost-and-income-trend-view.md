---
type: task
tags: [hivelog/task]
status: done
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
- [x] The financial report gains a multi-year view — either a chart
      (mirroring the hive weight histogram's rendering approach) or a
      simple year-by-year table, showing cost/income/net across, e.g.,
      the last 5 years plus the current one. Chart vs. table is an
      implementation decision; a table is the lower-risk starting point
      if chart infrastructure doesn't generalise cleanly from the
      single-hive, single-metric histogram to this three-series,
      per-apiary case. Went with a table — the chart's single-metric,
      single-hive histogram infrastructure didn't generalise cleanly to
      this three-series (cost/income/net), per-apiary case, and a table
      is the lower-risk starting point the task itself flagged. Always
      covers the real current calendar year and the five before it,
      independent of the report's own ±1 year selector, since 5 years is
      a much wider window than that selector supports.
- [x] Years with no activity at all render as zero/empty rather than
      being skipped, so the trend line/table doesn't visually
      misrepresent a gap as "no data collected" vs. "genuinely zero
      that year".
- [x] Kernel test: an apiary with activity across three different years
      shows all three in the trend view with correct per-year totals.
- [x] `ddev drush updb -y && ddev drush cr` clean (read-side aggregation
      only, like the rest of `InventoryReportController`).

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` — added
  `buildTrendRows()` (loops `computeApiaryYearTotals()` across the
  6-year window) and factored the single-year total computation
  `costReport()` already did inline out into
  `computeApiaryYearTotals()`, shared by both, so the summary table and
  the trend table can never drift out of sync on how a total is
  derived.
- Reused the existing `hivelog-inventory-report-table` CSS class for the
  trend table rather than a new class — no CSS changes needed.
- Cache metadata: every item/product entity encountered while building
  the trend rows (across all 6 years, not just the selected one) is
  folded into the page's `CacheableMetadata`, matching what the
  single-year summary/breakdown sections already did — correctness, not
  just the list-level cache tags that would already invalidate on any
  change.
- Performance: as flagged, this multiplies the per-year query cost by 6.
  Not optimised further — acceptable at the hobbyist scale this module
  targets.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 461 tests, 0 failures/errors.
- New kernel test `testTrendTableShowsThreeYearsOfActivityWithCorrectTotals`
  covers three durable items depreciating in three different years
  within the 6-year window (each isolated to exactly one year via
  `useful_life_years = 1`), asserting correct totals for each of those
  years plus a zeroed row for a year with no activity at all.
- End-to-end smoke test via `drush php:eval`: a real apiary/durable-item/
  purchase, confirming `costReport()`'s real rendered `#rows` contain 6
  entries with the current year's depreciation reflected correctly.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits:: e5110f1 (`computeApiaryYearTotals()` refactor, `buildTrendRows()`,
  trend table, tests)
