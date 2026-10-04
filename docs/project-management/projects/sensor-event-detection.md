---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Sensor Event Detection

## Goal
Have `nanoprobe` detect notable events from sensor time series — a
sudden weight drop (swarm), sustained loss (robbing), step changes from
harvest/feeding/supering, and (with suitable hardware) the hive being
opened — and surface them as needs-attention alerts and hive timeline
entries the beekeeper can confirm. BroodMinder and BeeHero both alert on
these. Fits directly behind the weight-sensor pilot ([[0079-pilot-
weight-sensor-hardware-build]]).

Proposal H from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - Weight step-change detection in nanoprobe
  - Alerts + timeline entries, with confirm/dismiss and optional conversion to a colony event
  - Hive-opened detection where hardware supports it
- Out of scope:
  - Acoustic analysis
  - Changes to how `SensorReading` is ingested or stored

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0195-weight-step-change-detection]]
- [[0196-sensor-event-alerts-and-confirmation]]
- [[0197-hive-opened-detection]]

## Open questions
- Detection thresholds need real data; tune against the pilot scale and `assimilate` mock data before enabling alerts by default.

## Related decisions
- 
