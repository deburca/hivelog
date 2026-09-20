---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-20
branch: feature/0076-sensor-device-and-reading-entities
release:
depends-on:
blocked-by:
---
# Task: Define the `SensorDevice` and `SensorReading` entities

## Context
Foundational pair for [[sensor-data-collection]] — the registered
hardware and the time series it produces. Nothing else in this project
can be built until these exist, per
[[0074-sensor-data-ingestion-architecture]] §1.

## Acceptance criteria
- [ ] `src/Entity/SensorDevice.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_sensor_device`, entity
      keys (`id`, `label` → `label`, `uuid`, `owner` → `uid`).
- [ ] `SensorDevice` fields: `label` (required string), `apiary`
      (required entity_reference → `apiary`), `hive` (entity_reference →
      `hive`, required only when `scope = hive`), `scope` (required
      list_string: `apiary` | `hive`), `device_type` (list_string:
      `weight`, `temperature_humidity`, `acoustic`, `entrance_counter`,
      `gps`, `multi`, `other`), `transport` (list_string: `lorawan`,
      `wifi`, `cellular`, `bluetooth`, `other` — informational only),
      `token` (string, server-generated on insert, stored hashed — never
      returned in plaintext except immediately after generation/
      regeneration), `enabled` (boolean, default `TRUE`), `last_seen`
      (timestamp, optional), plus `uid`/`created`/`changed`.
- [ ] `src/Entity/SensorReading.php` — base table `hivelog_sensor_reading`,
      entity keys (`id`, `uuid`); no `uid`/owner (machine-written).
- [ ] `SensorReading` fields: `sensor_device` (required entity_reference →
      `sensor_device`), `metric` (required list_string, code-defined via a
      `SensorReading::METRIC_TYPES` constant — starting set: `weight_kg`,
      `temp_internal_c`, `temp_external_c`, `humidity_internal_pct`,
      `humidity_external_pct`, `battery_voltage`, `signal_rssi`), `value`
      (required float), `recorded` (required timestamp, supplied by the
      device/bridge), plus `created` (automatic — doubles as "received
      by HiveLog", kept distinct from `recorded` per
      [[0074-sensor-data-ingestion-architecture]] §1).
- [ ] Validation (`preSave()`, throws): `SensorDevice.hive` must be set
      when `scope = hive` and unset when `scope = apiary` — same
      "exactly one of two references, gated by a sibling field" shape
      already used by `InventoryUsage`/`HarvestYield`'s
      `hive_action_log`/`apiary_action_log` pair in
      `ApiaryAccessTrait::resolveApiary()`.
- [ ] Access control: extend `ApiaryAccessTrait::resolveApiary()` with two
      new branches — `SensorDevice` → `apiary` directly, or → `hive` →
      `apiary` (mirrors `HiveActionLog`'s `hive` → `apiary` branch);
      `SensorReading` → `sensor_device` → (apiary or hive) → apiary.
- [ ] `hivelog.permissions.yml`: standard `view own/any`, `add`,
      `edit own/any`, `delete own/any` for `sensor_device`. `SensorReading`
      gets only `view own/any` + `delete own/any` (admin cleanup) — no
      `add`/`edit` permissions at all, since these rows are machine-
      written only (no add/edit form is ever built for this entity type).
- [ ] Update hook(s) in `hivelog.install` install both new tables (net-new,
      no data migration). Both entity type IDs added to
      `hivelog_uninstall()`'s child-first cleanup list (`sensor_reading`
      before `sensor_device`, both before `apiary`/`hive`).
- [ ] Kernel tests (`SensorDeviceTest`, `SensorReadingTest`): CRUD, field
      defaults, the scope/hive validation guard (both directions),
      apiary-scoped access parity (mirroring `HiveActionLogTest`'s
      pattern), token generation/regeneration behaviour (not returned in
      plaintext on a plain load).
- [ ] `ddev drush updb -y && ddev drush cr` clean, verified against `cms2`.
- [ ] Full kernel + unit suite (`--group hivelog`) re-run against `cms2`,
      no regressions.

## Implementation notes
- Follow `src/Entity/CalendarAction.php` (single required apiary
  reference + list_string enums) and `src/Entity/HiveActionLog.php`
  (entity reference chain, no owner-driven label) as structural
  templates, matching how [[0028-inventory-item-and-purchase-entities]]
  followed the same pair for `InventoryItem`/`InventoryPurchase`.
- No routes, controllers, list builder UI, or the ingestion endpoint
  itself in this task — deferred to
  [[0077-sensor-ingestion-endpoint-and-device-auth]] (ingestion),
  [[0078-sensor-device-configuration-descriptor]] (device management UI),
  and [[0080-hive-apiary-sensors-panel]] (read-side UI), mirroring how
  [[0028-inventory-item-and-purchase-entities]] deferred its own routing
  layer to [[0029-inventory-catalog-and-purchase-ledger-ui]].
- No `AccessControlHandler` needs writing from scratch if
  `CalendarActionAccessControlHandler`'s shape (delegating to
  `ApiaryAccessTrait`) can be genericised/reused — decide during
  implementation.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0003-code-defined-entity-schema]], [[0019-authorisation-model]]
- Commits::
