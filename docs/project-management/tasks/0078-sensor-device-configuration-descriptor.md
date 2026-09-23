---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-20
completed: 2026-09-21
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

**Lives in the `nanoprobe` submodule**, per
[[0098-nanoprobe-collective-locutus-submodule-split]]. No `SensorDevice`
canonical page existed yet from any prior task — 0076 deliberately
deferred all UI/routing. This task builds the minimal one needed to host
the config/regenerate action (a field summary + the action itself, not a
general management UI), per ADR-0004 (custom controllers over view
builders).

## Acceptance criteria
- [x] New route `nanoprobe.sensor_device.config` →
      `/hivelog/sensor-device/{sensor_device}/config`, gated by the same
      entity access as managing the device itself (`SensorDevice::
      access('update')` — it exposes the secret token, so this is at
      least as strict as edit access). **Implemented as a Drupal form
      (POST, CSRF-protected), not a bare GET link/controller** — see
      Implementation notes for why a GET couldn't work here at all.
- [x] Response body matches
      [[0074-sensor-data-ingestion-architecture]] §3's schema exactly:
      `config_version`, `device` (`id`, `label`), `endpoint` (`url`,
      `method`, `content_type`), `auth` (`type: bearer`, `token`),
      `transport` (`batch`, `suggested_interval_seconds`), `metrics` (the
      `SensorReading::METRIC_TYPES` values appropriate to this device's
      `device_type`, via `SensorDevice::DEVICE_TYPE_METRICS` /
      `getConfigMetrics()`).
- [x] `suggested_interval_seconds` defaults to `1800` (30 minutes) —
      `SensorDevice::DEFAULT_SUGGESTED_INTERVAL_SECONDS` — mid-band within
      [[0074-sensor-data-ingestion-architecture]] §5's 15–60 minute
      recommendation.
- [x] A "Download Configuration" action on the new `SensorDevice`
      canonical page (`entity.sensor_device.canonical`,
      `SensorDeviceController::view()`), shown only when the current user
      has `update` access to the device.
- [x] **"Download config" and "Regenerate token" are the same action, by
      design** — see Implementation notes. Submitting the form
      immediately invalidates the previous token (a subsequent ingest
      request using the stale token gets `401` from
      [[0077-sensor-ingestion-endpoint-and-device-auth]]'s endpoint); the
      form's own explanatory text makes this explicit before submission.
- [x] Kernel tests
      (`modules/nanoprobe/tests/src/Kernel/SensorDeviceConfigDownloadFormTest.php`,
      `SensorDeviceControllerTest.php`): descriptor shape is exactly
      correct; the correct metric list per `device_type` (each mapped
      type, plus the `multi`/`other`/unset fallback to the full
      taxonomy); access denied for a user without update rights (form)
      and without view rights (canonical page); the download action is
      hidden on the canonical page for a view-only apiary member;
      regenerating invalidates the old token (a follow-up ingest POST
      with the stale token is rejected with `401`) and the newly
      downloaded token works; both routes are registered correctly.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Why "Download config" and "Regenerate token" ended up as one
  action.** `SensorDevice`'s plaintext token is never persisted (only its
  `password_hash()` digest is, per
  [[0076-sensor-device-and-reading-entities]]) — there is no way to
  recover a previously-generated plaintext for an existing, already-saved
  device. The only way a "download config" action can ever produce a file
  with a real, working token is to generate a fresh one in the same
  request that serves the file. Building these as two genuinely separate
  actions (a non-mutating "download" plus a distinct "regenerate") would
  mean "download" almost always produces a token-less/broken file for any
  device that wasn't *just* created or *just* regenerated — not a
  workable UX. Unifying them is the only design that keeps "download
  config" meaningful every time it's used. Documented directly in
  `SensorDeviceConfigDownloadForm`'s class docblock.
- **Why this is a Form (POST), not a controller (GET).** Because the
  unified action mutates the device's token, a bare `GET` link would
  violate [[0018-csrf-and-safe-http-methods]]'s "no state change via GET"
  principle — applied here exactly as everywhere else in hivelog, not
  waived for convenience. `$form_state->setResponse()` is used to return
  the JSON file directly from `submitForm()` instead of the normal
  redirect.
- **Route-level access is a flat own/any/admin permission**
  (`edit own sensor device+edit any sensor device+administer hivelog`),
  matching every other custom hivelog route; the real, apiary-scoped
  check happens inside the form's `buildForm()` /
  `SensorDeviceController::view()`, per ADR-0020's access-parity
  requirement — this codebase has no existing use of `_entity_access` in
  routing.yml despite ADR-0020 naming it as an option; every other custom
  route already follows the "flat permission + explicit in-controller
  check" pattern instead, so this task matched that established practice
  rather than introducing a new one.
- `SensorDevice::DEVICE_TYPE_METRICS` maps `weight`/`temperature_humidity`/
  `acoustic`/`entrance_counter`/`gps` to their applicable
  `SensorReading::METRIC_TYPES`; `multi`, `other`, and an unset
  `device_type` fall back to the full taxonomy rather than guessing a
  subset. Every mapped type also includes `battery_voltage`/`signal_rssi`
  (useful diagnostics regardless of primary sensing purpose).
- The canonical page is deliberately minimal (a field summary table plus
  the download action) — a full `SensorDevice` management UI (add/edit
  forms, a list builder) was never in this task's scope and still doesn't
  exist; only enough was built to give the config/regenerate action
  somewhere to live.
- **Addendum (2026-09-23, user report):** the destructive-action warning
  paragraph and the download button on this page were both effectively
  unstyled. Fixed, along with the same problem on the API Client
  canonical page — see
  [[0113-destructive-action-styling-sensor-device-api-client]] for the
  full writeup (the root cause — `hivelog.buttons.css`'s button system
  requires a registered "context wrapper" class, undocumented outside
  that file's own header comment — is shared, not specific to this
  page).

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0019-authorisation-model]], [[0018-csrf-and-safe-http-methods]],
  [[0020-access-parity-custom-routes]], [[0004-custom-controllers-over-view-builders]]
- Commits::
