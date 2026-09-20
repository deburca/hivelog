---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-20
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
- [ ] A cron-driven job purges raw `SensorReading` rows older than a
      rolling window (proposed: 2 years — confirm the exact figure during
      implementation; [[0074-sensor-data-ingestion-architecture]] §5
      fixes the policy, not the number).
- [ ] Before purge, a daily min/max/avg rollup per device/metric/day is
      computed and persisted. Storage mechanics (a new
      `SensorReadingDaily` entity vs. a computed aggregate table) are
      left open by the ADR — decide here, following
      [[0003-code-defined-entity-schema]] if a new entity is chosen.
- [ ] [[0080-hive-apiary-sensors-panel]]'s trend chart is updated to read
      from the rollup for dates past the raw-retention window, rather
      than assuming raw rows always exist.
- [ ] Kernel tests: purge respects the configured retention window and
      never deletes a row still inside it; rollup values are correct
      (min/max/avg match the source raw rows before they're purged);
      chart rendering still works once raw rows for an older date are
      gone, using the rollup instead.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- Not blocking for the Phase 1 pilot — pick up once device count or data
  age actually approaches a real concern, per
  [[0074-sensor-data-ingestion-architecture]] §5/§8. Left without a hard
  `blocked-by` for that reason; it only genuinely needs `SensorReading`
  to exist ([[0076-sensor-device-and-reading-entities]]) and somewhere to
  demonstrate the rollup being consumed
  ([[0080-hive-apiary-sensors-panel]]).

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0003-code-defined-entity-schema]]
- Commits::
