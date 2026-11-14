---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0274-single-hive-reminders
release:
depends-on:
blocked-by:
---
# Task: One-off reminders for a single hive

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A `CalendarAction` is apiary-wide and week-scheduled: scope `hive` means every hive in the
apiary. The follow-ups that fill a beekeeper's notebook are about one hive and one date: check
the queen is released in five days, look for eggs in three weeks, remove the strips on the 20th.
Today they can only go in an inspection's notes, which nothing reads back.

## Acceptance criteria
- [ ] A reminder has a hive, a due date, a short text and done / dismissed
- [ ] It can be added from the hive page and at the end of the inspection form ("Next visit")
- [ ] Due and overdue reminders appear in Needs attention and on the hive page
- [ ] Decide whether this is a new small entity or a non-recurring, single-hive `CalendarAction`; record it in the task or an ADR
- [ ] Access, hierarchy, delete-registry rows and kernel tests if an entity is added
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/CalendarAction.php`, `src/HivelogAlertCollector.php`, `src/Form/HiveInspectionForm.php`
- Update hook needed? Yes if an entity or field is added.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
