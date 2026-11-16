---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: routing
created: 2026-11-15
branch: feature/0287-calendar-harvest-uses-the-harvest-fields
release:
depends-on: ["[[0285-every-harvest-carries-its-own-apiary-hive-and-date]]"]
blocked-by:
---
# Task: A calendar harvest uses the same fields as Record harvest

## Context
Follows from [[0110-one-harvest-record-plan-and-fact]], accepted 2026-11-15. Part of [[beekeeper-centred-simplification-and-gaps]].

When a harvest action is reported done, the log form offers one quantity per product the action
expects, and nothing else: no date, and no way to record a product that was not planned. An action
with no expected harvest can record nothing at all, which task 0270 patched for the starter
calendar only. ADR-0110 §3 keeps the harvest in the same step as "Done" but makes it the same
fields as "Record harvest".

## Acceptance criteria
- [x] The log form's harvest block has a product, a quantity and a date. Decided: one date for the block, not one per row, because a log is one occasion
- [x] It is pre-filled with one row per expected harvest, quantity as expected, date today (or inside the log's year for a log of another year)
- [x] A product the action does not expect can be added to the block, from the apiary's active products; no JavaScript, one unplanned product a save
- [x] A row left at zero writes nothing; lowering a saved row to zero removes it
- [x] An apiary-scoped action's harvest names no hive (ADR-0110), by `HarvestYield::completeHarvest()`
- [x] The friendly errors are the entity's constraints; `validateForm()` does not repeat them. A product that is not one of the apiary's active products is ignored on save
- [x] Kernel tests: the date and the offered products, an added product, edit after save, a zero row, a product from elsewhere, a harvest action with no expected harvest (`HarvestYieldReportingIntegrationTest`)
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Form/HarvestYieldFormTrait.php`
- Update hook needed? No.
- **Where the block appears.** When the action has an expected harvest, when the log already has a harvest, or when the action's category starts with `harvest`. A varroa treatment does not grow a Harvest tab. An action of another category with no expected harvest still needs one added, or the harvest recorded directly.
- **Shared code.** The criterion asked for the block to be built by code shared with `HarvestYieldForm`. That form is an entity form with widgets; the block is plain form elements beside another entity's form, so little could be shared. What is shared is the rule: both offer only the apiary's active products, both date the harvest, and both write a `HarvestYield` completed by `completeHarvest()`.
- The date field also lets a backfilled harvest's date (1 July, where its log had no week) be corrected by editing the log.
- [[0052-merge-usage-and-yield-form-traits]] touches the same trait and is still open.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
