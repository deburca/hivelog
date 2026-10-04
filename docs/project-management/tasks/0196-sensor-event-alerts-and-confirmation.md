---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-event-detection]]"
area: dashboard
created: 2026-10-04
branch: feature/0196-sensor-event-alerts-and-confirmation
release:
depends-on: ["[[0195-weight-step-change-detection]]"]
blocked-by:
---
# Task: Sensor event alerts and beekeeper confirmation

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal H.
Part of [[sensor-event-detection]].

## Acceptance criteria
- [ ] Detected events raise needs-attention alerts and appear in the hive's
      Sensors panel
- [ ] Beekeeper can confirm, relabel or dismiss an event; confirming a swarm
      can create a colony event once [[colony-lifecycle-events]] exists
      (via a hook, not a hard dependency)
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[sensor-event-detection]]
- Decisions:: 
- Commits:: 
