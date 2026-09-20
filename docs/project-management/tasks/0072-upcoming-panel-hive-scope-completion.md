---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: dashboard
created: 2026-09-20
completed: 2026-09-20
branch: main
release: 1.8.6
depends-on: ["[[0056-dashboard-landing-page]]"]
blocked-by:
---
# Task: "Upcoming" panel never hides a fully-reported hive-scoped action

## Context
User-reported: the `/hivelog` dashboard's "Open seasonal tasks" stat tile
showed 9, but the "Upcoming" panel only listed 3 rows, with no way to
account for the other 6. Investigated live via a read-only reflection
script calling `DashboardController::collectSeasonalAlerts()` /
`buildUpcoming()` directly against production data.

Findings:
1. The 9 open tasks and the 3 visible "Upcoming" rows were unrelated sets
   — not a subset. All 9 open tasks were scheduled 6+ weeks out (beyond
   the 4-week "Upcoming" window), so correctly 0 of them should have
   appeared. Nothing wrong there.
2. The 3 rows actually shown were **stale**: hive-scoped actions where
   every hive had already reported done/ignored for the year.
   `buildUpcoming()` only checked reported status for apiary-scoped
   actions (via `reportedApiaryActionIds()`) — a hive-scoped row was never
   hidden, even once fully complete.
3. A red herring: 6 calendar actions had been reassigned from `scope:
   hive` to `scope: apiary` earlier in the year, leaving orphaned
   `HiveActionLog` rows. Confirmed harmless — each of those 6 actions also
   had a valid, correctly-closed `ApiaryActionLog` for the year, so they
   were already excluded from every count. Left alone; not worth a
   cleanup migration.

## Resolution
- Added `reportedHiveActionIds()` to `DashboardController` — mirrors
  `reportedApiaryActionIds()`, returning the set of hive-scoped action ids
  where **every** hive in the action's apiary has a non-pending
  (done/ignored) log for the year.
- `buildUpcoming()` now skips a hive-scoped row once its action id is in
  that set, alongside the existing apiary-scoped check.
- Added `hive_action_log` + `hive` list cache tags to `buildUpcoming()`'s
  cache metadata so the panel invalidates when a hive report changes it.

## Acceptance criteria
- [x] A hive-scoped action with every hive reported done/ignored for the
      year drops off "Upcoming".
- [x] A hive-scoped action with any hive still pending stays on
      "Upcoming".
- [x] `DashboardTest::testUpcomingSkipsFullyReportedHiveAction` +
      `testUpcomingKeepsPartiallyReportedHiveAction` added; full
      `tests/src/Kernel` suite green from cms2 (434 tests).
- [x] phpcs clean (`--standard=Drupal,DrupalPractice --warning-severity=0`).
- [x] Pushed directly to `main` (no schema/update-hook risk, small
      self-contained fix) — commit `749ff26`. Shipped in release 1.8.6.

## Implementation notes
- Key files: `src/Controller/DashboardController.php`
  (`buildUpcoming`, `reportedHiveActionIds`), `tests/src/Kernel/DashboardTest.php`.
- No entity schema change — no update hook.
- The 6 orphaned `HiveActionLog` rows from the earlier scope reassignment
  were left in place — confirmed inert, not part of this fix.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Follows:: [[0056-dashboard-landing-page]]
- Commits:: `749ff26` (fix + tests); release 1.8.6 bump
