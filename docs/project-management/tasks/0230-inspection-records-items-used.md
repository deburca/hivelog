---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-25
branch: feature/0230-inspection-records-items-used
release:
depends-on: ["[[0224-permission-granularity-by-role]]"]
blocked-by:
---
# Task: Record items used on a hive inspection

## Context
Split out of [[0224-permission-granularity-by-role]] (decision of 2026-10-25). Day-to-day
work often uses stock during an inspection, for example 5 kg of sugar, but inventory usage
can today only be written as a side effect of reporting a calendar job
(`InventoryUsageFormTrait`, linking to exactly one of `hive_action_log` or
`apiary_action_log`) and has no screen of its own.

"Single-use" items are the existing `consumable` item type; `durable` items have no usage.

## Acceptance criteria
- [ ] The inspection form has a repeatable "items used" part (consumable item and quantity)
- [ ] Saving the inspection writes inventory usage for each line, so stock on hand falls
- [ ] Usage can link to an inspection (today: exactly one of the two action logs; the rule and
      the delete-dependency rows need extending)
- [ ] A stock check: what happens when the quantity is more than stock on hand
- [ ] Editing an inspection updates its usage; deleting one removes it (and stock is restored)
- [ ] Items offered are the apiary's own consumables only
- [ ] Access follows the apiary role (beekeeper and owner may write; novice reads)
- [ ] Update hook and API contract/schema reviewed if the usage entity changes
- [ ] Tests; phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed

## Implementation notes
Open design points to settle when this is picked up: whether usage also needs the apiary
stamped on it (see the history decision in 0224); whether several inspections of the same
hive on the same day may each record usage; whether the same usage can be both on an
inspection and on a reported job (double counting).

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]]
- Commits::
