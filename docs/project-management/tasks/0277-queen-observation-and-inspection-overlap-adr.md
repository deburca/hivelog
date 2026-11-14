---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0277-queen-observation-and-inspection-overlap-adr
release:
depends-on:
blocked-by:
---
# Task: ADR: one place to record what was seen of the queen

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

An inspection records `queen_seen`, `eggs_seen`, `queen_cells` and `temperament`; a queen
observation records `health`, `temperament` and `active` (laying). They are separate entities
with separate forms and lists, so a beekeeper who sees the queen during an inspection must
decide which record it belongs in, or write both. Temperament alone is stored in four places:
`Hive`, `Queen`, `HiveInspection` and `QueenObservation`, with nothing keeping them in step.

## Acceptance criteria
- [ ] An ADR decides between: (a) the inspection form writes an optional queen observation in the same save, (b) observations become a section of the inspection and the separate entity is retired, (c) keep both and document when each applies
- [ ] It decides which temperament is the truth; proposal: recorded per inspection, with the hive's and queen's shown as derived from recent records, not hand-edited
- [ ] It states the effect on the v1 API contract (queen observation is an exposed resource) and on the Vinculum app
- [ ] Follow-up implementation tasks are created from the accepted ADR
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Entity/QueenObservation.php`, `src/Entity/Hive.php`, `src/Entity/Queen.php`, `modules/hivelog_api/tests/fixtures/api-v1-contract.json`
- Update hook needed? Decided by the ADR.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
