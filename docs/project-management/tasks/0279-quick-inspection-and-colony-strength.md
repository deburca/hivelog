---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0279-quick-inspection-and-colony-strength
release:
depends-on:
blocked-by:
---
# Task: A quick inspection, and colony strength in frames

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

The inspection form has 22 fields in nine tabs and no short path for "all fine, queen right,
fed". At the same time it cannot hold the numbers beekeepers compare between visits: `population`
is strong / moderate / weak with no frames of bees or brood, `disease_signs` is single-valued (a
hive with chalkbrood and nosema signs can record one), and feeding is a `fed` tick with a
free-text `feed_type` and no amount ([[0230-inspection-records-items-used]] covers the stock
side).

## Acceptance criteria
- [ ] A quick mode: date, queen right (seen or eggs), stores, health, notes, with the full form one tap away; the last inspection's values offered as defaults where that is safe
- [ ] Optional `frames_of_bees` and `frames_of_brood` integers, shown as tiles and available to a trend
- [ ] `disease_signs` accepts several values; existing data preserved
- [ ] The schema endpoint and the v1 contract are extended, not broken (new optional fields; a cardinality change needs a compatibility decision)
- [ ] Update hook; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Form/HiveInspectionForm.php`, `src/Controller/HiveInspectionController.php`, `modules/hivelog_api/`
- Update hook needed? Yes.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
