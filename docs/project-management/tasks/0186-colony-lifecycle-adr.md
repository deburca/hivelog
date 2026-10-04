---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[colony-lifecycle-events]]"
area: docs
created: 2026-10-04
branch: feature/0186-colony-lifecycle-adr
release:
depends-on:
blocked-by:
---
# Task: ADR: colony events and hive lineage

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal E.
Part of [[colony-lifecycle-events]].

## Acceptance criteria
- [ ] ADR (next free number): event types (split, swarm lost, swarm caught,
      merge, death, requeen, move), relation to `Hive.status` and
      `Queen`, lineage field, how a move affects apiary-scoped access,
      reports and calendar logs
- [ ] Delete-registry treatment for events and lineage references
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[colony-lifecycle-events]]
- Decisions:: 
- Commits:: 
