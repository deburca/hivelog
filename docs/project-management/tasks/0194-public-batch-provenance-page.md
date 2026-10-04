---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[honey-sales-and-batch-provenance]]"
area: routing
created: 2026-10-04
branch: feature/0194-public-batch-provenance-page
release:
depends-on: ["[[0193-honey-batch-entity]]", "[[0178-printable-qr-label-sheet]]"]
blocked-by:
---
# Task: Public batch provenance page and QR

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal G.
Part of [[honey-sales-and-batch-provenance]].

## Acceptance criteria
- [ ] Opt-in per batch; an unguessable public URL (not the entity id) showing
      only ADR-approved fields
- [ ] Printable jar-label QR reusing the QR helper from [[0178-printable-qr-
      label-sheet]]
- [ ] Anonymous access test proving no other entity data is reachable
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[honey-sales-and-batch-provenance]]
- Decisions:: 
- Commits:: 
