---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: routing
created: 2026-08-20
branch: feature/0047-cross-apiary-aggregate-views
release:
depends-on:
blocked-by:
---
# Task: Cross-apiary aggregate views

## Context
A direct consequence of ADR-0027's confirmed per-apiary scoping decision:
there is no "40kg of sugar across all my apiaries" or "total potential
income across all my apiaries this year" view. Explicitly flagged in
both parent projects as "could be a future report if it turns out to
matter" — not requested by real usage yet.

## Acceptance criteria
- [ ] Confirm actual demand before building — this is the most
      speculative item in this project; check with real multi-apiary
      usage (or ask directly) whether per-apiary totals are actually
      inconvenient enough to warrant a cross-apiary rollup, versus just
      opening each apiary's own financial report in turn.
- [ ] If confirmed: a new aggregate report (route + controller) summing
      `InventoryReportController`'s existing per-apiary breakdown methods
      across every apiary the current user can view, for a selected
      year — reusing `consumableCostBreakdown()`/`depreciationBreakdown()`/
      `yieldBreakdown()` per apiary rather than inventing new
      aggregation logic.
- [ ] Access-scoped: only apiaries the current user can actually view
      contribute to the total — no aggregate leaking private apiaries'
      figures to users who shouldn't see them individually.

## Implementation notes
- Key files: likely a new `AggregateInventoryReportController` alongside
  `InventoryReportController`, or a new method on that same controller —
  decide based on how much the per-apiary and cross-apiary views end up
  sharing once scoped.
- No stock-on-hand aggregate is implied here (`InventoryItem.name`
  collisions across apiaries with different units/items would need
  careful handling) — scope this to cost/income/net totals only unless
  a stock rollup is separately requested.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
