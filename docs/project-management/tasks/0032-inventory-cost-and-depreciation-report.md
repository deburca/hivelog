---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[inventory-tracking-and-depreciation]]"
area: routing
created: 2026-08-19
branch: feature/0032-inventory-cost-and-depreciation-report
release:
depends-on: ["[[0031-inventory-usage-and-action-log-reporting-integration]]"]
blocked-by:
---
# Task: Per-apiary, per-year inventory cost & depreciation report

## Context
The payoff of [[0027-inventory-tracking-and-depreciation]]'s whole design:
a report showing, per apiary per year, the total cost of consumables used
plus the active depreciation of durable assets — the figure that should
trend downward over time as durable purchases finish depreciating,
per the ADR's "production becomes more profitable" premise. This task
covers cost only; it explicitly does not attempt profitability (revenue −
cost), since Hivelog has no harvest-quantity/sales model yet — see the
project note's scope.

## Acceptance criteria
- [x] `InventoryItem::getAnnualDepreciation(int $year): float` — sums,
      across every `durable` `InventoryPurchase` for the item,
      `total_cost / useful_life_years` for each purchase whose window
      (`purchase year` through `purchase year + useful_life_years − 1`)
      includes `$year`.
  - [x] `InventoryReportController::depreciationBreakdown()` resolves the
        same across every durable item in an apiary, for report
        aggregation.
- [x] A report page (route + controller) at
      `/hivelog/apiary/{apiary}/inventory/cost-report` showing, for a
      selected year: total consumable cost (`Σ InventoryUsage.quantity ×
      unit_cost_snapshot` for usage rows whose log's `year` matches),
      total active depreciation (from the helper above), and their sum,
      with a breakdown table by item. Linked from the apiary page's
      Inventory heading via a "View Cost Report" button.
- [x] A year selector (previous/current/next), matching
      `HiveController::extractCalendarFilters()`'s existing year-clamping
      logic (`InventoryReportController::extractReportYear()`) rather
      than inventing a new pattern.
- [x] Kernel tests (`InventoryCostReportTest`, 7 tests): depreciation
      calculation across purchase-year boundaries (a purchase made
      partway through its life correctly stops contributing after
      `useful_life_years` full years), multiple purchases of the same
      durable item at different times/costs summed independently,
      consumable cost aggregation matching a hand-computed expected total
      (via the real `HiveActionLogForm` save path), the report's empty
      state when an apiary has no inventory activity yet for the
      selected year, and the out-of-range year query parameter falling
      back to the current year.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php` (add the depreciation
  method), `src/Controller/InventoryReportController.php` (new),
  `hivelog.routing.yml`.
- This task is intentionally last and lowest-priority in the project — it
  has no schema of its own and is purely a read-side aggregation over
  everything [[0028-inventory-item-and-purchase-entities]] through
  [[0031-inventory-usage-and-action-log-reporting-integration]] already
  built. It can slip without blocking the rest of the project from being
  useful (recording purchases and usage has value on its own, even before
  a report exists to summarise them).
- Revisit the project's open question about low-stock warnings here too —
  this report page is a natural place to also surface "you're low on X"
  once stock-on-hand is already being computed for the breakdown table.

## Verification
- Full kernel+unit suite against `cms2` (MySQL): 383 tests, 0 failures
  attributable to this change (the same two pre-existing local-sqlite-only
  anomalies from task 0031's close-out — unrelated `QueenTest`/
  `ApiaryCalendarChecklistTest` — still don't reproduce under CI's MySQL
  backend).
- End-to-end smoke test via `drush php:eval`: created a consumable item
  (purchased 20L @ 2.0, used 5L via a real "done"
  `HiveActionLogForm::save()`) and a durable item (purchased for 500,
  5-year useful life), rendered the report for the current year, and
  confirmed the breakdown table showed Sugar Syrup — 10.00 and
  Extractor — 100.00, with a correct grand total of 110.00.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: b1a36ff (depreciation method, report controller, route,
  apiary-page link, tests)
