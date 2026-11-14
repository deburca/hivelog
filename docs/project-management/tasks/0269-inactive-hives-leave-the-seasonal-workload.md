---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0269-inactive-hives-leave-the-seasonal-workload
release:
depends-on:
blocked-by:
---
# Task: A dead, sold or merged hive leaves the seasonal workload

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

`Hive.status` offers `active`, `inactive`, `dead`, `sold` and `merged`, but nothing outside the
field definition reads the three terminal values. `HivelogAlertCollector::collectSeasonalAlerts()`
loads every hive of the apiary with no status condition, so a colony that died in March keeps
raising "Needs attention" rows and open hive-scoped calendar tasks all year. `Hive` has no
`preSave()`, so its queen stays `active` and the hive page still shows her as the current queen.
Only the "Active hives" counts (`HivelogStatTileBuilder`, `DashboardController`) filter on status.
A beekeeper who records a loss expects the hive to go quiet, not to be nagged about it.

## Acceptance criteria
- [ ] Hives whose status is not `active` are left out of seasonal alerts, the open-task counts and the apiary's hive-scoped checklist totals (decide and document whether `inactive` counts as out)
- [ ] The hive page of a non-active hive shows its calendar as history only, with no open tasks
- [ ] Setting a hive to `dead`, `sold` or `merged` retires its active queen (status `inactive`, `hive` reference kept), through the form and the API alike
- [ ] Apiary and collection hive lists hide non-active hives by default, with a filter to show them
- [ ] `computed/alerts` in `hivelog_api` returns the same narrowed rows
- [ ] Kernel tests for alerts, counts and the queen retirement
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogAlertCollector.php`, `src/HivelogCalendarChecklistBuilder.php`, `src/Entity/Hive.php`, `src/HiveListBuilder.php`, `src/Form/HivelogHiveFilterForm.php`
- Update hook needed? No schema change. An end date for the colony belongs to [[0187-colony-event-entity-and-timeline]], not here.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
