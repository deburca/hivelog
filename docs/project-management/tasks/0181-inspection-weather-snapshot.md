---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[weather-aware-inspection-planning]]"
area: entity
created: 2026-10-04
branch: feature/0181-inspection-weather-snapshot
release:
depends-on: ["[[0179-weather-forecast-adr-and-client]]"]
blocked-by:
---
# Task: Weather snapshot on inspection

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal C. Context for later analysis of temperament and behaviour.
Part of [[weather-aware-inspection-planning]].

## Acceptance criteria
- [ ] Optional temperature/conditions fields on `HiveInspection`, pre-filled
      from the forecast (or from nanoprobe ambient readings when
      present) and editable
- [ ] Update hook installing the new fields
- [ ] Kernel test
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[weather-aware-inspection-planning]]
- Decisions:: 
- Commits:: 
