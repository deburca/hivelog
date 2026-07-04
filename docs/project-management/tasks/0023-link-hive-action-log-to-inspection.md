---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: entity
created: 2026-07-03
branch: feature/0023-link-hive-action-log-to-inspection
release:
depends-on: ["[[0018-hive-action-log-entity-and-schema]]", "[[0019-calendar-routing-controllers-and-access]]", "[[0021-hive-calendar-filtering-and-report-actions]]"]
blocked-by:
---
# Task: Optionally link a "done" report to a new hive inspection record

## Context
Answers the project's question of whether reporting a calendar action as
`done` can create a hive intervention record with the report as its
comment — yes, per [[0025-seasonal-calendar-and-hive-action-tracking]].
Bridges the "planned" world (`CalendarAction`/`HiveActionLog`) and the
"already happened" world (`HiveInspection`). Depends on the base
`HiveActionLog` schema/routing/report-actions work landing first.

## Acceptance criteria
- [x] `HiveActionLog` gains an optional `inspection` field
      (`entity_reference` → `hive_inspection`). Added via
      `hivelog_update_10016` — `0018`'s `_10015` hook had already shipped
      and been run separately, so this landed as its own hook rather than
      being folded in.
- [x] `HiveActionLogForm` exposes a non-persisted checkbox, "Also create a
      hive inspection record for this," shown only when: (a) the current
      user holds `add hive inspection` or `administer hivelog`
      (`canCreateLinkedInspection()`), and (b) the entity doesn't already
      have a linked inspection (`$entity->get('inspection')->isEmpty()` —
      an extra guard beyond the original spec, added to prevent
      re-offering the checkbox when editing an already-linked log and
      silently orphaning the first inspection). Visibility additionally
      narrows to `status = done` via `#states`
      (`:input[name="status"]` — confirmed empirically that
      `options_select` on a single-cardinality field renders with exactly
      that flattened `name` attribute, not `status[0][value]`).
- [x] On submit with the checkbox ticked: saves the `HiveActionLog` as
      normal, then `createLinkedInspection()` creates a `HiveInspection`
      (`hive` + `inspection_date` = today + `action_taken` synthesised as
      `"{calendar_action label}: {log notes}"`), sets
      `HiveActionLog.inspection`, saves the log again, and redirects to
      the **new inspection's edit form**. Re-validates the submitted
      status and permission server-side in `save()` — never trusts the
      checkbox's client-side `#states` visibility alone.
- [x] Hive checklist rows (`HiveController::view()`) show a "View
      Inspection" button next to any `done` row with a non-empty,
      viewable `inspection`. The log's own canonical page
      (`HiveActionLogController::view()`) also displays the `inspection`
      field as a link (or an em-dash placeholder when absent).
- [x] Access parity: `createLinkedInspection()` explicitly calls
      `EntityAccessControlHandler::createAccess()` on `hive_inspection`
      before creating anything — verified this returns `NULL` (no
      inspection created) for a user without the permission, confirming
      the entity save itself is still permission-respecting rather than
      relying solely on the checkbox being hidden.
- [ ] Kernel test coverage — deferred to [[0024-calendar-test-coverage]]
      (`HiveActionLogInspectionLinkTest`, already anticipated in that
      task's acceptance criteria), consistent with how prior entity/schema
      tasks in this project handled kernel test deferral.

## Verification (against `/Users/paddy/Development/cms2`)
- `hivelog_update_10016` installs the `inspection` field storage
  definition cleanly; `drush cr` clean; full existing suite re-run with no
  regressions (124 kernel / 2186 assertions, 54 unit / 196 assertions).
- **Form-submission simulation caveat discovered**: driving a full
  `\Drupal::formBuilder()->submitForm()` headlessly against an
  `entity_reference_autocomplete` widget requires the exact
  `"Label (id)"` string format, and even then the triggering-element
  plumbing needed for `EntityForm`'s save() to actually fire is fragile
  outside a real HTTP POST. Rather than fight that, verified the actual
  business logic directly: `createLinkedInspection()` invoked via
  reflection (a legitimate technique for testing a protected method
  in isolation) — confirmed it creates the inspection with the correct
  `hive`, `inspection_date` (today), and synthesised `action_taken` text,
  and that `HiveActionLog.inspection` is persisted correctly **on
  reload from storage** (not just in-memory). Also confirmed the
  access-denial path: the same method called as a user with only `add
  hive action log` (no inspection permission) returns `NULL`.
- All four checkbox-visibility branches verified directly on built form
  arrays: present for a privileged user with no existing link; absent for
  a user lacking `add hive inspection`; absent when editing a log that
  already has a linked inspection; the raw `inspection` field itself
  confirmed hidden (`#access = FALSE`) on every form regardless.
- Confirmed the `#states` selector (`:input[name="status"]`) matches the
  real rendered `<select>`'s `name="status"` attribute — checked the
  actual rendered HTML rather than assuming Drupal's field-widget naming
  convention.
- End-to-end checklist rendering: a `done` row with a linked inspection
  shows a working "View Inspection" link pointing at the correct
  `/hivelog/inspection/{id}` URL, and the hive page's cache tags include
  the linked inspection's own tag. The log's own canonical page renders
  the same link, or an em-dash when no inspection is linked.
- Found and cleaned up test debris left over from two earlier failed
  script attempts (autocomplete widget value-format mistakes on my part,
  not a module bug) — both tables empty again afterwards; one pre-existing
  unrelated `hive_inspection` row (id 1, present before this session's
  testing began) was correctly left untouched.

## Implementation notes
- Key files changed: `src/Entity/HiveActionLog.php` (new field),
  `hivelog.install` (`hivelog_update_10016`),
  `src/Form/HiveActionLogForm.php` (checkbox, `save()`,
  `canCreateLinkedInspection()`, `createLinkedInspection()`),
  `src/Controller/HiveController.php` (checklist "View Inspection" button
  + cache dependency), `src/Controller/HiveActionLogController.php`
  (`inspection` in the Details section + cache dependency).
- Used `HiveInspection::create([...])` (the static entity-API shorthand)
  rather than injecting `EntityTypeManagerInterface` into the form's
  constructor — avoids the risk of an incomplete override of
  `ContentEntityForm`'s own constructor chain (which already wires up
  several core-provided properties) for a single, contained need.
- The "no existing inspection" guard on the checkbox (`$entity->get(
  'inspection')->isEmpty()`) and the matching guard in `save()` were
  added beyond the task's original acceptance criteria — a straightforward
  extension once the edit-form re-offering risk became apparent while
  implementing, not a late scope change.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0020-access-parity-custom-routes]]
- Commits::
