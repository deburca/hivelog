---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: entity
created: 2026-07-03
branch: feature/0018-hive-action-log-entity-and-schema
release:
depends-on: ["[[0017-calendar-action-entity-and-schema]]"]
blocked-by:
---
# Task: Define the `HiveActionLog` entity (hive-scoped)

## Context
Second half of the data model proposed in
[[0025-seasonal-calendar-and-hive-action-tracking]]. `HiveActionLog` is the
per-hive execution record against a `CalendarAction` — this is what makes
"each hive should have its own tracking" concrete. Depends on
[[0017-calendar-action-entity-and-schema]] for the `calendar_action` target
entity type to reference.

## Acceptance criteria
- [x] `src/Entity/HiveActionLog.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_hive_action_log`.
- [x] Fields: `hive` (required entity_reference → `hive`),
      `calendar_action` (required entity_reference → `calendar_action`),
      `year` (required integer, defaults to current year via
      `setDefaultValueCallback(HiveActionLog::getDefaultYear)`),
      `week_completed` (optional integer, `min`/`max` = 1–53),
      `status` (list_string: `pending`/`done`/`ignored`, default
      `pending`), `notes` (string_long), plus `uid`/`created`/`changed`.
      `pending` means "not yet reported"; `done` and `ignored` are the two
      reporting outcomes exposed to the beekeeper (see
      [[0021-hive-calendar-filtering-and-report-actions]] for the UI).
- [x] No uniqueness constraint on `(hive, calendar_action, year)` — verified
      empirically: two `HiveActionLog` rows for the same
      `(hive, calendar_action, year)` both save without error (see
      verification note below). No `preSave()` invariant added.
- [x] `HiveActionLogListBuilder` (links the calendar-action column to the
      log's own canonical page since there's no other natural "primary"
      text field, and the hive column to the hive — mirrors
      `QueenObservationListBuilder`'s date/queen split), default form
      (`HiveActionLogForm`), delete form (`HiveActionLogDeleteForm`).
- [x] `hivelog.permissions.yml`: `view own hive action log`,
      `view any hive action log`, `add hive action log`,
      `edit own hive action log`, `edit any hive action log`,
      `delete own hive action log`, `delete any hive action log`.
- [x] `hivelog_update_10015` in `hivelog.install` installs the new entity
      schema, following the same pattern as `hivelog_update_10014`. Also
      added `hive_action_log` to `hivelog_uninstall()`'s child-first entity
      cleanup list (first in the list — it references both `hive` and
      `calendar_action`).
- [x] `ddev drush updb -y && ddev drush cr` clean. **Verified** against
      `/Users/paddy/Development/cms2` (synced via `rsync`):
      `hivelog_update_10015` installs the `hive_action_log` entity type and
      `hivelog_hive_action_log` table; `drush cr` completes with no errors.
      A `drush php:eval` smoke test additionally confirmed: `year` defaults
      to the current calendar year when omitted; a valid `pending` log
      saves with 0 validation violations; a **second** log for the same
      `(hive, calendar_action, year)` saves successfully (2 rows confirmed
      via a direct query) — the "no uniqueness constraint" requirement
      holds; `week_completed = 99` is rejected by `validate()` (1
      violation, "no greater than 53"); `status = 'not_a_real_status'` is
      rejected by `validate()` (1 violation, "not a valid choice"). All
      smoke-test data was cleaned up afterwards (both
      `hivelog_hive_action_log` and `hivelog_calendar_action` tables are
      empty again in `cms2`).

## Implementation notes
- Key files added: `src/Entity/HiveActionLog.php`,
  `src/HiveActionLogListBuilder.php`, `src/Form/HiveActionLogForm.php`,
  `src/Form/HiveActionLogDeleteForm.php`. Changed:
  `hivelog.permissions.yml`, `hivelog.install`.
- Followed `src/Entity/QueenObservation.php` as the structural template
  (required reference to another hivelog entity + status enum + notes
  field + custom `label()` override rather than an entity-key `label`,
  since there's no single natural title field).
- `year` uses `BaseFieldDefinition::setDefaultValueCallback()` rather than a
  static `setDefaultValue()`, since the default must be computed at
  creation time (current year), not a fixed value — verified this resolves
  correctly for new entities without an explicit `year` in `::create()`.
- No `'access'` handler yet, same reasoning as
  [[0017-calendar-action-entity-and-schema]] — Drupal's `EntityType`
  defaults an unset `'access'` handler to
  `\Drupal\Core\Entity\EntityAccessControlHandler` until
  [[0019-calendar-routing-controllers-and-access]] adds
  `HiveActionLogAccessControlHandler`.
- Routing intentionally not touched — `entity.hive.canonical`/
  `entity.hive.collection` (used as the form/delete-form redirect targets)
  already exist.
- The `inspection` optional entity_reference field described in ADR-0025
  is deliberately **not** included here — that's
  [[0023-link-hive-action-log-to-inspection]]'s scope.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0003-code-defined-entity-schema]]
- Commits::
