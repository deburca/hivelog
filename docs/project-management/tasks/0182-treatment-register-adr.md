---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[treatment-register]]"
area: docs
created: 2026-10-04
branch: feature/0182-treatment-register-adr
release:
depends-on:
blocked-by:
---
# Task: ADR: treatment record model

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal D.
Part of [[treatment-register]].

## Acceptance criteria
- [ ] ADR (next free number) deciding: treatment as its own entity vs
      extending `HiveActionLog`; link to `CalendarAction`,
      `InventoryItem`/`InventoryUsage` and inspections; required fields
      (product, batch/lot, dose, method, start/end, withdrawal period,
      supers removed, administered by)
- [ ] Delete-registry treatment (ADR-0103) for the new entity
- [ ] Decide whether reporting a treatment action log done creates a treatment
      record
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[treatment-register]]
- Decisions:: 
- Commits:: 
