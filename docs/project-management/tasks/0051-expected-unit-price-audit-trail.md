---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0051-expected-unit-price-audit-trail
release:
depends-on:
blocked-by:
---
# Task: `Product.expected_unit_price` audit trail

## Context
An open question carried over from
[[honey-wax-propolis-yield-and-potential-income]]: `Product.expected_unit_price`
is silently overwritten on edit, with no history of what it used to be.
The project note leaned "acceptable" — no request for a price history,
and adding one would blur the line with the explicitly out-of-scope real
sales ledger ([[0046-real-sales-ledger]]) — so this task exists only as
a recorded possibility, not a recommendation to build it.

## Acceptance criteria
- [ ] Confirm this is actually wanted before implementing — the
      `unit_price_snapshot` already recorded on every `HarvestYield` row
      means past financial reports are unaffected by a later price
      change (that immutability guarantee is the point of the snapshot);
      an audit trail would only add "what did I used to think honey was
      worth" visibility, which may not be worth a new entity/log.
- [ ] If confirmed: a lightweight approach (e.g. a `revision` table or
      simply logging via `\Drupal::logger('hivelog')` on price change)
      is likely sufficient — a full revisioned entity is probably
      over-engineering for what this actually needs.

## Implementation notes
- Deliberately left unspecified — lowest-confidence item alongside
  [[0048-unit-conversion]]/[[0050-fifo-lot-costing]].

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
