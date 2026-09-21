---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: dashboard
created: 2026-09-20
completed: 2026-09-21
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

**Same architectural mismatch as [[0080-hive-apiary-sensors-panel]]**:
this task's acceptance criteria predates
[[0098-nanoprobe-collective-locutus-submodule-split]] and reads as if
`DashboardController` could query `SensorDevice`/`SensorReading`
directly — it can't, since core must never depend on the optional
`nanoprobe` submodule. Resolved by extending
[[0099-submodule-canonical-page-panel-hook]]'s established
hook-contribution pattern to the dashboard queue, rather than inventing
a second mechanism.

## Acceptance criteria
- [x] A new `DashboardController` pass (sibling to
      `collectSeasonalAlerts()`/`collectLowStockAlerts()`) collects sensor
      alerts; its result is `array_merge`d into the same "Needs attention"
      queue at `DashboardController::view()`, matching the existing
      pattern exactly. **Implemented as `collectSensorAlerts()`, a thin
      wrapper around a new hook** (`hook_hivelog_needs_attention_alerts()`,
      documented in `hivelog.api.php`) — `nanoprobe` implements it
      (`nanoprobe.module`), delegating to a new `SensorAlertCollector`
      service (`modules/nanoprobe/src/SensorAlertCollector.php`). Core
      has zero PHP reference to `nanoprobe`; `invokeAll()` returns an
      empty array when no module implements the hook.
- [x] **Device offline**: `SensorDevice.last_seen` older than a flat 24h
      threshold becomes a `warning` row. **Decision: flat 24h, not "2×
      the device's typical interval"** — no field persists a per-device
      reporting interval today (`SensorDevice::
      DEFAULT_SUGGESTED_INTERVAL_SECONDS` is only ever used to build a
      config descriptor, never stored on the entity), so the flat
      fallback the acceptance criteria itself already offered is what's
      actually implementable; confirming/refining against real Phase 1
      sensor noise is still open, per this project's own tracked open
      question. A device that has **never** reported (`last_seen` empty)
      does not fire — that's "not yet provisioned," not "offline," and
      alerting on it would false-alarm every freshly-registered device.
- [x] **Sudden weight drop**: a same-day drop of 1.5kg (the middle of the
      cited 1–3kg range) or more between the two most-recent `weight_kg`
      readings on the same device becomes a `critical` row (possible
      swarm). "Same-day" is a literal same-calendar-date check on
      `recorded`, not an elapsed-time window.
- [x] **Temperature out of brood range**: implemented alongside the other
      two, exactly as instructed, even though nothing triggers it yet
      (Phase 1 pilot is weight-only). Fires a `warning` row only when
      the two most recent `temp_internal_c` readings are **both** outside
      33–36°C — "sustained," not a single noisy blip, using exactly "the
      most recent one or two `SensorReading` rows" the acceptance
      criteria itself specifies as the intended check depth.
- [x] Each rule is a simple threshold check against the most recent one
      or two `SensorReading` rows per device/metric — no forecasting or
      anomaly-detection model.
- [x] Kernel tests
      (`modules/nanoprobe/tests/src/Kernel/SensorAlertCollectorTest.php`,
      12 tests): each rule fires under the right condition and doesn't
      fire otherwise (including the two "almost but not quite" cases —
      a drop under threshold, a single stray out-of-range reading);
      correct severity per rule (`warning`/`warning`/`critical`); a
      disabled device contributes nothing; the hook is actually
      dispatched by `\Drupal::moduleHandler()` (not just the service in
      isolation); and — matching the acceptance criteria's explicit "all
      three appear correctly in the merged queue alongside existing
      alert sources" — one test renders the real
      `DashboardController::view()` output (with `nanoprobe` enabled)
      and confirms a sensor alert and a low-stock alert both appear
      together.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Second application of [[0099-submodule-canonical-page-panel-hook]]'s
  pattern**, not a new ADR — the mechanism (core defines + invokes a
  hook via `moduleHandler()->invokeAll()`, an optional submodule
  implements it, core has zero dependency either way) is identical to
  the Hive/Apiary panel case; only the extension *point* differs (a
  queue to `array_merge` into, rather than a page section to splice in).
  `hivelog.api.php` documents both hooks together.
- `DashboardController::attentionContext()` (the apiary/hive-links +
  detail line every alert row uses) is `protected` on a core class —
  `SensorAlertCollector::attentionContext()` is an independent,
  byte-for-byte-identical reimplementation (same Twig template, same
  `.hivelog-attention__ctx` CSS class) rather than shared code, so a
  sensor row renders visually indistinguishable from a seasonal or
  low-stock row.
- **Alert actions link to existing pages, not anything new**: "Device
  offline" links to the device's own canonical page
  ([[0078-sensor-device-configuration-descriptor]]) when the user has
  update access to it, falling back to the apiary otherwise; "Possible
  swarm" and "Temperature out of range" link to the hive's canonical
  page (or the apiary's, for an apiary-scoped device) — which now has
  the Sensors panel from [[0080-hive-apiary-sensors-panel]] to actually
  go look at, rather than a bespoke sensor-specific action UI this task
  never asked for.
- **Sort tiers**: "Possible swarm" (`critical`) sits at tier `0`,
  alongside overdue seasonal actions — a possible swarm is genuinely as
  urgent as an overdue task, arguably more so. "Device offline" and
  "Temperature out of range" (`warning`) sit at tier `2`, alongside
  low-stock — see `DashboardController::attentionTiming()` for the
  existing tier numbering this reuses rather than inventing a fourth.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0057-dashboard-information-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0099-submodule-canonical-page-panel-hook]]
- Commits::
