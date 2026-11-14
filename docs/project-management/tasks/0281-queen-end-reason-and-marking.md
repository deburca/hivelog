---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0281-queen-end-reason-and-marking
release:
depends-on:
blocked-by:
---
# Task: Why a queen's reign ended, and whether she is marked

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A queen is `active` or `inactive` and that is all. When she is demoted (by a new queen in
`Queen::preSave()`, or by hand) nothing records when or why: died, superseded, swarmed, replaced,
sold. `queen_colour` is derived from the year, but whether she is actually marked or clipped is
not recorded, though it decides how hard she is to find. [[colony-lifecycle-events]] plans a
requeening event; this is the queen's side of it.

## Acceptance criteria
- [ ] `ended_date` and `end_reason` (allowed values) on the queen, required when status becomes `inactive` through a form, filled with "replaced" on automatic demotion
- [ ] `marked` and `clipped` booleans; the Year · Colour tile says "unmarked" when she is not
- [ ] Coordinated with [[0187-colony-event-entity-and-timeline]] so one action does not need two entries
- [ ] Update hook; schema endpoint and v1 contract extended; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/Queen.php`, `src/Form/QueenForm.php`, `src/Controller/QueenController.php`
- Update hook needed? Yes.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
