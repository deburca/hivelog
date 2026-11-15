---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-15
branch: feature/0285-every-harvest-carries-its-own-apiary-hive-and-date
release:
depends-on:
blocked-by:
---
# Task: Every harvest carries its own apiary, hive and date

## Context
Follows from [[0110-one-harvest-record-plan-and-fact]], accepted 2026-11-15. Part of [[beekeeper-centred-simplification-and-gaps]].

A harvest recorded directly carries `apiary`, `hive` and `harvest_date`; one reported with a
calendar action carries none of them and borrows its apiary, hive and year from its log. Every
list and report therefore joins two shapes by hand. ADR-0110 §1 makes the harvest's own fields
the truth for both kinds. This task is the storage half; nothing a beekeeper sees changes yet.

## Acceptance criteria
- [ ] `HarvestYieldFormTrait::syncHarvestYield()` sets `apiary`, `harvest_date` and (for a hive log) `hive` on the rows it writes; the log reference is kept
- [ ] `HarvestYield::preSave()` completes and checks a logged harvest as it does a direct one: the apiary is a stamp, the product must belong to it
- [ ] An update hook backfills the three fields on existing logged harvests, by direct update so `changed` is untouched, safe to run twice; the date is the Monday of the log's `week_completed`, or 1 July of its `year` without one (ADR-0110)
- [ ] `InventoryReportController::yieldBreakdown()` reads harvests by their own `apiary` and the year of `harvest_date`; the figures for every apiary and year are identical before and after (a test compares them on mixed data, including a hive that moved)
- [ ] `isDirect()` still means "no log"; `ApiaryAccessTrait::resolveApiary()` may use the harvest's own apiary
- [ ] Kernel tests; update hook tested against rows written the old way
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HarvestYield.php`, `src/Form/HarvestYieldFormTrait.php`, `src/Controller/InventoryReportController.php`, `hivelog.install`
- Update hook needed? Yes (data only; the fields exist since `hivelog_update_10039`).

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
