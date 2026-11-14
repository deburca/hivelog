---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-14
branch: feature/0272-hive-harvest-history-and-totals
release:
depends-on: ["[[0271-record-a-harvest-directly]]"]
blocked-by:
---
# Task: Harvest history and totals on the hive page

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

Yields are stored per hive (through `hive_action_log`), yet the hive page shows nothing about
them: `HiveController` never reads `harvest_yield`, and the only place a harvest appears is the
apiary's financial report. "Which hive gave the most honey last year?" is the question behind
most requeening and split decisions and cannot be answered. [[0253-hive-page-operational-dashboard]]
names "historical weight/harvest" as a need without scoping the data.

## Acceptance criteria
- [ ] A Harvest section on the hive page: per product, this year's total and a per-year history
- [ ] A "Harvest this year" stat tile among the hive's core tiles (web only, like the other core tiles)
- [ ] The apiary page's hive table gains a "Harvest this year" column, or the report gains a per-hive comparison
- [ ] Counts follow the apiary stamp, so a moved hive keeps its history
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Controller/HiveController.php`, `src/HivelogStatTileBuilder.php`, `src/Controller/ApiaryController.php`
- Update hook needed? No.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
