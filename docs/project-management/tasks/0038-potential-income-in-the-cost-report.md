---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[honey-wax-propolis-yield-and-potential-income]]"
area: routing
created: 2026-08-20
branch: feature/0038-potential-income-in-the-cost-report
release:
depends-on: ["[[0037-harvest-yield-and-action-log-reporting-integration]]"]
blocked-by:
---
# Task: Potential income in the per-apiary cost report

## Context
The payoff of this whole project, per
[[0034-honey-wax-propolis-yield-and-potential-income]]: the existing
per-apiary, per-year cost report
([[0032-inventory-cost-and-depreciation-report]]) gains a potential-income
figure and a net (income − cost) figure, finally giving the
cost-vs-income-vs-net view the original inventory project's premise was
framed around. Unlike every other task in this project, this one extends
an existing controller rather than adding a new one.

## Acceptance criteria
- [x] `InventoryReportController::yieldBreakdown(Apiary $apiary, int
      $year): array` (or equivalently named, following the file's
      existing `consumableCostBreakdown()`/`depreciationBreakdown()`
      naming) — sums `Σ HarvestYield.quantity × unit_price_snapshot` for
      yield rows whose owning log (hive- or apiary-scoped) belongs to
      this apiary and matches `$year`, using the identical two-query join
      (apiary-scoped logs directly, hive-scoped logs via the apiary's
      hives) `consumableCostBreakdown()` already performs — reuse that
      exact join shape rather than inventing a new one.
- [x] `InventoryReportController::costReport()`'s summary table gains two
      columns: total potential income, and net (income − total cost,
      where total cost = consumable cost + depreciation as already
      computed). Net is signed — a loss year shows as a negative number,
      not hidden or floored at zero (see the parent project's open
      question, resolved here: hiding a loss would be misleading for a
      profitability report).
- [x] The breakdown-by-item table gains yield rows (product, "Yield",
      quantity produced, income) alongside the existing consumable/
      durable rows — one combined table, not a second separate table, so
      the page reads as one coherent per-item ledger.
- [x] Route name and controller method (`hivelog.apiary.inventory_cost_report`,
      `costReportTitle()`) unchanged — this task extends the existing page,
      it does not add a new one. The judgement call on whether "Inventory
      Cost Report" was still the right *text* was resolved: renamed to
      "Apiary Financial Report" (and the apiary page's "View Cost Report"
      button to "View Financial Report" for consistency), since the page
      now covers both cost and income.
- [x] Kernel tests: potential income aggregation matching a
      hand-computed expected total (mirroring
      `InventoryCostReportTest::testReportAggregatesConsumableCostMatchingHandComputedTotal`),
      net figure correctness including a loss-year case (income < cost,
      confirming the net renders as a signed negative number, not
      zero-floored or hidden), the report's still-correct empty state
      when an apiary has no yield activity for the selected year (cost
      figures alone must still render correctly).
- [x] `ddev drush updb -y && ddev drush cr` clean (no schema change
      expected here — purely a read-side aggregation extension, like
      0032 itself).

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` only —
  no new files expected.
- Extracted `apiaryYearLogIds(Apiary $apiary, int $year): array{apiary_log_ids, hive_log_ids}`
  as a shared private helper, used by both `consumableCostBreakdown()`
  and `yieldBreakdown()` — the two now differ only in which storage
  (`inventory_usage` vs. `harvest_yield`) and which snapshot field
  (`unit_cost_snapshot` vs. `unit_price_snapshot`) they sum.
- Cache metadata: added `harvest_yield`'s list cache tags and each
  rendered `HarvestYield` row's `Product` cache dependency, mirroring how
  `inventory_usage`/`InventoryItem` were already handled.
- The combined breakdown table's header changed from `Quantity used`/
  `Cost` to `Quantity`/`Amount`, since it now also carries "produced"
  yield rows, not just "used" consumable/durable rows. No test asserted
  the old header text, so this was a clean rename.
- The empty-state message changed from "No inventory cost or
  depreciation recorded for @year." to "No inventory cost, depreciation,
  or yield recorded for @year." — the two existing assertions in
  `InventoryCostReportTest` that checked for the old text were updated
  to match.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 439 tests, 0 failures/errors (up from 437
  before this task).
- `ddev drush cr` clean — no schema change, as expected for a pure
  read-side aggregation extension.
- End-to-end smoke test via `drush php:eval`: a real apiary with a
  reported feeding action (10 kg sugar used, weighted-average cost 2.0),
  a durable extractor purchase (500 over a 5-year life), and a reported
  harvest action (15 kg honey produced at an expected price of 12) —
  confirmed the summary table showed Total consumable cost 20.00, Total
  active depreciation 100.00, Total cost 120.00, Total potential income
  180.00, Net 60.00, and the combined breakdown table listed all three
  rows (Sugar/Consumable, Extractor/Durable, Honey/Yield) correctly —
  then cleaned up.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits:: ca444c7 (yieldBreakdown(), shared apiaryYearLogIds() helper,
  summary/breakdown table changes, page title/button rename, tests)
