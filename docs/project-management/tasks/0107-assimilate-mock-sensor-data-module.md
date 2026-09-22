---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-22
branch: feature/0107-assimilate-mock-sensor-data-module
release:
depends-on: ["[[0106-sensor-device-management-ui]]"]
blocked-by: ["[[0106-sensor-device-management-ui]]"]
---
# Task: `assimilate` — dev-only mock sensor data generator

## Context
Raised alongside [[0105-submodule-navigation-menu-links]] and
[[0106-sensor-device-management-ui]] during manual UI validation: a
beekeeper (or anyone evaluating HiveLog) currently has no way to see
what the Sensors panel, the dashboard, or a `nexus`-generated AI
insight actually look like without first owning real hardware. A
`test`/`development`-only module that fabricates plausible
`SensorReading` data — and, on a regular cron cadence, keeps adding
more — closes that gap: it gives `nanoprobe`'s panels real data to
render and `nexus`'s insight generation real context to reason over,
without waiting on [[0079-pilot-weight-sensor-hardware-build]] or any
future hardware purchase.

**Depends on [[0106-sensor-device-management-ui]]**, not because
`assimilate` needs that UI itself (it provisions its own devices
programmatically, same as every other module in this project's test
suites do today) — but because demonstrating "here's what it looks like
once you add a sensor" is hollow if the UI to actually add one still
doesn't exist. Do this after, not instead of, closing that real gap.

## Scope
- **Strictly a `test`/`development`-package module** — matches
  `hivelog.info.yml`'s own `package: Custom` convention, but flagged in
  its own `.info.yml` `package` and `description` as dev/demo-only, not
  something to enable on a real beekeeper's production site. Exact
  enforcement mechanism (a loud admin-page warning, a hard refusal to
  install if `ai_insights_enabled` is already on for a real apiary, or
  just documentation) is this task's own design decision, not fixed
  here — but *some* real guardrail is required, not documentation alone,
  given the risk of contaminating genuine sensor/insight data if
  accidentally left enabled.
- Provisions its own `SensorDevice`(s) against apiaries/hives the site
  already has (or a dedicated demo apiary it creates itself) —
  independent of [[0106-sensor-device-management-ui]]'s UI, using the
  entity API directly, the same way this project's own kernel tests do.
- `assimilate_cron()` generates a new batch of plausible
  `SensorReading` rows on every cron run — a believable trend (e.g.
  gradual weight gain with daily noise, occasional realistic dips), not
  pure random noise, so the trend chart and `nexus`'s context-building
  produce something worth looking at.
- Explicitly out of scope: acoustic/VOC mock data (matches
  [[sensor-data-collection]]'s own "out of scope for now" list for those
  modalities); anything that touches real `AiProviderConfig`
  credentials or makes real outbound AI provider calls — mock data
  feeds the *context* nexus reasons over, it doesn't fake the provider
  response itself.

## Acceptance criteria
- [ ] `modules/assimilate/assimilate.info.yml` — depends on `nanoprobe`
      (and, once wired up, is expected to be exercised alongside
      `collective`/`nexus`); description makes the dev-only nature
      explicit in its own text, not just in this task file.
- [ ] A real, working guardrail against accidental production use —
      the specific mechanism is this task's own design decision (see
      Scope above), but "install and it just works on any site" is not
      acceptable without one.
- [ ] Provisions at least one demo `SensorDevice` per common
      `device_type` (weight, temperature_humidity at minimum), scoped
      realistically (hive-scoped, matching the actual pilot design in
      [[sensor-data-collection]]'s own architecture).
- [ ] `assimilate_cron()`: generates one plausible new `SensorReading`
      per active mock device per metric on every run, trending
      believably over time rather than pure noise — exact generation
      model (random walk, sine-wave seasonal variation, etc.) is an
      implementation decision.
- [ ] Verified manually: with `assimilate` enabled alongside
      `nanoprobe`/`collective`/`nexus`, the Sensors panel shows a real
      trend chart, the dashboard's "Needs attention"/"AI Insights"
      sections have real data to react to, and (with a real
      `AiProviderConfig` configured) `nexus_cron()` produces a genuine,
      context-grounded insight from the mock data — not just that mock
      rows exist in the database.
- [ ] Kernel tests: mock device provisioning is idempotent (running
      install/cron repeatedly doesn't duplicate devices); generated
      readings fall within realistic bounds for their metric; the
      production guardrail actually blocks/warns as designed.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Commits::
