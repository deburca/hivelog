---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: docs
created: 2026-11-22
branch: feature/0296-document-when-inspection-and-queen-observation-apply
release:
depends-on: ["[[0294-inspection-form-queen-observation-section]]", "[[0295-temperament-cache-on-inspection-and-observation-save]]"]
blocked-by:
---
# Task: Document when an inspection and a queen observation each apply

## Context
From [[0111-one-place-to-record-the-queen]] (task [[0277-queen-observation-and-inspection-overlap-adr]]). Part of [[beekeeper-centred-simplification-and-gaps]].

The two records overlap in what they describe. [[0111-one-place-to-record-the-queen]] decides the rule: an inspection is a visit to a hive; a queen observation is a note about one queen, recorded on its own when she was looked at outside an inspection and from the inspection form when she was looked at during one.

## Acceptance criteria
- [ ] The field help texts of `queen_seen`, `eggs_seen` and the observation's `active` say which record is for what
- [ ] The hive page's Add Observation button and the queen page carry a one-line hint
- [ ] `AGENTS.md` describes the rule under the Queen and QueenObservation entries
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Entity/QueenObservation.php`, `src/Controller/HiveController.php`, `AGENTS.md`
- Update hook needed? No (help text only).
- Best done after 0294 and 0295, so the text describes what exists.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0111-one-place-to-record-the-queen]]
- Commits::
