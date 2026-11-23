---
type: task
tags: [hivelog/task]
status: done
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
- [x] A quick mode: date, queen right (seen or eggs), stores, health, notes, with the full form one tap away; the last inspection's values offered as defaults where that is safe
- [x] Optional `frames_of_bees` and `frames_of_brood` integers, shown as tiles and available to a trend
- [x] `disease_signs` accepts several values; existing data preserved
- [x] The schema endpoint and the v1 contract are extended (new optional fields); the `disease_signs` cardinality change is a deliberate v1 change, the app being unreleased
- [x] Update hook; kernel tests
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

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
3. **Several disease signs (decided 2026-11-23: `disease_signs` itself becomes multi-valued, and v1 is
   changed in place).** The v1 attribute turns from a string into a list of strings. The iOS app
   (Vinculum) is not released, so no new API version or alias is needed; the owner updates the app to
   match. Existing data: `hivelog_update_10043` moves the column into the field's own table, one row per
   old value, by direct insert (so `changed` and the latest-temperament cache are not touched). The form
   shows checkboxes, a `HivelogExclusiveValue` constraint refuses `none` together with another sign (for
   the API too), severity reads every value (AFB or EFB anywhere is critical) and the tile lists all the
   labels. `collective`'s inspection summary carries `disease_signs` as a list (or null).

Tests: a kernel test per part (field validation and the update hook; quick-mode form builds
the right elements and the defaults skip the unsafe fields; the second disease field and
severity). AGENTS.md: the form, the field names and the contract note.

## Outcome
Merged in PR #238 (2026-11-23), unreleased at the time of writing. Frames of bees and brood,
a quick inspection (`?mode=quick`) and several disease signs. Update hooks `10042` and `10043`.
The v1 `disease_signs` attribute is now a list, changed in place because the Vinculum app is
unreleased, so the app has to send an array and refuse "None" with another sign. Not done: the
frame fields are not in `collective`'s minimised context payload, and quick mode was tested in
kernel tests only, not in a browser. Lesson: run phpstan locally in cms2
(`vendor/bin/phpstan analyse -c web/modules/contrib/hivelog/phpstan.neon`) before pushing, since
the baseline is a ratchet.

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Form/HiveInspectionForm.php`, `src/Controller/HiveInspectionController.php`, `modules/hivelog_api/`
- Update hook needed? Yes.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
