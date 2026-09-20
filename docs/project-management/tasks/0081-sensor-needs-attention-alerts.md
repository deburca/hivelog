---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: dashboard
created: 2026-09-20
branch: feature/0081-sensor-needs-attention-alerts
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]", "[[0080-hive-apiary-sensors-panel]]"]
blocked-by: ["[[0076-sensor-device-and-reading-entities]]"]
---
# Task: Sensor-driven "Needs attention" alert rules

## Context
Sensor anomalies as a third feed into the dashboard's existing merged-
alert-passes queue ([[0057-dashboard-information-architecture]]),
alongside the seasonal-calendar and low-stock inventory passes — not a
parallel notification system — per
[[0074-sensor-data-ingestion-architecture]] §7.

## Acceptance criteria
- [ ] A new `DashboardController` pass (sibling to
      `collectSeasonalAlerts()`/`collectLowStockAlerts()`) collects sensor
      alerts; its result is `array_merge`d into the same "Needs attention"
      queue at `DashboardController::view()`, matching the existing
      pattern exactly.
- [ ] **Device offline**: a `SensorDevice.last_seen` older than a
      threshold (proposed: 2× the device's own typical reporting
      interval, or a flat 24h fallback — confirm the exact figure against
      real sensor noise once Phase 1 data exists, per this project's own
      open question) becomes a `warning` row.
- [ ] **Sudden weight drop**: a same-day drop of roughly 1–3 kg (the
      figure [[0074-sensor-data-ingestion-architecture]] §7 cites,
      itself sourced from the Apiculture.ai research) between two
      most-recent `weight_kg` readings on the same device becomes a
      `critical` row (possible swarm).
- [ ] **Temperature out of brood range**: a sustained `temp_internal_c`
      reading outside roughly 33–36 °C becomes a `warning` row. (Nothing
      will trigger this until a temperature sensor exists — the Phase 1
      pilot is weight-only — but the code path is the same shape as the
      other two rules, so it's implemented alongside them rather than
      deferred.)
- [ ] Each rule is a simple threshold check against the most recent one
      or two `SensorReading` rows per device/metric — no forecasting or
      anomaly-detection model, consistent with
      [[0074-sensor-data-ingestion-architecture]]'s Consequences ("simple,
      explainable rules over a model," matching the seasonal calendar's
      own fixed-week-window style).
- [ ] Kernel tests mirroring `DashboardTest`'s existing seasonal-alert
      coverage: each rule fires under the right condition and doesn't
      fire otherwise, correct severity per rule, all three appear
      correctly in the merged queue alongside existing alert sources.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0057-dashboard-information-architecture]]
- Commits::
