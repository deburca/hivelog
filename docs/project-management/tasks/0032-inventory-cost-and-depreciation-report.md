---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `InventoryItem::getAnnualDepreciation(int $year): float` (or a
      standalone service/utility method) — sums, across every `durable`
      `InventoryPurchase` for the item, `total_cost / useful_life_years`
      for each purchase whose window (`purchase year` through
      `purchase year + useful_life_years − 1`) includes `$year`.
  - [ ] A helper resolving the same across every item in an apiary,
        not just one item, for report aggregation.
- [ ] A report page (route + controller, e.g.
      `/hivelog/apiary/{apiary}/inventory/cost-report`) showing, for a
      selected year: total consumable cost (`Σ InventoryUsage.quantity ×
      unit_cost_snapshot` for usage rows whose log's `year` matches),
      total active depreciation (from the helper above), and their sum,
      with a breakdown table by item.
- [ ] A year selector (previous/current/next), matching the existing
      calendar checklist's year-switching UX
      (`HiveController::extractCalendarFilters()`) rather than inventing
      a new pattern.
- [ ] Kernel tests: depreciation calculation across purchase-year
      boundaries (a purchase made partway through its life should still
      correctly stop contributing after `useful_life_years` full years),
      multiple purchases of the same durable item at different times/costs,
      consumable cost aggregation matching a hand-computed expected total,
      the report's empty state when an apiary has no inventory activity
      yet for the selected year.

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

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
