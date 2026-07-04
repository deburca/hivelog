---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: entity
created: 2026-07-03
branch: feature/0017-calendar-action-entity-and-schema
release:
depends-on:
blocked-by:
---
# Task: Define the `CalendarAction` entity (apiary-scoped)

## Context
First half of the data model proposed in
[[0025-seasonal-calendar-and-hive-action-tracking]]. `CalendarAction` is the
recurring seasonal plan, scoped to a single apiary and shared by every hive
in it. Nothing else in this project can be built until this entity exists.

## Acceptance criteria
- [x] `src/Entity/CalendarAction.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_calendar_action`, entity
      keys (`id`, `label` → `title`, `uuid`, `owner` → `uid`).
- [x] Fields: `apiary` (required entity_reference → `apiary`), `title`
      (required string, short name — e.g. "Harvest Spring Honey"),
      `description` (required string_long — full description of the
      activity; see rendering note below), `category` (optional list_string;
      hard-coded allowed values: `varroa_treatment`, `feeding`,
      `spring_buildup`, `swarm_prevention`, `harvest_spring`,
      `harvest_summer`, `winter_prep`, `requeening`, `other`), `week_start`
      (required integer), `week_end` (optional integer),
      `recurring` (boolean, default `TRUE`), `enabled` (boolean, default
      `TRUE` — controls visibility on the apiary/hive views; disabled items
      remain visible in the list builder for management), plus
      `uid`/`created`/`changed`.
- [x] ISO-8601 week validation on `week_start` and `week_end`: implemented as
      `->setSetting('min', 1)->setSetting('max', 53)` on both integer
      fields — this is the idiomatic Drupal mechanism (already used for
      `varroa_count`/`weight`/`supers` in `HiveInspection.php`) and is what
      actually registers a `Range`-equivalent constraint under the hood via
      `NumericItemBase`, rather than a bespoke `addConstraint()` call.
      `week_end >= week_start` is enforced two ways: `CalendarActionForm::
      validateForm()` for a proper form error, and a defensive
      `CalendarAction::preSave()` guard (throws `\InvalidArgumentException`)
      for programmatic creation. Week 53's year-specific validity is
      intentionally not checked — `CalendarAction` is year-agnostic.
- [x] `CalendarActionListBuilder` (mirrors `HiveListBuilder`), showing every
      row regardless of `enabled` (with a visible "Disabled"/"Yes" indicator
      on the `enabled` column) so beekeepers can find and re-enable them.
      Default form (`CalendarActionForm`) and delete form
      (`CalendarActionDeleteForm`).
- [x] `description` rendering helper built now (used later by
      [[0020-apiary-and-hive-calendar-ui]]): `Drupal\hivelog\Utility\
      SimpleBulletText::render()` — lines starting with `- `/`* ` render as
      a `<ul><li>` list; other non-empty lines render as `<p>` paragraphs;
      every line is `Html::escape()`-d before any markup is added, per
      [[0017-output-sanitisation-policy]]. Not yet wired into any
      controller — that's [[0020-apiary-and-hive-calendar-ui]]'s job.
- [x] `hivelog.permissions.yml`: `view own calendar action`,
      `view any calendar action`, `add calendar action`,
      `edit own calendar action`, `edit any calendar action`,
      `delete own calendar action`, `delete any calendar action`.
- [x] `hivelog_update_10014` in `hivelog.install` installs the new entity
      schema (net-new table — no data migration needed), following the
      `hivelog_update_10009` (Queen Observation) precedent exactly. Also
      added `calendar_action` to `hivelog_uninstall()`'s child-first entity
      cleanup list.
- [x] `ddev drush updb -y && ddev drush cr` clean. Verified against
      `/Users/paddy/Development/cms2` (synced via `rsync`): `hivelog_update_
      10014` installs the `calendar_action` entity type and
      `hivelog_calendar_action` table; `drush cr` completes with no errors.
      A `drush php:eval` smoke test additionally confirmed: a valid
      `CalendarAction` saves with 0 validation violations;
      `week_start = 60` is rejected by `validate()` (the `min`/`max`
      field-setting constraint); `week_end < week_start` is rejected by the
      `preSave()` guard (surfaces as a wrapped `EntityStorageException`,
      transaction rolled back, no row written — expected Drupal behaviour);
      `SimpleBulletText::render()` produces the expected `<p>`/`<ul><li>`
      markup. All smoke-test data was cleaned up afterwards (table is empty
      again in `cms2`).
- [ ] Kernel test smoke-check deferred to [[0024-calendar-test-coverage]] as
      anticipated by this task's own acceptance criteria — not duplicated
      here.

## Implementation notes
- Key files added: `src/Entity/CalendarAction.php`,
  `src/CalendarActionListBuilder.php`, `src/Form/CalendarActionForm.php`,
  `src/Form/CalendarActionDeleteForm.php`,
  `src/Utility/SimpleBulletText.php`. Changed:
  `hivelog.permissions.yml`, `hivelog.install`.
- Followed `src/Entity/Hive.php` as the structural template (single required
  parent reference + list_string enums + a long-text field); `description`
  is required here (unlike `Hive::notes`, which is optional) since the
  user's brief calls for a full description on every item.
- No `'access'` handler in the `#[ContentEntityType]` attribute yet, as
  scoped — Drupal's `EntityType` defaults an unset `'access'` handler to
  `\Drupal\Core\Entity\EntityAccessControlHandler`, which is sufficient
  until [[0019-calendar-routing-controllers-and-access]] adds
  `CalendarActionAccessControlHandler` and wires it in.
- Routing intentionally not touched — `entity.apiary.canonical`/
  `entity.apiary.collection` (used as the form/delete-form redirect
  targets) already exist, so the new forms are internally consistent even
  before [[0019-calendar-routing-controllers-and-access]] adds the
  `calendar_action`-specific routes.
- `CalendarAction::DEFAULT_STARTER_CALENDAR` /
  `CalendarAction::seedDefaultsForApiary()` are deliberately **not** included
  here — that's [[0022-seed-default-calendar-on-apiary-creation]]'s scope.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0003-code-defined-entity-schema]]
- Commits::
