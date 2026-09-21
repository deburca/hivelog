---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-20
completed: 2026-09-21
branch: feature/0082-sensor-reading-retention-and-rollup
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]", "[[0080-hive-apiary-sensors-panel]]"]
blocked-by:
---
# Task: Sensor reading retention purge + daily rollup

## Context
Retention policy decided (but not built) in
[[0074-sensor-data-ingestion-architecture]] §5: raw `SensorReading` rows
must not be allowed to grow unbounded forever, but a long-term trend must
still be answerable after older raw rows are purged. Explicitly **not**
required for the Phase 1 pilot — a handful of devices for a few months
won't hit the volume where this matters — but required before general
rollout, so it's tracked now rather than discovered as a problem later.

## Acceptance criteria
- [x] A cron-driven job (`nanoprobe_cron()` → `SensorReadingRetentionService::
      runDailyMaintenance()`) purges raw `SensorReading` rows older than a
      rolling window — **730 days (2 years)**, the figure
      [[0074-sensor-data-ingestion-architecture]] §5 itself proposed;
      that ADR fixed the policy, not the number, so this is the
      confirmed-during-implementation figure, as a documented, adjustable
      constant (`SensorReadingRetentionService::RAW_RETENTION_DAYS`).
- [x] Before purge, a daily min/max/avg rollup per device/metric/day is
      computed and persisted. **Decision: a new `SensorReadingDaily`
      entity** (`modules/nanoprobe/src/Entity/SensorReadingDaily.php`),
      following [[0003-code-defined-entity-schema]] — not a computed
      aggregate table — so it gets the same apiary-scoped access model
      as every other hivelog entity for free via `ApiaryAccessTrait`
      (extended with a new `sensor_reading_daily` branch, in `hivelog`
      core per ADR-0098 §6's established precedent). Reuses
      `SensorReading`'s own `view own/any sensor reading` and
      `delete own/any sensor reading` permissions rather than minting
      new ones — a rollup is the same underlying data at a coarser
      grain, not a distinct capability to grant separately. Added via a
      real update hook (`nanoprobe_update_10001()`), since `nanoprobe`
      is no longer a brand-new module — unlike 0076's entities, which
      installed automatically on first enable.
- [x] [[0080-hive-apiary-sensors-panel]]'s trend chart
      (`SensorPanelBuilder::buildTrendChart()`) is updated to read from
      the rollup for dates past the raw-retention window. Refactored into
      `collectDailyAggregatesFromRaw()` (unchanged on-read aggregation,
      for the portion of the window still within
      `RAW_RETENTION_DAYS`) and `collectDailyAggregatesFromRollup()`
      (reads `SensorReadingDaily` directly for any older portion). In
      practice `TREND_WINDOW_DAYS` (30) sits far inside
      `RAW_RETENTION_DAYS` (730), so the rollup branch never actually
      fires with today's constants — it exists for correctness if either
      constant changes, verified by a test that calls the method with an
      explicit wider window (via reflection, since production code never
      needs one), not by anything currently reachable through the panel
      itself.
- [x] Kernel tests
      (`SensorReadingDailyTest.php`, `SensorReadingRetentionServiceTest.php`,
      plus one new test in `SensorPanelBuilderTest.php`): purge respects
      the configured retention window (keeps rows inside it, deletes rows
      past it); rollup values are exactly correct (min/max/avg computed
      from real raw rows, verified before they're purged); rollup is
      idempotent (recomputing updates the existing row rather than
      duplicating it — simulates a late-arriving reading for an
      already-rolled-up date); a reading recorded "today" is never rolled
      up (it's still accumulating); and — the acceptance criterion's own
      explicit case — chart rendering is proven to still work once raw
      rows for an older date are **actually deleted** (not just assumed
      gone), reading the rollup instead.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Deliberately simple rollup computation, not optimised for scale**:
  every cron run recomputes the rollup for every device/metric/date that
  still has raw data (except today), rather than tracking a "last rolled
  up" watermark to skip already-stable historical days. Correct and
  cheap at current volumes (this task itself is explicitly not required
  until "general rollout," per [[0074-sensor-data-ingestion-architecture]]
  §8) — revisit for efficiency only if it ever shows up as slow in
  practice, matching the same trade-off already accepted in
  `SensorPanelBuilder`'s on-read aggregation.
- **First use of Drupal's cron API in this codebase** (`nanoprobe_cron()`)
  — no prior precedent to follow; a plain `hook_cron()` delegating
  straight to a service, the simplest shape that fits.
- A genuine test bug caught along the way, worth recording: the first
  user created within a single kernel test method becomes uid 1, which
  bypasses Drupal's permission checks entirely (core behaviour, not a
  bug in this module) — an access test asserting a user *lacks* a
  permission must use a user created *after* the first one, or the
  assertion silently passes for the wrong reason. Fixed in
  `SensorReadingDailyTest::testApiaryScopedAccessReusesSensorReadingPermissions()`.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0003-code-defined-entity-schema]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Commits::
