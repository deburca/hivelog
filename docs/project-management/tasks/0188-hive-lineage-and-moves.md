---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[colony-lifecycle-events]]"
area: entity
created: 2026-10-04
branch: feature/0188-hive-lineage-and-moves
release:
depends-on: ["[[0186-colony-lifecycle-adr]]"]
blocked-by:
---
# Task: Hive lineage and moves between apiaries

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal E.
Part of [[colony-lifecycle-events]].

## Acceptance criteria
- [ ] Optional parent-hive reference (split/swarm origin) shown on the Hive
      page with links to children
- [ ] A Move hive action implementing the ADR's chosen semantics, recording a
      move event
- [ ] Access, breadcrumb and apiary-scoped reports verified after a move;
      kernel tests
- [ ] Update hook
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[colony-lifecycle-events]]
- Decisions:: 
- Commits:: 
