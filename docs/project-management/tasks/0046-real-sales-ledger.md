---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0046-real-sales-ledger
release:
depends-on:
blocked-by:
---
# Task: Real sales ledger

## Context
ADR-0034 deliberately chose "potential income" (yield × a single mutable
`expected_unit_price` assumption) over an audited sales record, and both
that ADR and the [[honey-wax-propolis-yield-and-potential-income]]
project note explicitly flagged a real sales ledger — actual
transactions with buyer, date, and realized price — as a future ADR, not
this one. This task is a placeholder for that future work, not a scoped
implementation.

## Acceptance criteria
- [ ] Before any code: a new ADR revisiting ADR-0034's "aggregate
      assumption, not a ledger" decision — confirm there's real demand
      (has `expected_unit_price` proven inadequate in practice?) before
      designing a `Sale` entity. This task should not proceed past the
      ADR stage without that confirmation.
- [ ] If confirmed: a `Sale` entity mirroring `InventoryPurchase`'s shape
      inverted (product, date, quantity, actual unit price, buyer/notes)
      — the natural analogue, per ADR-0034's own framing.
- [ ] Decide whether "potential income" (current behaviour) and "actual
      income" (new ledger) both stay visible on the financial report, or
      whether actual income supersedes potential income once any real
      sales exist for a year — a real design question, not pre-decided.

## Implementation notes
- Do not start PHP implementation until the ADR above exists and is
  accepted — this task is intentionally under-specified until then.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
