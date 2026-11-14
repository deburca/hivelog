---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] A "Record harvest" button on the hive page and the apiary page opens a short form: date, product, quantity, optional notes
- [ ] The record feeds the cost and yield reports and the apiary's Net YTD exactly as a calendar-logged yield does, counted by the apiary stamp
- [ ] Decide in the task (or a short ADR amending ADR-0034) whether `HarvestYield` gains a direct `hive` / `apiary` parent or an unplanned log is created behind the form
- [ ] `HivelogParentAccess` on the new parent reference; delete-registry rows; access parity if exposed in `hivelog_api`
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HarvestYield.php`, `src/Form/HarvestYieldFormTrait.php`, `src/Controller/InventoryReportController.php`, `hivelog.routing.yml`
- Update hook needed? Yes if `HarvestYield` gains fields (date, parent reference).

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
