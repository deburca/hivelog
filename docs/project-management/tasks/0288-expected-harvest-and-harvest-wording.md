---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-15
branch: feature/0288-expected-harvest-and-harvest-wording
release:
depends-on:
blocked-by:
---
# Task: Say "Expected harvest" for the plan and "Harvest" for the fact

## Context
Follows from [[0110-one-harvest-record-plan-and-fact]], accepted 2026-11-15. Part of [[beekeeper-centred-simplification-and-gaps]].

"Product yield" on a calendar action is a plan; a "harvest yield record" is what was really
taken. The two names read as one thing, and neither is a beekeeper's word. ADR-0110 §4 names them
Expected harvest and Harvest. This can ship before the other three tasks.

## Acceptance criteria
- [x] `CalendarActionProductYield`: label "Expected harvest", plural "expected harvests"; the calendar action page's section ("Expected Harvest"), its add button, the three route titles and the edit form's tab ("Required items & expected harvest") follow
- [x] `HarvestYield`: label "Harvest", plural "harvests"; delete pages, messages, the dashboard's recent activity, the report's row type and the delete-blocked reason ("1 harvest") follow
- [x] The help text on the expected quantity says it is an estimate and that nothing counts as harvested until the action is reported done
- [x] Machine names, routes, permission keys and tables are unchanged; only permission titles changed
- [x] Tests asserting the old labels updated; `AGENTS.md` gives the label beside each machine name
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/CalendarActionProductYield.php`, `src/Entity/HarvestYield.php`, `src/Controller/CalendarActionController.php`, `src/Controller/InventoryReportController.php`, `src/Controller/DashboardController.php`, `src/Form/CalendarActionForm.php`, `src/Form/HarvestYieldFormTrait.php`, `src/Form/CalendarCopyForm.php`, `hivelog.routing.yml`, `hivelog.permissions.yml`
- Update hook needed? No. A cache rebuild picks up the entity labels and route titles.
- The log form's block is now titled "Harvest" (was "Yield Produced").
- Left as they were: exception messages for callers that never validate, and old update hooks' result messages.
- Part of the wider glossary in [[0275-plain-language-vocabulary]]; this task settles these two terms so that one need not.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
