---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-20
completed: 2026-09-21
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

**Lives in the `nanoprobe` submodule, not `hivelog` core** — per
[[0098-nanoprobe-collective-locutus-submodule-split]], sensor
functionality is optional (a beekeeper with no hardware shouldn't carry
this schema), so `modules/nanoprobe/` is a separate, independently
enableable module depending only on `hivelog:hivelog`. This task was
originally implemented directly in `hivelog` core, then redone against
`modules/nanoprobe/` once that ADR was accepted, in the same session
(nothing from the first pass had been committed).

## Acceptance criteria
- [x] `modules/nanoprobe/src/Entity/SensorDevice.php` —
      `ContentEntityBase` with `#[ContentEntityType]`, base table
      `nanoprobe_sensor_device`, entity keys (`id`, `label` → `label`,
      `uuid`, `owner` → `uid`).
- [x] `SensorDevice` fields: `label` (required string), `apiary`
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
- [x] `modules/nanoprobe/src/Entity/SensorReading.php` — base table
      `nanoprobe_sensor_reading`, entity keys (`id`, `uuid`); no
      `uid`/owner (machine-written).
- [x] `SensorReading` fields: `sensor_device` (required entity_reference →
      `sensor_device`), `metric` (required list_string, code-defined via a
      `SensorReading::METRIC_TYPES` constant — starting set: `weight_kg`,
      `temp_internal_c`, `temp_external_c`, `humidity_internal_pct`,
      `humidity_external_pct`, `battery_voltage`, `signal_rssi`), `value`
      (required float), `recorded` (required timestamp, supplied by the
      device/bridge), plus `created` (automatic — doubles as "received
      by HiveLog", kept distinct from `recorded` per
      [[0074-sensor-data-ingestion-architecture]] §1).
- [x] Validation (`preSave()`, throws): `SensorDevice.hive` must be set
      when `scope = hive` and unset when `scope = apiary` — same
      "exactly one of two references, gated by a sibling field" shape
      already used by `InventoryUsage`/`HarvestYield`'s
      `hive_action_log`/`apiary_action_log` pair in
      `ApiaryAccessTrait::resolveApiary()`.
- [x] Access control: extend `ApiaryAccessTrait::resolveApiary()` (which
      stays in `hivelog` core per
      [[0098-nanoprobe-collective-locutus-submodule-split]] §6) with two
      new branches — `SensorDevice` → `apiary` directly, or → `hive` →
      `apiary` (mirrors `HiveActionLog`'s `hive` → `apiary` branch);
      `SensorReading` → `sensor_device` → (apiary or hive) → apiary.
      `modules/nanoprobe/src/SensorDeviceAccessControlHandler.php` and
      `SensorReadingAccessControlHandler.php` use the trait exactly like
      every `hivelog`-core access handler does.
- [x] `modules/nanoprobe/nanoprobe.permissions.yml`: standard
      `view own/any`, `add`, `edit own/any`, `delete own/any` for
      `sensor_device`. `SensorReading` gets only `view own/any` +
      `delete own/any` (admin cleanup) — no `add`/`edit` permissions at
      all, since these rows are machine-written only (no add/edit form is
      ever built for this entity type).
- [x] No update hook needed: both entity types are new to a brand-new
      module (`nanoprobe`), so Drupal installs their schema automatically
      when the module is first enabled — no
      `hivelog_update_NNNNN()`-style hook applies.
      `modules/nanoprobe/nanoprobe.install` only needs
      `nanoprobe_uninstall()`, cleaning up `sensor_reading` before
      `sensor_device`.
- [x] Kernel tests (`modules/nanoprobe/tests/src/Kernel/SensorDeviceTest.php`,
      `SensorReadingTest.php`): CRUD, field defaults, the scope/hive
      validation guard (both directions), apiary-scoped access parity
      (mirroring `HiveActionLogTest`'s pattern), token generation/
      regeneration behaviour (not returned in plaintext on a plain load).
- [x] `ddev drush updb -y && ddev drush cr` clean, verified against `cms2`.
- [x] Full kernel + unit suite (`--group hivelog`) re-run against `cms2`,
      no regressions.
- [x] `.github/workflows/ci.yml` and `composer.json`'s `lint`/`stan`
      scripts extended to cover `modules/*/src`, `modules/*/tests`,
      `modules/*/*.module`, `modules/*/*.install` — per
      [[0098-nanoprobe-collective-locutus-submodule-split]] §7, otherwise
      `nanoprobe`'s code ships with zero CI coverage.

## Implementation notes
- Follow `src/Entity/CalendarAction.php` (single required apiary
  reference + list_string enums) and `src/Entity/HiveActionLog.php`
  (entity reference chain, no owner-driven label) as structural
  templates, matching how [[0028-inventory-item-and-purchase-entities]]
  followed the same pair for `InventoryItem`/`InventoryPurchase`. These
  templates live in `hivelog` core; `nanoprobe`'s classes `use` core's
  `HivelogEntityStorage` and `ApiaryAccessTrait` directly, since
  `nanoprobe` depends on `hivelog:hivelog`.
- No routes, controllers, list builder UI, or the ingestion endpoint
  itself in this task — deferred to
  [[0077-sensor-ingestion-endpoint-and-device-auth]] (ingestion),
  [[0078-sensor-device-configuration-descriptor]] (device management UI),
  and [[0080-hive-apiary-sensors-panel]] (read-side UI), mirroring how
  [[0028-inventory-item-and-purchase-entities]] deferred its own routing
  layer to [[0029-inventory-catalog-and-purchase-ledger-ui]]. These
  follow-up tasks also live in `modules/nanoprobe/`.
- Dedicated `AccessControlHandler` classes were written (not a genericised
  shared one) because `SensorReading`'s permission shape (no `add`/`edit`
  at all) differs materially from `SensorDevice`'s full CRUD shape.
- A kernel-test gotcha worth recording: `EntityReferenceItem`'s computed
  `entity` property caches the resolved target entity on first access,
  and entity storage's own static cache does too — so a single test
  method that checks access before *and* after flipping an ancestor
  apiary's `visibility` must explicitly reset both the relevant entity
  storages (`getStorage($type)->resetCache()`) and the access control
  handler (`getAccessControlHandler($type)->resetCache()`) before
  reloading, or the second check silently sees stale data. See both
  `testApiaryScopedAccess()` methods.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0003-code-defined-entity-schema]], [[0019-authorisation-model]]
- Commits::
