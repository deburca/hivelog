---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0142-delete-cascade-owned-records
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Cascade-delete owned records with their parent

## Context
Implements the CASCADE rows of
[[0103-delete-policy-for-records-with-children]]. Neither a block nor a
warn-with-link can work for these, because the children are part of
the parent itself: its plan, content, telemetry or derived output.
They're either system-generated or have no delete page of their own.
They should **not** be deleted before the parent; they go with it.
*CASCADE is a recommendation in the ADR. Confirm it before starting.*

| # | Parent | Child deleted with it | Why it's owned |
|---|---|---|---|
| 2 | Apiary | CalendarAction (and their #15 / #16) | the apiary's plan. ~30 are auto-seeded by `Apiary::postSave()` → `CalendarAction::seedDefaultsForApiary()` |
| 8 | Apiary | HiveInsight (nexus) | derived AI output |
| 13 | Hive | HiveInsight (nexus) | derived AI output |
| 15 | CalendarAction | CalendarActionItemRequirement | part of the action's definition |
| 16 | CalendarAction | CalendarActionProductYield | part of the action's definition |
| 19 | Hive/ApiaryActionLog | InventoryUsage | entered inline on the log (`InventoryUsageFormTrait`) |
| 20 | Hive/ApiaryActionLog | HarvestYield | entered inline on the log (`HarvestYieldFormTrait`) |
| 27 | SensorDevice | SensorReading (nanoprobe) | raw telemetry |
| 28 | SensorDevice | SensorReadingDaily (nanoprobe) | telemetry rollups |

## Acceptance criteria
- [ ] All 9 rows registered with treatment CASCADE. Rows #8, #13, #27
      and #28 are registered by their submodules.
- [ ] Deleting the parent by **any** path (form, API, drush,
      `$entity->delete()`) deletes these children, through the
      framework's single delete-execution hook from
      [[0134-delete-dependency-framework]]. Cascades chain: apiary →
      calendar actions → their requirements / yields.
- [ ] **Safety invariant**, tested: a cascade never deletes a record
      that a BLOCK row protects. For an apiary, #2 only runs once the
      apiary passes its BLOCK checks
      ([[0141-delete-block-relationships]]), and by then no action logs
      exist (#3 / #10 / #17 / #18).
- [ ] The delete confirmation page lists "Will also be deleted" with
      counts for each cascading type (e.g. "34 calendar actions, 1,200
      sensor readings").
- [ ] Telemetry (#27 / #28) is deleted in batches (`Batch API` on the
      form path, chunked `deleteMultiple()` on programmatic paths) so a
      device with a large reading history doesn't time out or exhaust
      memory. Tested with a few thousand readings in a kernel test.
- [ ] Kernel test per row: parent deleted → children gone, and unrelated
      records of the same type untouched.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- There's no marker distinguishing seeded from user-created calendar
  actions. Both cascade (#2), and that's fine: the log BLOCK rows
  protect every calendar action that was actually *used*.
- `InventoryUsage` / `HarvestYield` reference a hive log *or* an apiary
  log (both optional fields), so each log type cascades on its own
  field.
- Key files: the framework's execution hook, nanoprobe / nexus
  registrations, `src/Form/*DeleteForm.php` (via the shared base).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0141-delete-block-relationships]]
- Commits::
