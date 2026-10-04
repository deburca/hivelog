---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-event-detection]]"
area: integration
created: 2026-10-04
branch: feature/0195-weight-step-change-detection
release:
depends-on:
blocked-by:
---
# Task: Weight step-change detection in nanoprobe

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal H.
Part of [[sensor-event-detection]].

## Acceptance criteria
- [ ] A detector over `SensorReading`/`SensorReadingDaily` weight series
      classifying sudden drops (swarm candidate), sustained loss
      (robbing candidate) and step gains/losses (supering, feeding,
      harvest)
- [ ] Detected events persisted (new entity or field — decide in task) and run
      on cron
- [ ] Thresholds configurable; tuned against `assimilate` mock data and, when
      available, [[0079-pilot-weight-sensor-hardware-build]]
- [ ] Kernel tests with synthetic series
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Lives in `modules/nanoprobe`; core must not depend on it (ADR-0098)

## Related
- Project:: [[sensor-event-detection]]
- Decisions:: 
- Commits:: 
