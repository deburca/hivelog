---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: routing
created: 2026-11-14
branch: feature/0271-record-a-harvest-directly
release:
depends-on: ["[[0270-starter-product-catalogue-and-harvest-yields]]"]
blocked-by:
---
# Task: Record a harvest directly from a hive or apiary

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A `HarvestYield` can only be written as a side effect of reporting a calendar action log: the
add route is `/hivelog/hive/{hive}/calendar-action/{calendar_action}/log/add` and the entity has no
form of its own. Taking two supers off in a good June, cutting comb, or collecting propolis outside
the planned weeks cannot be recorded without first inventing a calendar action and a yield recipe
for it. Harvesting is one of the five things a beekeeper does; it should be one button.
[[0230-inspection-records-items-used]] opens the same door for inventory usage.

## Acceptance criteria
- [x] A "Record harvest" button on the hive page and the apiary page opens a short form: date, product, quantity, optional notes (in a new "Harvests" section, which also lists direct harvests with Edit and Delete)
- [x] The record feeds the cost and yield reports and the apiary's Net YTD exactly as a calendar-logged yield does, counted by the apiary stamp
- [x] Decided: `HarvestYield` gains direct `hive` / `apiary` parents, a date and notes; no log is created behind the form. Recorded as an amendment to ADR-0034
- [x] `HivelogParentAccess` on the new parent references; delete-registry rows (`0034-1`, `0034-2`, BLOCK). Not exposed in `hivelog_api`, so no access-parity change
- [x] Kernel tests (`DirectHarvestTest`, fixture added to `RouteEntityAccessTest`)
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `ddev drush updb -y && ddev drush cr` clean (schema changed: `hivelog_update_10039`): not run

## Implementation notes
- Key files: `src/Entity/HarvestYield.php`, `src/Form/HarvestYieldForm.php`, `src/Controller/HarvestYieldController.php`, `src/HivelogHarvestSectionTrait.php`, `src/Controller/InventoryReportController.php`, `src/Delete/HivelogDeleteDependencyRegistry.php`, `src/HivelogEntityHierarchy.php`, `hivelog.routing.yml`, `hivelog.install`
- Update hook: `hivelog_update_10039` installs `harvest_date`, `apiary`, `hive` and `notes`.
- The Harvests section lists direct harvests only (the latest 20). Totals, and yields reported with the calendar, are [[0272-hive-harvest-history-and-totals]].
- The edit and delete routes also accept a yield that belongs to an action log; nothing links to them for such a yield.
- The dashboard's "Recent activity" already lists harvest yields and needed no change.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits:: 
