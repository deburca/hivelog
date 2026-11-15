---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-15
branch: feature/0286-one-list-of-harvests-and-totals-that-count-them-all
release:
depends-on: ["[[0285-every-harvest-carries-its-own-apiary-hive-and-date]]"]
blocked-by:
---
# Task: One list of harvests, and totals that count them all

## Context
Follows from [[0110-one-harvest-record-plan-and-fact]], accepted 2026-11-15. Part of [[beekeeper-centred-simplification-and-gaps]].

The Harvests section on the hive and apiary pages lists only harvests recorded directly, and the
hive's "Harvest this year" tile and Harvest History count only those. A harvest reported through
the calendar is in the financial report and nowhere else, so the section can be empty on an apiary
that has harvested, and a hive's total can be lower than the report's. Its empty message, "No
harvest has been recorded … outside the seasonal calendar", assumes the reader knows there are two
ways to record one. ADR-0110 §2.

## Acceptance criteria
- [ ] The Harvests section lists every harvest of the hive or apiary, newest first, with a Source column: the calendar action's title linking to its log, or "Recorded directly"
- [ ] Edit and Delete on a directly recorded row as today; a row from the calendar links to its log instead
- [ ] `HivelogHarvestTotals::perProduct()` counts every harvest, so the tile and the Harvest History agree with the apiary's report for the hive
- [ ] The Products table on the apiary page gains a "Harvested this year" column, per product, in the product's unit
- [ ] The empty message no longer mentions the seasonal calendar
- [ ] Kernel tests with one logged and one direct harvest of the same product
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogHarvestSectionTrait.php`, `src/HivelogHarvestTotals.php`, `src/Controller/ApiaryController.php`
- Update hook needed? No.
- Closes the "direct harvests only" note in [[0272-hive-harvest-history-and-totals]] and [[0284-harvest-totals-per-product-not-across-units]].

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
