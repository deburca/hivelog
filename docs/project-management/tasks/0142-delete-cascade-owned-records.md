---
type: task
tags: [hivelog/task]
status: done
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
- [x] All 9 rows registered with treatment CASCADE. Rows #8, #13, #27
      and #28 are registered by their submodules.
- [x] Deleting the parent by **any** path (form, API, drush,
      `$entity->delete()`) deletes these children, through the
      framework's single delete-execution hook from
      [[0134-delete-dependency-framework]]. Cascades chain: apiary →
      calendar actions → their requirements / yields.
- [x] **Safety invariant**, tested: a cascade never deletes a record
      that a BLOCK row protects. For an apiary, #2 only runs once the
      apiary passes its BLOCK checks
      ([[0141-delete-block-relationships]]), and by then no action logs
      exist (#3 / #10 / #17 / #18).
- [x] The delete confirmation page lists "Will also be deleted" with
      counts for each cascading type (e.g. "34 calendar actions, 1,200
      sensor readings").
- [x] Telemetry (#27 / #28) is deleted in batches (`Batch API` on the
      form path, chunked `deleteMultiple()` on programmatic paths) so a
      device with a large reading history doesn't time out or exhaust
      memory. Tested with a few thousand readings in a kernel test.
- [x] Kernel test per row: parent deleted → children gone, and unrelated
      records of the same type untouched.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-25.**
- **"CASCADE is a recommendation in the ADR. Confirm it before
  starting" was already settled** — task 0134's own first acceptance
  criterion required exactly this confirmation before any
  delete-dependency implementation started, and the user confirmed
  CASCADE and DETACH "as written" on 2026-09-24 (see ADR-0103's Open
  Questions, and 0134's Implementation notes). Not re-asked here.
- **Batch API vs. chunked `deleteMultiple()` — asked the user
  directly**, since the two options had a real, differently-scoped
  engineering cost: real Batch API needs a multi-request progress-bar
  flow that's hard to exercise in a kernel test and would mean
  modifying `HivelogEntityDeleteForm`, the shared base every one of the
  module's ~20 delete forms uses. Chose chunked `deleteMultiple()` for
  *both* the form and programmatic paths — the same
  `while`-loop-with-a-bounded-`range()` pattern
  `SensorReadingRetentionService::purgeOldRawReadings()` (task 0082)
  already established for exactly this "don't load everything into
  memory at once" problem. A few thousand rows chunked this way
  deletes in low single-digit seconds in the kernel test that exercises
  it (`SensorDeviceDeleteCascadeTest::testSensorDeviceDeleteCascadesLargeReadingVolume`,
  2,500 readings) — real Batch API's benefit is a UI progress bar and
  surviving PHP's max-execution-time across multiple requests, which
  only actually matters at a scale past what this task's own test
  target ("a few thousand") reaches. Recorded as a deliberate scope
  decision, not an oversight.
- **New `HivelogDeleteDependencyExecutor` service** (sibling to
  `HivelogDeleteDependencyCounter`) is what `hivelog_entity_predelete()`
  now delegates to — the hook itself is a one-line call, matching the
  "dispatch shell, not an implementation" framing task 0134 left it in.
  `cascade()` deletes a row's matching children in chunks of 50 via the
  entity API (`$storage->delete($storage->loadMultiple($ids))`), never
  a raw query — deleting each child through the entity API is what
  makes chaining automatic: a deleted `CalendarAction` (#2) re-invokes
  `hook_entity_predelete()` for itself, which finds and cascades *its
  own* `CalendarActionItemRequirement`/`CalendarActionProductYield`
  rows (#15/#16) in turn, with no special chaining logic needed anywhere
  — confirmed by `testApiaryDeleteChainsThroughCalendarActionToRequirementsAndYields()`
  and live on `cms2` (see below).
- **The safety invariant is structural, not a new check** — CASCADE
  only ever processes rows this class's own `execute()` dispatches as
  `CASCADE`; a BLOCK-registered child type (Hive, InventoryItem, …) is
  a *different* registry row entirely and is never in that dispatch, so
  it cannot be touched here regardless of how the parent's delete was
  triggered. `testCascadeNeverTouchesBlockRegisteredChildren()` proves
  this by calling `$apiary->delete()` directly (bypassing the
  access-level BLOCK check task 0141 enforces at the form/route,
  which — unlike storage-level `delete()` — Drupal's entity API doesn't
  gate by default) on an apiary that still has a Hive: the calendar
  action cascades, the Hive does not.
- **"Will also be deleted" with counts needed no new code** — task
  0134's `HivelogEntityDeleteForm::buildDependencySections()` already
  renders this section from `HivelogDeleteDependencyCounter::countsFor()`
  for every treatment, CASCADE included; this task only had to make the
  counted rows actually get deleted once confirmed.
- **The reused `installedSchemaRepository` guard** (same one task 0137
  added to `HivelogDeleteDependencyCounter` after it broke ~28 unrelated
  tests) is on `HivelogDeleteDependencyExecutor::cascade()` too, for the
  identical reason: a kernel test installing only the schemas its own
  fixtures touch must not have `hivelog_entity_predelete()` try to
  query an uninstalled child type's table just because a *different*
  row happens to be registered against the same parent type.
- **Verification**: 14 new kernel tests — 7 in
  `tests/src/Kernel/HivelogDeleteCascadeTest.php` (core's own rows #2,
  #15, #16, #19a/#19b, #20a/#20b, plus the chaining and safety-invariant
  tests), 4 in `modules/nanoprobe/tests/src/Kernel/SensorDeviceDeleteCascadeTest.php`
  (rows #27/#28, including the 2,500-reading volume test), 3 in
  `modules/nexus/tests/src/Kernel/HiveInsightDeleteCascadeTest.php`
  (rows #8/#13). Full suite: core `tests/` 713 tests (only the 3
  pre-existing, already-documented, unrelated `DashboardTest`
  Functional errors); all 4 submodules' own Kernel suites together, 238
  tests, no failures. phpcs and phpstan clean module-wide (baseline
  unchanged — no new advisory findings). Verified live on `cms2`: built
  a throwaway apiary + calendar action + inventory item + calendar
  action item requirement via `drush php-eval`, deleted the calendar
  action through the real `/hivelog/calendar-action/{id}/delete` form
  (a genuine authenticated POST, not a direct entity-API call), and
  confirmed both the calendar action and its requirement were gone
  afterward — the real HTTP path, not just the kernel harness. Did not
  additionally live-verify the submodule rows (sensor readings, hive
  insights) beyond their kernel tests — the cascading mechanism is the
  same shared `HivelogDeleteDependencyExecutor` already proven live via
  the calendar-action case, and creating+deleting more throwaway
  submodule fixtures on the shared `cms2` database for the same proof
  didn't seem worth it. All throwaway `cms2` fixtures cleaned up
  afterward — no lasting change to `cms2`'s real data.
- Key files: new `src/Delete/HivelogDeleteDependencyExecutor.php`,
  `hivelog.services.yml` (new `hivelog.delete_dependency_executor`),
  `hivelog.module` (`hivelog_entity_predelete()` now a one-line
  delegate), new `tests/src/Kernel/HivelogDeleteCascadeTest.php`, new
  `modules/nanoprobe/tests/src/Kernel/SensorDeviceDeleteCascadeTest.php`,
  new `modules/nexus/tests/src/Kernel/HiveInsightDeleteCascadeTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0141-delete-block-relationships]]
- Commits::
