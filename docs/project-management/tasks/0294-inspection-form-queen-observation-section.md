---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: form
created: 2026-11-22
branch: feature/0294-inspection-form-queen-observation-section
release:
depends-on: []
blocked-by:
---
# Task: Inspection form: optional queen observation section

## Context
From [[0111-one-place-to-record-the-queen]] (task [[0277-queen-observation-and-inspection-overlap-adr]]). Part of [[beekeeper-centred-simplification-and-gaps]].

A beekeeper who sees the queen during an inspection should not have to open a second form. The inspection form offers an optional Queen observation section that writes a `QueenObservation` in the same save.

## Acceptance criteria
- [ ] A "Queen observation" section on the inspection form, on a **new** inspection only, with Health and Actively laying (pre-filled from Eggs seen)
- [ ] It is offered only when the hive has an active queen and the user may add a queen observation
- [ ] Saving with Health or Actively laying filled in creates a `QueenObservation` for the active queen, dated the inspection date, with the inspection's temperament; validated like any write (a refusal gives a warning and the inspection is still saved)
- [ ] Editing an inspection never creates or changes an observation; no link between the two
- [ ] Kernel tests: created, not offered without a queen or without permission, not on edit, refused write warns
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Form/HiveInspectionForm.php` (the Next visit section of task 0274 is the pattern: `canAddNextVisit()`, `createNextVisitReminder()`), `src/Entity/QueenObservation.php`
- Update hook needed? No.
- The v1 API contract is unchanged.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0111-one-place-to-record-the-queen]]
- Commits::
