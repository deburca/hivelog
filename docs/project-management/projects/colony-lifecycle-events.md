---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Colony Lifecycle Events

## Goal
Record what happens to colonies over time — splits, swarms (lost or
caught), merges, deaths, requeening and moves between apiaries — and the
parent/child lineage between hives. Apiary Book tracks colony movements,
BeeHero tracks splits and location, BeeKeepPal recommends splits.
HiveLog only has a terminal `status` (dead/sold/merged) with no history
or lineage.

Proposal E from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - ADR on the event model and lineage
  - A colony event entity with a hive-page timeline
  - Hive lineage (parent hive) and moving a hive between apiaries with history preserved
- Out of scope:
  - Automatic event detection (that is [[sensor-event-detection]])
  - Genetics/breeding records beyond queen provenance

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0186-colony-lifecycle-adr]]
- [[0187-colony-event-entity-and-timeline]]
- [[0188-hive-lineage-and-moves]]

## Open questions
- Moving a hive changes its `apiary` reference — which also changes ownership/access scope and every apiary-scoped report. Should a move instead close the old hive and open a new one?

## Related decisions
- 
