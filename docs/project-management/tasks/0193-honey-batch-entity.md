---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[honey-sales-and-batch-provenance]]"
area: entity
created: 2026-10-04
branch: feature/0193-honey-batch-entity
release:
depends-on: ["[[0192-sales-and-batch-adr]]"]
blocked-by:
---
# Task: Honey batch entity

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal G.
Part of [[honey-sales-and-batch-provenance]].

## Acceptance criteria
- [ ] Batch entity (product, harvest yields included, batch code, packed date,
      quantity) per the ADR
- [ ] Collection list, forms, access, hierarchy and delete-registry entries;
      update hook; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[honey-sales-and-batch-provenance]]
- Decisions:: 
- Commits:: 
