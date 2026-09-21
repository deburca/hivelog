---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-20
completed: 2026-09-21
branch: feature/0077-sensor-ingestion-endpoint-and-device-auth
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]"]
blocked-by: ["[[0076-sensor-device-and-reading-entities]]"]
---
# Task: Sensor reading ingestion endpoint + device-token authentication

## Context
The one HTTP contract HiveLog exposes for automated data collection, per
[[0074-sensor-data-ingestion-architecture]] §2/§3 — everything upstream
(LoRaWAN, MQTT, Bluetooth) is a bridge concern outside this module;
HiveLog only needs to accept this one authenticated `POST`.

**Lives in the `nanoprobe` submodule**, per
[[0098-nanoprobe-collective-locutus-submodule-split]] — same as
[[0076-sensor-device-and-reading-entities]]. The URL path stays exactly
`/hivelog/api/sensor-readings` as [[0074-sensor-data-ingestion-architecture]]
§2 documents it (a stable external contract firmware/bridges hardcode),
even though the route/controller are defined in `nanoprobe`'s own
`.routing.yml` under a `nanoprobe.*`-prefixed route name — Drupal doesn't
require a route's machine name or URL prefix to match the module that
defines it.

## Acceptance criteria
- [x] New route `nanoprobe.sensor_reading.ingest` →
      `POST /hivelog/api/sensor-readings`, `_controller:
      '\Drupal\nanoprobe\Controller\SensorIngestController::ingest'`. Route
      itself carries `_access: 'TRUE'` (no Drupal-permission gate) — the
      controller performs its own bearer-token check, per
      [[0074-sensor-data-ingestion-architecture]] §3's documented
      exception to the normal entity-permission model.
- [x] Controller reads `Authorization: Bearer <token>` from the request;
      resolves the matching `SensorDevice` (hashed-token comparison,
      constant-time via `SensorDevice::verifyToken()`'s internal
      `password_verify()`). Missing/unrecognised token → `401`. Token
      matches a disabled device → `403`. Every device (not only enabled
      ones) is checked so these two cases can be told apart — see
      Implementation notes.
- [x] Accepts either a single JSON object or a JSON array (batch) body,
      exactly the two shapes shown in
      [[0074-sensor-data-ingestion-architecture]] §2.
- [x] Per-item validation: `metric` must be one of
      `SensorReading::METRIC_TYPES` (else `422`), `value` must be a finite
      number (else `422`), `recorded` must parse as a valid timestamp
      (else `422`). **Decision: a batch is all-or-nothing** — every item
      is validated before anything is written; one bad item rejects the
      whole batch with `422` and zero `SensorReading` rows created. See
      Implementation notes for why.
- [x] On success: one `SensorReading` per accepted item, `SensorDevice.
      last_seen` updated to "now", response `201` with the created
      reading id(s).
- [x] No CSRF token required — the bearer-token check is this route's
      protection, per [[0018-csrf-and-safe-http-methods]]'s documented
      exception in [[0074-sensor-data-ingestion-architecture]] §3. Route
      stays `POST`-only (`methods: [POST]`); no state change is ever
      reachable via `GET`. Nothing extra was needed to achieve this —
      hivelog has no global CSRF middleware; only its Form API routes
      carry CSRF tokens, so a plain custom controller route is
      CSRF-exempt by default already.
- [x] Kernel tests
      (`modules/nanoprobe/tests/src/Kernel/SensorIngestControllerTest.php`):
      valid single reading accepted; valid batch accepted;
      missing/invalid token → `401`; disabled device → `403`; unknown
      metric → `422`; non-numeric value → `422`; unparseable `recorded` →
      `422`; malformed JSON body → `422`; empty batch → `422`; one bad
      item fails the whole batch (nothing persisted) → `422`; `last_seen`
      updated on success; the created reading resolves to the correct
      apiary/hive via `ApiaryAccessTrait` afterward (readable by the right
      beekeeper, not just written); the route itself is registered with
      `_access: 'TRUE'` and no `_permission`.
- [x] phpcs clean (`--standard=Drupal,DrupalPractice --warning-severity=0`).
      Full kernel + unit suite re-run against `cms2`, no regressions.

## Implementation notes
- Rate-limiting a misbehaving/compromised device (`429`) is explicitly
  deferred — noted in [[0074-sensor-data-ingestion-architecture]] §2 so
  it isn't forgotten, not required for this task.
- Token comparison reuses `SensorDevice::verifyToken()` from
  [[0076-sensor-device-and-reading-entities]] (`password_verify()`
  against the stored `password_hash()` digest) rather than a custom
  crypto primitive, per [[0006-contrib-dependency-policy]]'s spirit
  ("don't invent crypto," not "don't use core APIs"). `password_verify()`
  is already timing-safe for the hash comparison itself; the controller
  loads and checks *every* `SensorDevice` (not just enabled ones, and not
  stopping at the first match some other way) so a disabled device's
  token still resolves to a device — otherwise `401`
  (unrecognised)and `403` (disabled) couldn't be told apart. Iterating
  every device per request is an accepted pilot-scale trade-off (a
  handful of devices, not thousands); a real lookup-optimised design
  (e.g. an indexed fast-hash for lookup, then a constant-time compare) is
  future work if device count ever makes this matter, not designed here.
- **Batch validation happens entirely before any write** (no interleaved
  validate-then-save per item), which is what makes all-or-nothing free —
  there's no need for an explicit database transaction, since nothing is
  written until every item has already passed. Chosen over "write the
  good items, report the bad ones" because it keeps the response shape
  simple (firmware only ever sees "everything landed" or "nothing did,
  here's why," never a mixed per-item result to reconcile), at the cost
  of one bad item losing an otherwise-good batch — acceptable at the
  reporting intervals and batch sizes
  [[0074-sensor-data-ingestion-architecture]] §5 describes (a handful of
  queued readings after a connectivity gap, not hundreds).
- The controller is called directly in kernel tests (`new
  SensorIngestController()` + a manually built `Request`), not dispatched
  through the full HTTP kernel/router — this is the first HTTP API
  hivelog/nanoprobe has, and there was no existing precedent either way;
  direct invocation avoids installing routing/path-alias state in a
  kernel test while still exercising the exact auth/validation/
  persistence logic the route invokes. Route registration itself (path,
  method, controller, `_access`) is checked separately via
  `router.route_provider`.
- `recorded` accepts either a UNIX timestamp (int) or an ISO 8601 string
  (parsed via `strtotime()`), matching
  [[0074-sensor-data-ingestion-architecture]] §2's example payload
  (`"2026-09-21T10:15:00Z"`).
- True `NaN`/`Infinity` cannot be transmitted as valid JSON (they aren't
  legal JSON literals), so the realistic "non-finite value" case is a
  `value` that isn't numeric at all (e.g. a string) — tested as such
  rather than via literal `NAN`/`INF`.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0018-csrf-and-safe-http-methods]], [[0020-access-parity-custom-routes]]
- Commits::
