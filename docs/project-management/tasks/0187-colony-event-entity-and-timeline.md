---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[colony-lifecycle-events]]"
area: entity
created: 2026-10-04
branch: feature/0187-colony-event-entity-and-timeline
release:
depends-on: ["[[0186-colony-lifecycle-adr]]"]
blocked-by:
---
# Task: Colony event entity and hive timeline

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal E.
Part of [[colony-lifecycle-events]].

## Acceptance criteria
- [ ] Entity per [[0186-colony-lifecycle-adr]] with scoped add route;
      recording a death/merge offers to update `Hive.status`
- [ ] A Colony history section on the Hive page (top-level H2) merged
      chronologically with inspections where sensible
- [ ] Access handler, hierarchy entries, delete-registry rows, update hook,
      kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[colony-lifecycle-events]]
- Decisions:: 
- Commits:: 
