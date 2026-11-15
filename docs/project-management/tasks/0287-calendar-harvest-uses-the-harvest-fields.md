---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] The log form's harvest block has, per row, product, quantity and date, built by code shared with `HarvestYieldForm`
- [ ] It is pre-filled with one row per expected harvest, quantity as expected, date today
- [ ] A product the action does not expect can be added to the block, from the apiary's active products; no JavaScript is required to add one row
- [ ] A row left at zero writes nothing; lowering a saved row to zero removes it, as today
- [ ] An apiary-scoped action's harvest names no hive (ADR-0110)
- [ ] The friendly errors are the entity's constraints; `validateForm()` does not repeat them
- [ ] Kernel tests: expected rows, an added product, a zero row, edit after save
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Form/HarvestYieldFormTrait.php`, `src/Form/HarvestYieldForm.php`, `src/Form/HiveActionLogForm.php`, `src/Form/ApiaryActionLogForm.php`
- Update hook needed? No.
- [[0052-merge-usage-and-yield-form-traits]] touches the same trait; do one first, not both at once.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0110-one-harvest-record-plan-and-fact]]
- Commits:: 
