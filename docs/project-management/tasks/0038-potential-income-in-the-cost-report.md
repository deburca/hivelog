---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `InventoryReportController::yieldBreakdown(Apiary $apiary, int
      $year): array` (or equivalently named, following the file's
      existing `consumableCostBreakdown()`/`depreciationBreakdown()`
      naming) — sums `Σ HarvestYield.quantity × unit_price_snapshot` for
      yield rows whose owning log (hive- or apiary-scoped) belongs to
      this apiary and matches `$year`, using the identical two-query join
      (apiary-scoped logs directly, hive-scoped logs via the apiary's
      hives) `consumableCostBreakdown()` already performs — reuse that
      exact join shape rather than inventing a new one.
- [ ] `InventoryReportController::costReport()`'s summary table gains two
      columns: total potential income, and net (income − total cost,
      where total cost = consumable cost + depreciation as already
      computed). Net is signed — a loss year shows as a negative number,
      not hidden or floored at zero (see the parent project's open
      question, resolved here: hiding a loss would be misleading for a
      profitability report).
- [ ] The breakdown-by-item table gains yield rows (product, "Yield",
      quantity produced, income) alongside the existing consumable/
      durable rows — one combined table, not a second separate table, so
      the page reads as one coherent per-item ledger.
- [ ] Route/title unchanged (`hivelog.apiary.inventory_cost_report`,
      `costReportTitle()`) — this task extends the existing page, it does
      not add a new one. Consider whether "Inventory Cost Report" is
      still the right page title/heading now that it covers income too
      (e.g. "Apiary Financial Report" or similar) — a judgement call to
      make while implementing, not pre-decided here.
- [ ] Kernel tests: potential income aggregation matching a
      hand-computed expected total (mirroring
      `InventoryCostReportTest::testReportAggregatesConsumableCostMatchingHandComputedTotal`),
      net figure correctness including a loss-year case (income < cost,
      confirming the net renders as a signed negative number, not
      zero-floored or hidden), the report's still-correct empty state
      when an apiary has no yield activity for the selected year (cost
      figures alone must still render correctly).
- [ ] `ddev drush updb -y && ddev drush cr` clean (no schema change
      expected here — purely a read-side aggregation extension, like
      0032 itself).

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php` only —
  no new files expected.
- Reuse `consumableCostBreakdown()`'s exact hive-log/apiary-log join
  pattern for yield, rather than writing a third parallel query
  implementation — the only difference is which entity type
  (`inventory_usage` vs. `harvest_yield`) and which snapshot field
  (`unit_cost_snapshot` vs. `unit_price_snapshot`) is being summed.
  Consider whether a small shared private helper (e.g. resolving "this
  apiary's hive-scoped and apiary-scoped log ids for a year") is worth
  extracting at this point, now that three call sites
  (`consumableCostBreakdown()`, `depreciationBreakdown()` doesn't need
  it, the new yield breakdown does) would share it — judgement call, not
  mandatory.
- Cache metadata: add `harvest_yield`'s list cache tags and each rendered
  `HarvestYield`/`Product`'s own cache dependency, mirroring how
  `inventory_usage`/`InventoryItem` are already handled in this
  controller's existing cache metadata block.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits::
