---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-20
branch: feature/0078-sensor-device-configuration-descriptor
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]", "[[0077-sensor-ingestion-endpoint-and-device-auth]]"]
blocked-by: ["[[0077-sensor-ingestion-endpoint-and-device-auth]]"]
---
# Task: Device configuration descriptor download + token regeneration

## Context
How a physical device actually learns its endpoint URL, its credential,
and the payload shape to send — without hand-editing/recompiling generic
firmware per device — per
[[0074-sensor-data-ingestion-architecture]] §3. Without this, a
`SensorDevice` can be registered and the ingestion endpoint
([[0077-sensor-ingestion-endpoint-and-device-auth]]) can accept requests,
but nothing can actually provision a real device to make one.

## Acceptance criteria
- [ ] New route (e.g. `hivelog.sensor_device.config`) →
      `/hivelog/sensor-device/{sensor_device}/config`, gated by the same
      entity access as managing the device itself (`SensorDevice::
      access('update')` — it exposes the secret token, so this should be
      at least as strict as edit access, not merely view access; confirm
      during implementation).
- [ ] Response body matches
      [[0074-sensor-data-ingestion-architecture]] §3's schema exactly:
      `config_version`, `device` (`id`, `label`), `endpoint` (`url`,
      `method`, `content_type`), `auth` (`type: bearer`, `token`),
      `transport` (`batch`, `suggested_interval_seconds`), `metrics` (the
      `SensorReading::METRIC_TYPES` values appropriate to this device's
      `device_type`).
- [ ] `suggested_interval_seconds` defaults within the 15–60 minute band
      per [[0074-sensor-data-ingestion-architecture]] §5 (Hivekraft's own
      recommended sampling interval) — exact default documented in code.
- [ ] A "Download config" action on the `SensorDevice` canonical page.
- [ ] A "Regenerate token" action on the same page: invalidates the
      previous token immediately (a subsequent ingest request using the
      stale token gets `401` from
      [[0077-sensor-ingestion-endpoint-and-device-auth]]'s endpoint), and
      makes clear the beekeeper needs to re-download and re-provision the
      device.
- [ ] Kernel tests: config download returns the correct shape and the
      correct metric list per `device_type`; access denied for a user
      without update rights to the device; regenerating the token
      invalidates the old one (a follow-up ingest POST with the stale
      token is rejected).
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- Deliberately **not** included in the descriptor: which physical pin
  reads which sensor, or any calibration constant — firmware/hardware-
  specific, stays in firmware's own local configuration, per
  [[0074-sensor-data-ingestion-architecture]] §3.
- Self-provisioning (a device fetching/refreshing this descriptor over
  the network by itself, rather than a beekeeper copying it on once) is
  explicitly deferred — not this task's scope.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0019-authorisation-model]]
- Commits::
