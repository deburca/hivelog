---
type: task
tags: [hivelog/task]
status: in-progress
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

## Plan (2026-11-23)
Three independent parts, each its own commit, in this order so the riskiest comes last.

1. **Frames of bees and brood (additive, no compatibility risk).** Two optional `integer` base
   fields on `HiveInspection`, `frames_of_bees` and `frames_of_brood` (min 0, max 40), in the
   Colony condition tab beside `population`. `population` stays: it is the quick judgement, the
   frames are the count. Tiles in the Colony condition strip (no severity: what is many depends
   on the season and the hive size). Update hook `hivelog_update_10042` installs both fields;
   the v1 contract fixture gains both names; `HivelogApiSchemaTest` covers them by itself.
   Trend: a small "frames over time" table on the hive page is **not** in scope; the fields are
   queryable and `collective`'s context builder gets them (it already reads `population`).
2. **Quick mode.** The same form and route (`hivelog.inspection.add`), with `?mode=quick`. It
   shows one flat group, not the tabs: date, Queen seen, Eggs seen, Honey stores, Population,
   Temperament, Notes, plus the Queen observation and Next visit sections already on the form.
   A "Full form" link goes to the same route without the parameter and a "Quick inspection"
   link the other way. Defaults from the hive's last inspection, only where safe: honey and
   pollen stores, population, supers. Never queen seen, eggs seen, varroa, disease, fed or
   notes, because a stale "yes" there is a false record. Quick mode only hides fields, so a
   quick inspection saves the same entity and the API is untouched. The full form stays the
   default (decided 2026-11-23); the hive page gets a Quick inspection button beside Add inspection.
3. **Several disease signs (decided 2026-11-23: make `disease_signs` itself multi-valued).**
   The compatibility cost is accepted: the v1 attribute changes from a string to an array, which
   the contract test and the iOS app (Vinculum) read as one string. So this part starts with a
   short ADR (0112) that records the decision and how it ships (a new `API_VERSION` and prefix,
   or an alias that keeps serving v1 a single string), and it is **not released before Vinculum
   is ready for it**. Existing data: one row per old value (`hivelog_update_10044`, `none` kept
   only when it is the sole value; the form refuses `none` together with another sign). Severity
   reads every value (AFB or EFB anywhere is critical).

Tests: a kernel test per part (field validation and the update hook; quick-mode form builds
the right elements and the defaults skip the unsafe fields; the second disease field and
severity). AGENTS.md: the form, the field names and the contract note.

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Form/HiveInspectionForm.php`, `src/Controller/HiveInspectionController.php`, `modules/hivelog_api/`
- Update hook needed? Yes.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
