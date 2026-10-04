---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[sensor-event-detection]]"
area: hardware
created: 2026-10-04
branch: feature/0197-hive-opened-detection
release:
depends-on:
blocked-by:
---
# Task: Hive-opened detection

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal H. BeeHero alerts when a hive is opened.
Part of [[sensor-event-detection]].

## Acceptance criteria
- [ ] Decide signal source (internal temperature/light transient, lid switch,
      accelerometer) against the hardware in `hardware/`
- [ ] If feasible, a detector and alert type following [[0195-weight-step-
      change-detection]]'s pattern; otherwise record why not and drop
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[sensor-event-detection]]
- Decisions:: 
- Commits:: 
