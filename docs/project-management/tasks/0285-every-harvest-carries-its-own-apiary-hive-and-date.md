---
type: task
tags: [hivelog/task]
status: done
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
- [x] A logged harvest gets `apiary`, `harvest_date` and (for a hive log) `hive` when it is created; the log reference is kept. Done in `HarvestYield::completeHarvest()` rather than in `HarvestYieldFormTrait::syncHarvestYield()`, so a script that creates one gets a complete row too
- [x] `HarvestYield::preSave()` completes and checks a logged harvest as it does a direct one: the apiary is a stamp, the product must belong to it
- [x] `hivelog_update_10040` backfills the three fields on existing logged harvests, by direct update so `changed` is untouched, safe to run twice; the date is the Monday of the log's `week_completed`, or 1 July of its `year` without one (ADR-0110)
- [x] `InventoryReportController::yieldBreakdown()` reads harvests by their own `apiary` and the year of `harvest_date`; the figures are the same before and after (tested on mixed data, including a hive that moved)
- [x] `isDirect()` still means "no log"; `ApiaryAccessTrait::resolveApiary()` uses the harvest's own apiary, falling back to its product's
- [x] Kernel tests (`HarvestStampTest`); update hook tested against rows written the old way
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [x] `ddev drush updb -y && ddev drush cr` clean: run on the local hvg site, 2026-11-15

## Implementation notes
- Key files: `src/Entity/HarvestYield.php`, `src/Controller/InventoryReportController.php`, `src/ApiaryAccessTrait.php`, `hivelog.install`
- Update hook: `hivelog_update_10040` (data only; the fields exist since `hivelog_update_10039`).
- Checked on the local hvg data: 3 calendar harvests backfilled. Income by apiary and year through the logs before (apiary 1: 5850.00, apiary 4: 1349.00) equals income by the harvests' own fields after, once the 3825.00 of direct harvests in apiary 1 is added. `changed` was not touched.
- Two details beyond the ADR's wording:
  - A new logged harvest is dated today only when its log is for the current year. A log for another year (last year's action reported late) gets a date inside that year by the backfill rule, so it counts in the year it belongs to.
  - ISO week 1 can start in the previous December; the date is then 1 January, to keep the harvest in the log's year.
- A harvest whose log has been deleted without it (an orphan) has nothing to take an apiary from and stays out of the reports, as it was before.
- Nothing a beekeeper sees changes here; the single list and the totals are [[0286-one-list-of-harvests-and-totals-that-count-them-all]].

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
