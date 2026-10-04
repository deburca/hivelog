---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[honey-sales-and-batch-provenance]]"
area: docs
created: 2026-10-04
branch: feature/0192-sales-and-batch-adr
release:
depends-on: ["[[0046-real-sales-ledger]]"]
blocked-by:
---
# Task: ADR: sales ledger and honey batches

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal G. Satisfies the ADR prerequisite recorded on [[0046-real-
sales-ledger]].
Part of [[honey-sales-and-batch-provenance]].

## Acceptance criteria
- [ ] ADR (next free number) revisiting ADR-0034: batch/lot model from
      `HarvestYield`, `Sale` entity shape, potential vs actual income on
      the financial report
- [ ] Privacy rules for a public provenance page (what location detail is
      shown)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[honey-sales-and-batch-provenance]]
- Decisions:: 
- Commits:: 
