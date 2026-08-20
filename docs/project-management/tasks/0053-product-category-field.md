---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0053-product-category-field
release:
depends-on:
blocked-by:
---
# Task: `Product.category` field

## Context
`InventoryItem` has a `category` field (`feed`/`treatment`/`packaging`/
`equipment`/`other`); `Product` deliberately does not — task 0035's
implementation notes reasoned that with only honey/wax/propolis in
scope, `Product.name` already *is* the category distinction, and adding
one would be premature for a catalog this small. Recorded here as a
placeholder in case the product catalog grows.

## Acceptance criteria
- [ ] Confirm real need first — only pick this up once a beekeeper's
      product catalog has grown enough (multiple honey varietals? candles
      alongside wax blocks?) that grouping/filtering by category would
      actually help, not on a "might as well" basis.
- [ ] If confirmed: mirror `InventoryItem.category`'s exact shape
      (`list_string`, optional, allowed-values list) — the categories
      themselves would need fresh thinking, not reuse of
      `InventoryItem`'s feed/treatment/packaging/equipment/other list,
      which doesn't fit sellable outputs at all.

## Implementation notes
- Deliberately left unspecified until real demand exists.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
