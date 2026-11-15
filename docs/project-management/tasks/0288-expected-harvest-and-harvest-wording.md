---
type: task
tags: [hivelog/task]
status: backlog
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
Follows from [[0110-one-harvest-record-plan-and-fact]], which must be accepted first. Part of [[beekeeper-centred-simplification-and-gaps]].

"Product yield" on a calendar action is a plan; a "harvest yield record" is what was really
taken. The two names read as one thing, and neither is a beekeeper's word. ADR-0110 §4 names them
Expected harvest and Harvest. This can ship before the other three tasks.

## Acceptance criteria
- [ ] `CalendarActionProductYield`: label "Expected harvest", plural "expected harvests"; the calendar action page's section, its add button and the edit form's tab follow
- [ ] `HarvestYield`: label "Harvest", plural "harvests"; delete pages, messages, the dashboard's recent activity and the delete-blocked reason ("1 harvest") follow
- [ ] The help text on the expected quantity says it is an estimate and that nothing is recorded until the action is reported done
- [ ] Machine names, routes, permissions (their titles may change, their keys may not) and tables are unchanged
- [ ] Tests asserting the old labels updated; `AGENTS.md` gives the label beside each machine name
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/CalendarActionProductYield.php`, `src/Entity/HarvestYield.php`, `src/Controller/CalendarActionController.php`, `src/Form/CalendarActionForm.php`, `hivelog.permissions.yml`
- Update hook needed? No.
- Part of the wider glossary in [[0275-plain-language-vocabulary]]; this task settles these two terms so that one need not.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
