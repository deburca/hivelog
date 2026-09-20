---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-20
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

## Acceptance criteria
- [ ] New route (e.g. `hivelog.sensor_reading.ingest`) →
      `POST /hivelog/api/sensor-readings`, `_controller:
      '\Drupal\hivelog\Controller\SensorIngestController::ingest'`. Route
      itself carries `_access: 'TRUE'` (no Drupal-permission gate) — the
      controller performs its own bearer-token check, per
      [[0074-sensor-data-ingestion-architecture]] §3's documented
      exception to the normal entity-permission model.
- [ ] Controller reads `Authorization: Bearer <token>` from the request;
      resolves the matching `SensorDevice` (hashed-token comparison,
      constant-time). Missing/unrecognised token → `401`. Token matches a
      disabled device → `403`.
- [ ] Accepts either a single JSON object or a JSON array (batch) body,
      exactly the two shapes shown in
      [[0074-sensor-data-ingestion-architecture]] §2.
- [ ] Per-item validation: `metric` must be one of
      `SensorReading::METRIC_TYPES` (else `422`), `value` must be a finite
      number (else `422`), `recorded` must parse as a valid timestamp
      (else `422`). A batch's items are validated independently — decide
      during implementation whether one bad item fails the whole batch or
      only that item (document the choice either way).
- [ ] On success: one `SensorReading` per accepted item, `SensorDevice.
      last_seen` updated to "now", response `201` with the created
      reading id(s).
- [ ] No CSRF token required — the bearer-token check is this route's
      protection, per [[0018-csrf-and-safe-http-methods]]'s documented
      exception in [[0074-sensor-data-ingestion-architecture]] §3. Route
      stays `POST`-only; no state change is ever reachable via `GET`.
- [ ] Kernel tests: valid single reading accepted; valid batch accepted;
      missing/invalid token → `401`; disabled device → `403`; unknown
      metric → `422`; non-finite value → `422`; unparseable `recorded` →
      `422`; `last_seen` updated on success; the created reading resolves
      to the correct apiary/hive via `ApiaryAccessTrait` afterward
      (confirms it is readable by the right beekeeper, not just written).
- [ ] phpcs clean (`--standard=Drupal,DrupalPractice --warning-severity=0`).
      Full kernel + unit suite re-run against `cms2`, no regressions.

## Implementation notes
- Rate-limiting a misbehaving/compromised device (`429`) is explicitly
  deferred — noted in [[0074-sensor-data-ingestion-architecture]] §2 so
  it isn't forgotten, not required for this task.
- Reuse whatever password-hashing primitive Drupal core already exposes
  for the token comparison (do not roll a custom hash) —
  [[0006-contrib-dependency-policy]]'s minimal-dependency policy still
  holds; this is about not inventing crypto, not about avoiding core
  APIs.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0018-csrf-and-safe-http-methods]], [[0020-access-parity-custom-routes]]
- Commits::
