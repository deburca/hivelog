---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0048-unit-conversion
release:
depends-on:
blocked-by:
---
# Task: Unit conversion

## Context
`InventoryItem.unit`/`Product.unit` are free text with no conversion
system — a beekeeper picks one unit per item/product and stays
consistent, an explicit simplicity trade-off confirmed in both
ADR-0027 and ADR-0034. Recorded here only because the gap analysis
surfaced it as a theoretically possible future need, not because it has
been requested.

## Acceptance criteria
- [ ] Confirm real demand first — this is a low-value, high-complexity
      change (a full unit-conversion system touches every quantity field
      across `InventoryPurchase`, `InventoryUsage`,
      `CalendarActionItemRequirement`, `HarvestYield`,
      `CalendarActionProductYield`) for a hobbyist-scale tool where "pick
      one unit and stay consistent" has worked fine so far. Do not start
      without a concrete case where the current model actually broke
      down for a real user.
- [ ] If confirmed: scope carefully — likely a conversion-factor table
      per unit pair, applied only at report/comparison time, never
      mutating the stored quantity/unit on any existing record.

## Implementation notes
- Deliberately left unspecified beyond the above — this is the lowest-
  confidence, most speculative item in this project's backlog.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
