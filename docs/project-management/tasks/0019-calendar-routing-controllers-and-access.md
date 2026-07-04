---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: routing
created: 2026-07-03
branch: feature/0019-calendar-routing-controllers-and-access
release:
depends-on: ["[[0017-calendar-action-entity-and-schema]]", "[[0018-hive-action-log-entity-and-schema]]"]
blocked-by:
---
# Task: Calendar routing, controllers, breadcrumbs and access control

## Context
Wires up the two entities from [[0017-calendar-action-entity-and-schema]]
and [[0018-hive-action-log-entity-and-schema]] following the module's
existing scoped-add route convention (`hivelog.hive.add`,
`hivelog.queen.add`, etc.) and apiary-scoped access model.

## Acceptance criteria
- [x] `hivelog.routing.yml`: standard CRUD routes for both entities
      (`entity.calendar_action.collection/canonical/edit_form/delete_form`,
      same for `hive_action_log`), plus scoped-add routes:
      - `hivelog.calendar_action.add` →
        `/hivelog/apiary/{apiary}/calendar-action/add`
      - `hivelog.hive_action_log.add` →
        `/hivelog/hive/{hive}/calendar-action/{calendar_action}/log/add`
- [x] `CalendarActionController` and `HiveActionLogController` (view +
      title callback + `addForm`), per
      [[0004-custom-controllers-over-view-builders]]. Both follow the
      `QueenController`/`QueenObservationController` section-table pattern;
      `CalendarActionController`'s description section renders via
      `SimpleBulletText::render()` (wired in for the first time — built in
      [[0017-calendar-action-entity-and-schema]] but unused until now).
- [x] `CalendarActionAccessControlHandler` (mirrors `HiveAccessControlHandler`
      — owner-only delete, since a calendar action is foundational apiary
      structure) and `HiveActionLogAccessControlHandler` (mirrors
      `HiveInspectionAccessControlHandler` — owner-or-creator delete, since
      a log is a per-visit record), both using `ApiaryAccessTrait`. Both
      entity `#[ContentEntityType]` attributes updated to reference their
      new `'access'` handler (previously deferred to this task).
- [x] `ApiaryAccessTrait::resolveApiary()` gains two branches:
      `calendar_action` → `apiary` directly; `hive_action_log` → `hive` →
      `apiary`.
- [x] `hivelog.breadcrumb` builder's `applies()` extended to match
      `entity.calendar_action.*` and `entity.hive_action_log.*` explicitly;
      the two new `hivelog.*` scoped-add route names were already covered
      by the existing generic `hivelog.` prefix match (same as
      `hivelog.hive.add`/`hivelog.queen.add` today), so no separate entry
      was needed for those. Both are genuine pages (forms) — neither
      needed excluding as a non-page endpoint.
      **Non-obvious wrinkle handled**: `hivelog.hive_action_log.add` is the
      module's first route with *two* entity route parameters (`hive` and
      `calendar_action`) at once. The generic `calendar_action` breadcrumb
      block is therefore guarded with `str_starts_with($route_name,
      'entity.calendar_action.')` so it only fires on the calendar action's
      own CRUD routes — otherwise it would have produced a duplicate/wrong
      crumb on the log's add-form (verified empirically; see below).
- [x] Access parity: every route/operation pair covered by an "own" and
      "any" permission check consistent with
      [[0020-access-parity-custom-routes]] — verified directly via
      `access_manager.checkNamedRoute()` for all 10 new routes (see below).
- [x] `HiveActionLogController::addForm()` reads an optional `status` query
      parameter (`?status=done` / `?status=ignored`) and passes it through as
      the form's default value for the `status` field, validated against
      the field's real `allowed_values` (unknown values are silently
      ignored, falling back to the field's own default). Also added a
      defensive check: a `calendar_action` from a different apiary than the
      `hive` throws `NotFoundHttpException` rather than silently accepting
      a mismatched pairing.

## Verification (against `/Users/paddy/Development/cms2`)
- `php -r` with Symfony's `Yaml::parseFile()` confirms `hivelog.routing.yml`
  parses cleanly (36 routes total, all 10 new ones present).
- `drush cr` succeeds with no errors (entity attributes, controllers,
  access handlers, and routing all load without a fatal).
- **Full existing test suite re-run to confirm no regressions**:
  `tests/src/Unit` — 54 tests / 196 assertions, all pass (includes the
  pre-existing `HivelogBreadcrumbBuilderTest`, unmodified by this task but
  exercising the same `applies()`/`build()` methods that were changed).
  `tests/src/Kernel` — 124 tests / 2114 assertions, all pass.
- `access_manager.checkNamedRoute()` against all 10 new routes: anonymous
  denied, privileged (uid 1) allowed, for every route.
- Scoped-permission access test (a real `Role` with only `view own
  calendar action` / `view own hive action log`, no site-wide "any"): a
  user who is an apiary *member* can view a `calendar_action`/
  `hive_action_log` belonging to their apiary; the same user is denied for
  an *unrelated* apiary's `calendar_action`; a user with no membership
  anywhere is denied both — this is what actually exercises
  `resolveApiary()`'s two new branches end-to-end, not just uid 1's
  blanket "administer hivelog" bypass.
- `HiveActionLogController::addForm()`: cross-apiary hive/calendar_action
  pairing throws `NotFoundHttpException`; `?status=done` on a matching pair
  produces a rendered form whose `status` widget's actual
  `#default_value` is `done` (checked on the real render array, not a
  reproduction); an invalid `?status=` value doesn't throw and falls back
  to the field default.
- Breadcrumb trails checked directly via the `hivelog.breadcrumb` service:
  `entity.calendar_action.canonical` → `Home / HiveLog / {Apiary} /
  {CalendarAction}`; `entity.hive_action_log.canonical` → `Home / HiveLog /
  {Apiary} / {Hive} / {Log}`; `hivelog.hive_action_log.add` (the dual-param
  route) → `Home / HiveLog / {Apiary} / {Hive}` with **no** duplicate or
  incorrect calendar-action crumb, confirming the guard above works.
- Both list builders (`entity.calendar_action.collection`,
  `entity.hive_action_log.collection`) render successfully now that
  canonical routes exist — resolves the "untestable until routing lands"
  caveat noted in [[0017-calendar-action-entity-and-schema]] and
  [[0018-hive-action-log-entity-and-schema]].
- All smoke-test entities (apiaries, hives, calendar actions, logs, a
  test role, and two test users) were deleted afterwards; both
  `hivelog_calendar_action` and `hivelog_hive_action_log` tables are empty
  in `cms2` again.

## Implementation notes
- Key files: `hivelog.routing.yml`, `src/Controller/CalendarActionController.php`,
  `src/Controller/HiveActionLogController.php`,
  `src/CalendarActionAccessControlHandler.php`,
  `src/HiveActionLogAccessControlHandler.php`, `src/ApiaryAccessTrait.php`,
  `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `src/Entity/CalendarAction.php`, `src/Entity/HiveActionLog.php` (both
  gained an `'access'` handler entry).
- No schema changes in this task — no update hook added.
- Not yet done (out of scope here, tracked elsewhere): the existing
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` has no
  `calendar_action`/`hive_action_log` test cases of its own yet — it just
  happens to still pass because it doesn't test the new branches. Worth
  adding explicit coverage as part of
  [[0024-calendar-test-coverage]].

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0004-custom-controllers-over-view-builders]], [[0013-breadcrumb-policy]], [[0020-access-parity-custom-routes]], [[0018-csrf-and-safe-http-methods]]
- Commits::
