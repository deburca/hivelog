---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0278-supers-one-source-of-truth
release:
depends-on:
blocked-by:
---
# Task: One source of truth for the supers on a hive

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A hive's boxes are recorded twice. `HiveInspection.supers` is a bare integer typed at each
inspection; `HiveComponent` rows are the composition that `Hive::getEmptyWeightKg()` sums. Adding
a super in June means typing a number on the inspection and, separately, editing the hive's
components, or the net colony weight from the scale is wrong by the weight of the box. Most
beekeepers will do only the first.

## Acceptance criteria
- [ ] Decide the truth: proposal, the composition; the inspection offers "added / removed N of <item>" and updates the hive's components on save
- [ ] The inspection keeps a stamped supers count for history, filled from the composition, so old inspections still read correctly
- [ ] Availability rules (`HivelogHiveComponentQuantity`) apply to a change made from the inspection, with the friendly error
- [ ] Net colony weight (`nanoprobe_net_weight`) is correct after an inspection that adds a super, with no second edit
- [ ] Kernel tests; API behaviour stated (the field is in the v1 contract)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Entity/HiveComponent.php`, `src/Form/HiveInspectionForm.php`
- Update hook needed? Depends on the decision.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
