---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-22
completed: 2026-09-22
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
- [x] `modules/assimilate/assimilate.info.yml` — depends on `nanoprobe`
      (and, once wired up, is expected to be exercised alongside
      `collective`/`nexus`); description makes the dev-only nature
      explicit in its own text, not just in this task file.
- [x] A real, working guardrail against accidental production use —
      the specific mechanism is this task's own design decision (see
      Scope above), but "install and it just works on any site" is not
      acceptable without one.
- [x] Provisions at least one demo `SensorDevice` per common
      `device_type` (weight, temperature_humidity at minimum), scoped
      realistically (hive-scoped, matching the actual pilot design in
      [[sensor-data-collection]]'s own architecture).
- [x] `assimilate_cron()`: generates one plausible new `SensorReading`
      per active mock device per metric on every run, trending
      believably over time rather than pure noise — exact generation
      model (random walk, sine-wave seasonal variation, etc.) is an
      implementation decision.
- [x] Verified manually: with `assimilate` enabled alongside
      `nanoprobe`/`collective`/`nexus`, the Sensors panel shows a real
      trend chart, the dashboard's "Needs attention"/"AI Insights"
      sections have real data to react to, and (with a real
      `AiProviderConfig` configured) `nexus_cron()` produces a genuine,
      context-grounded insight from the mock data — not just that mock
      rows exist in the database.
- [x] Kernel tests: mock device provisioning is idempotent (running
      install/cron repeatedly doesn't duplicate devices); generated
      readings fall within realistic bounds for their metric; the
      production guardrail actually blocks/warns as designed.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Guardrail mechanism: `hook_requirements()`, not a `hook_install()`
  exception.** Throwing from `hook_install()` wouldn't actually cancel
  the install cleanly (the module's schema/config is already registered
  by the point `hook_install()` runs) — Drupal's own idiomatic
  install-time refusal is a `REQUIREMENT_ERROR` from the `install`
  phase of `hook_requirements()`, which both the module-list UI
  (`drupal_check_module()`) and `drush pm:install` check *before*
  calling `hook_install()` at all. The `runtime` phase shows the same
  check as a standing `admin/reports/status` entry — `REQUIREMENT_ERROR`
  if a real apiary has since opted into AI insights, `REQUIREMENT_WARNING`
  otherwise, so the dev-only nature stays visible for as long as the
  module is enabled.
- **The `install`-phase check can't use assimilate's own service.**
  `hook_requirements('install')` runs before the module is installed —
  `drupal_check_module()` just `require_once`s `assimilate.install` and
  calls the function directly, so `assimilate.services.yml` isn't
  registered in the container yet. `assimilate_requirements()` inlines
  the check with core services only (`\Drupal::entityTypeManager()`,
  `\Drupal::state()`); `ProductionGuardrail`'s service (used by
  `assimilate_cron()`, which runs long after install) is the "real"
  single implementation everywhere else.
- **The guardrail re-checks on every `assimilate_cron()` run, not just
  at install.** The more insidious case is a site installing assimilate
  first (safe) and only later having a real apiary opt into AI insights
  — `assimilate_cron()` must stop generating mock data the moment that
  happens, not just refuse to install in the first place.
- **`HiveContextBuilder` is "sensor-less" by design (task 0085) — it
  never reads `SensorReading` data.** For `nexus_cron()` to produce a
  genuine insight from the demo hive at all, `DemoDataProvisioner` also
  creates one realistic `HiveInspection` (not just sensor devices) —
  a small, deliberate scope addition beyond "mock sensor data" in the
  task's own title, directly required by this task's own verification
  criterion. Created once at provision time, not regenerated every
  cron run, unlike sensor readings — a beekeeper's real inspection
  cadence is weeks, not every cron run.
- **State API tracks every demo entity's id** (`assimilate.demo_apiary_id`,
  `.demo_hive_id`, `.demo_device_ids`, `.demo_inspection_id`), giving
  `provision()` idempotency, `getDemoDevices()` a way to find "its own"
  devices without fragile label-matching, `ProductionGuardrail` a way to
  exclude assimilate's own demo apiary from its "is there a real one?"
  check, and `cleanUp()` a precise list to delete on uninstall.
- **Verified live against `cms2`** (`drush pm:enable assimilate`, no
  real apiary had AI insights enabled beforehand): provisioned the demo
  apiary/hive/devices/inspection correctly, `drush cron` generated
  plausible readings for every metric on both devices (weight, battery,
  signal, internal/external temp and humidity), the Hive canonical
  page's Sensors panel showed live latest-reading summaries, the demo
  inspection appeared in Hive Activity and the dashboard's "Recent
  activity"/"Needs attention" sections, and `nexus_cron()` genuinely
  attempted an AI call for the demo hive using the site's real "Claude"
  `AiProviderConfig` — it reached the Anthropic API and failed only on
  a pre-existing, unrelated stale model id in that config
  (`claude-3-5-haiku-latest` → 404), not on anything to do with the
  mock data or context-building. `admin/reports/status` correctly shows
  the dev-only warning. Left `assimilate` installed on `cms2` afterward
  — unlike a throwaway verification artifact, giving a real demo
  experience is the entire point of this module.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Tasks:: [[0085-sensor-less-insight-prototype]] (why a demo
  `HiveInspection` was needed alongside the mock sensor devices)
- Commits::
