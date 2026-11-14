---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0282-hive-type-and-material-lists
release:
depends-on:
blocked-by:
---
# Task: Hive type and material lists fit more than one region

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

`hive_type` offers `10x12`, Norwegian, Langstroth, Trugstad and "Normal"; `hive_material`
offers wood and styrofoam. National, Dadant, WBC, Warré, top-bar and Layens are missing, "Normal"
says nothing, and there is no way to say a hive is a nuc. The lists were written for one
beekeeper's yard and the application is now hosted for others.

## Acceptance criteria
- [ ] The type list covers the common frame standards plus Other; "Normal" is relabelled or mapped by an update hook, with existing values preserved
- [ ] Material gains at least polyurethane/plastic and Other
- [ ] Decide whether "nuc / full colony / observation" is a type or a separate `kind` field
- [ ] The schema endpoint serves the new options; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/Hive.php`, `hivelog.install`
- Update hook needed? Yes if stored values are renamed.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
