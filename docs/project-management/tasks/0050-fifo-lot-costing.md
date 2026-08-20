---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0050-fifo-lot-costing
release:
depends-on:
blocked-by:
---
# Task: FIFO/lot costing

## Context
`InventoryItem::getWeightedAverageUnitCost()` uses a weighted average
across all purchases, not FIFO/lot tracking — an explicit, confirmed
simplicity trade-off in ADR-0027, "acceptable for a small-scale/hobbyist
tool, revisit only if it proves insufficient in practice." Recorded here
as a backlog placeholder, not because weighted-average has actually
proven insufficient.

## Acceptance criteria
- [ ] Confirm real demand first — per ADR-0027's own framing, this
      should only be picked up if weighted-average costing has caused a
      concrete, observed problem (e.g. a beekeeper who tracks purchase
      lots separately for tax reasons and finds weighted-average
      inaccurate for their bookkeeping).
- [ ] If confirmed: switching `InventoryUsage.unit_cost_snapshot`'s
      derivation from weighted-average to FIFO changes which specific
      purchase(s) a usage event "consumes" — this is a meaningfully
      different cost-basis model, not a small tweak, and would need its
      own ADR revisiting ADR-0027's costing decision rather than a quiet
      implementation change.

## Implementation notes
- Deliberately left unspecified — this is a "confirm before designing"
  placeholder, matching [[0048-unit-conversion]]'s framing.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
