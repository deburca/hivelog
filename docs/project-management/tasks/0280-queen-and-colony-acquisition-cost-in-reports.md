---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0280-queen-and-colony-acquisition-cost-in-reports
release:
depends-on:
blocked-by:
---
# Task: Queen and colony purchases count in the cost report

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

`Queen.purchase_cost` and `purchase_date` are shown on the queen page and read nowhere else:
`InventoryReportController` totals inventory only. A bought queen, nuc or package is a real
yearly cost and often the largest after equipment, so Net YTD overstates the surplus. There is
no place at all to record what a bought colony cost.

## Acceptance criteria
- [ ] Queen purchase costs appear in the apiary's cost report and Net YTD for the year of `purchase_date`, attributed to the apiary of the queen's hive at purchase
- [ ] Decide how a bought colony (nuc, package, swarm) is costed: a field on the hive, or a purchase of an inventory item; document it
- [ ] The totals strip and the combined report include the new line
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php`, `src/Entity/Queen.php`
- Update hook needed? Only if a hive acquisition cost field is added. A queen has no apiary stamp today; the report needs one or a documented rule.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
