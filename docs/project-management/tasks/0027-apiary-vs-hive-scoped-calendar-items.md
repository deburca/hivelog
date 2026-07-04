---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: entity
created: 2026-07-04
branch: feature/0027-apiary-vs-hive-scoped-calendar-items
release:
depends-on: ["[[0026-post-testing-refinements]]"]
blocked-by:
---
# Task: Distinguish apiary-scoped vs hive-scoped calendar items

## Context
User feedback during hands-on testing: some starter-calendar items (e.g.
"Set Bait Hives / Swarm Traps") are really apiary-wide activities, not
per-hive ones — doing it once for the apiary is enough, and it
shouldn't need reporting separately on every hive's checklist. Confirmed
with the user via three clarifying questions before any implementation
started:

1. **Apiary-scoped items get full Done/Ignored tracking**, mirroring what
   hives already have — not just an informational display. This means a
   **new `ApiaryActionLog` entity** (apiary-scoped sibling of
   `HiveActionLog`), not just a display filter.
2. **Page structure**: the apiary's own canonical page shows only
   apiary-scoped items (a filtered "Seasonal Calendar" checklist,
   analogous to the hive one); a **new dedicated page** lists everything
   (both scopes) for that apiary, linked from the apiary page.
3. **Confirmed starter-calendar reclassification** (5 of the 31 items
   become `scope = apiary`, the rest stay `scope = hive`):
   - Renew Central Beehive Registration (CBR)
   - Order & Prepare Equipment for the Season
   - Apiary Site & Hygiene Check
   - Set Bait Hives / Swarm Traps
   - Apiary Record-Keeping & Season Review

This file was written as a complete implementation plan before any code
was started; see "Implementation summary (as built)" below for the small
number of places the actual implementation diverged from or clarified
the original design.

## Design

### 1. `CalendarAction.scope` field
- New `list_string` field, allowed values `hive` (default) / `apiary`.
- `hivelog_update_10017` installs the field storage definition (follow
  the exact pattern of `hivelog_update_10013`/`_10016` — read
  `CalendarAction::baseFieldDefinitions()`, call
  `installFieldStorageDefinition()` if not already present).
- In `CalendarAction::DEFAULT_STARTER_CALENDAR`, add `'scope' =>
  'apiary'` to exactly the 5 entries listed above; leave the other 26
  entries alone (they'll pick up the field's `hive` default
  automatically — no need to touch every entry).
- Add `scope` to `CalendarActionForm`'s Overview section, and to
  `CalendarActionListBuilder`'s columns, and to
  `CalendarActionController::view()`'s Overview section fields (with a
  `list_default`-style label lookup, matching how `category` is already
  rendered in each of those three places).

### 2. Filter existing views by scope
- `HiveController::buildCalendarChecklist()`: add
  `->condition('scope', 'hive')` to the calendar_action query (alongside
  the existing `enabled` condition) — apiary-scoped items must never
  appear on a hive's checklist.
- `ApiaryController::view()`'s "Seasonal Calendar" table: add
  `->condition('scope', 'apiary')` to its calendar_action query. This
  table needs to become a **checklist**, not just a plan display — see
  item 5 below (it needs Status/Report Done/Report Ignored just like the
  hive one, cross-referenced against the new `ApiaryActionLog`).

### 3. New `ApiaryActionLog` entity
Mirrors `HiveActionLog` (`src/Entity/HiveActionLog.php`) almost exactly:
- Fields: `apiary` (required, entity_reference → apiary — **not** a
  hive), `calendar_action` (required, entity_reference → calendar_action),
  `year` (required, default current year via the same
  `getDefaultValueCallback` pattern), `status` (pending/done/ignored,
  default pending), `week_completed` (optional, 1–53), `notes`
  (string_long), uid/created/changed.
- **Deliberately no `inspection` field** — the "create a linked
  HiveInspection" feature from
  [[0023-link-hive-action-log-to-inspection]] is inherently hive-scoped
  (an inspection is always of one hive); there's no apiary-level
  equivalent. Not in scope for this task.
- No uniqueness constraint on `(apiary, calendar_action, year)`, same
  reasoning as `HiveActionLog`.
- `src/ApiaryActionLogListBuilder.php`, `src/Form/ApiaryActionLogForm.php`
  + `ApiaryActionLogDeleteForm.php` (mirror the Hive Action Log versions,
  minus the inspection-linking checkbox/logic).
- `src/ApiaryActionLogAccessControlHandler.php`: delete access is
  **owner-or-creator** (mirrors `HiveActionLogAccessControlHandler` /
  `HiveInspectionAccessControlHandler` — a log is a per-report record,
  not foundational structure like `CalendarAction`/`Hive`, which are
  owner-only-delete).
- `ApiaryAccessTrait::resolveApiary()` gains a new branch:
  `apiary_action_log` → `apiary` directly (simplest branch in the
  trait — no traversal needed, unlike `hive_action_log` → `hive` →
  `apiary`).
- New permissions (7, matching the established own/any × operation
  pattern): `view own/any apiary action log`, `add apiary action log`,
  `edit own/any apiary action log`, `delete own/any apiary action log`.
- `hivelog_update_10018` installs the new entity type (mirrors
  `hivelog_update_10015` for `hive_action_log`).
- Add `apiary_action_log` to `hivelog_uninstall()`'s cleanup list,
  positioned before `apiary` (it references apiary directly, no other
  dependents).

### 4. Routing, controller, breadcrumbs for `ApiaryActionLog`
- Standard CRUD routes: `entity.apiary_action_log.collection/canonical/
  edit_form/delete_form`.
- Scoped-add route: `hivelog.apiary_action_log.add` →
  `/hivelog/apiary/{apiary}/calendar-action/{calendar_action}/log/add`
  — this is the module's **second** dual-entity-parameter route (after
  `hivelog.hive_action_log.add`), so it needs the exact same defensive
  check in the controller's `addForm()` (calendar_action's `apiary`
  target must match the route's `apiary` — throw
  `NotFoundHttpException` otherwise) and the exact same breadcrumb
  guard treatment as item 6 below.
- `ApiaryActionLogController` (view/title/addForm), mirroring
  `HiveActionLogController` minus the inspection-linking pieces.
  `addForm()` reads the same `?status=` query default pattern as
  `HiveActionLogController::addForm()`.
- Breadcrumbs: extend `HivelogBreadcrumbBuilder::applies()` with
  `entity.apiary_action_log.*` (the `hivelog.apiary_action_log.add`
  scoped-add route is already covered by the generic `hivelog.` prefix,
  same as every other scoped-add route — no separate entry needed).
  Add a `build()` block for `apiary_action_log` → `apiary` (single
  ancestor level, simpler than the hive one). **Important**: the
  existing `calendar_action` breadcrumb block (guarded to
  `entity.calendar_action.*` routes only, added in
  [[0019-calendar-routing-controllers-and-access]] for exactly this
  reason) already prevents a spurious crumb on
  `hivelog.apiary_action_log.add` — confirm this with a test case
  mirroring `testBuildHiveActionLogAddRouteDoesNotAddCalendarActionCrumb`
  in `HivelogBreadcrumbBuilderTest.php`, but no new guard code should be
  needed since the existing one is already route-name-based, not
  parameter-presence-based.

### 5. Apiary-level checklist UI (`ApiaryController`)
- Add a `buildApiaryCalendarChecklist(Apiary $apiary, int $year, string
  $status_filter): array` method, structurally identical to
  `HiveController::buildCalendarChecklist()` but querying
  `calendar_action` with `condition('scope', 'apiary')` instead of
  `condition('enabled', TRUE)` alone, and cross-referencing
  `apiary_action_log` instead of `hive_action_log` (condition on
  `apiary` instead of `hive`).
- Add `extractCalendarFilters()` (can literally reuse
  `HiveController`'s implementation verbatim, or extract to a shared
  trait if it turns out truly identical — check whether duplicating per
  the module's established "duplicate small controller helpers" pattern
  is still the right call at this point, since it's now needed
  identically in two places; a trait might be justified here as the
  third occurrence of near-identical logic).
- Add the same "current week" heading + `Timing` /
  `Unreported (Due now/Overdue/Upcoming)` treatment built in
  [[0026-post-testing-refinements]] item 1 — this feature should already
  generalize cleanly since the underlying logic doesn't care whether
  the parent is a hive or an apiary.
- Add "Report Done" / "Report Ignored" buttons linking to
  `hivelog.apiary_action_log.add` with the same `?status=` query-default
  pattern.
- Cache metadata: add `apiary_action_log` list cache tag + per-row
  cacheable dependencies, and the same `setCacheMaxAge
  (secondsUntilNextIsoWeek())` bound as the hive page already has.

### 6. Generalize `HivelogCalendarFilterForm`
- Currently `buildForm(array $form, FormStateInterface $form_state,
  ?Hive $hive = NULL)` — only used for the Reset button's target URL.
  Change the parameter to accept a `?Url $reset_url = NULL` directly
  (computed by the caller) instead of a typed `Hive` entity, so the
  exact same form class serves both `HiveController` (passing
  `Url::fromRoute('entity.hive.canonical', ['hive' => $hive->id()])`)
  and `ApiaryController` (passing `Url::fromRoute('entity.apiary.canonical',
  ['apiary' => $apiary->id()])`) without a near-duplicate form class.
  Double-check `\Drupal::service('form_builder')->getForm($class, $arg)`
  still resolves the `$arg` correctly against the new parameter type
  (Drupal's form-arg-passing is positional, so this should just work).

### 7. New "Full Calendar" page (both scopes, one apiary)
- New route, e.g. `hivelog.apiary.calendar_action.collection` →
  `/hivelog/apiary/{apiary}/calendar` (parameter: `apiary` only — this
  means it's automatically covered by `HivelogBreadcrumbBuilder`'s
  existing generic `hivelog.` prefix match and the existing generic
  `apiary` parameter block in `build()`; no breadcrumb code changes
  needed for this specific route).
- New controller method, e.g. `ApiaryController::fullCalendar(Apiary
  $apiary)` — lists **every** `enabled` `CalendarAction` for the apiary
  regardless of scope (both `hive` and `apiary`), sorted by
  `week_start`, with a `Scope` column (Apiary/Hive) so it's visually
  clear which is which. This is explicitly a **reference/management
  view, not a reporting mechanism** — no Status/Report buttons here;
  actual reporting still happens on the apiary page (for apiary-scoped
  items) or each hive's page (for hive-scoped items). This is
  essentially what `ApiaryController::view()`'s calendar table looked
  like *before* this task's scope-filtering change, just relocated to
  its own page and apiary-scoped (rather than the existing
  `entity.calendar_action.collection`, which is unfiltered across every
  apiary the user can access).
- Add a "View Full Calendar" link/button on the apiary's main page
  (`ApiaryController::view()`), near the (now filtered) Seasonal
  Calendar heading, pointing at the new route.

### 8. Tests
Mirror the existing calendar test suite's structure and depth
([[0024-calendar-test-coverage]]):
- `tests/src/Kernel/ApiaryActionLogTest.php` — CRUD, required-field
  validation, `status`/`year` defaults, no uniqueness constraint (same
  shape as `HiveActionLogTest.php`).
- Extend `tests/src/Kernel/CalendarActionTest.php` for the `scope`
  field's allowed values + default.
- Extend `tests/src/Kernel/ApiaryScopedAccessTest.php` with an
  `apiary_action_log` section (own/any parity, owner-or-creator delete).
- Extend `tests/src/Kernel/ControllerCacheMetadataTest.php` for the new
  `ApiaryActionLogController` and the apiary page's new cache
  dependencies.
- Extend `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`
  with `entity.apiary_action_log.*` route cases and a
  `hivelog.apiary_action_log.add` dual-parameter case (mirroring the
  hive one).
- New test covering: a `scope = apiary` `CalendarAction` never appears
  on any hive's checklist; a `scope = hive` `CalendarAction` never
  appears on the apiary's (filtered) checklist; both appear together on
  the new Full Calendar page; reporting an apiary item done/ignored
  behaves identically to the hive equivalent (default-unreported view,
  status filter, year preview, current-week timing).
- Extend `tests/src/Kernel/CalendarActionSeedingTest.php` to confirm
  exactly 5 of the 31 seeded rows have `scope = apiary` and the rest
  `scope = hive`.

### Verification checklist (completed)
- [x] Full existing suite re-run with **no regressions**, plus new tests
      this task added: started at 248 kernel + unit tests, ended at 285
      (248 pre-existing + 6 `ApiaryActionLogTest` + 2 `CalendarActionTest`
      + 1 `CalendarActionSeedingTest` + 8 `ApiaryScopedAccessTest` (plus
      extending 3 existing tests in that file) + 1
      `ControllerCacheMetadataTest` + 6 `ApiaryCalendarChecklistTest` (new
      file) + 6 `HivelogBreadcrumbBuilderTest` (route providers + build
      tests), run via the canonical DDEV command in AGENTS.md — 285 tests,
      4035 assertions, all green.
- [x] Manual `drush php:eval` smoke test against a real DDEV site: created
      an apiary and confirmed exactly 5 of the 31 seeded starter items
      have `scope = apiary`; confirmed all 6 new/changed routes
      (`entity.apiary_action_log.*`, `hivelog.apiary_action_log.add`,
      `hivelog.apiary.calendar_action.collection`) resolve via the route
      provider after a router rebuild. Full page-render coverage (scope
      filtering on both pages, Report Done/Ignored on an apiary item,
      access control for a non-admin apiary member, breadcrumbs on the
      new dual-parameter route, cache max-age bounding) is exercised by
      the kernel test suite above, which renders the real controllers
      through Drupal's renderer with real permissions/entity access.
- [x] `composer lint` (phpcs, `--warning-severity=0` matching the real CI
      invocation) clean on every new/changed file. Two pre-existing
      errors were found in `Queen.php`/`HiveController.php` (the
      "each index in a multi-line array must be on a new line" sniff,
      confirmed via `git diff`/GitHub Actions run history to predate
      this task — CI was green on the parent commit with this exact
      code, so this was a drift between a newer local `drupal/coder`
      install and CI's cached older one) — fixed via `phpcbf` as a
      trivial, behaviour-free drive-by since they were auto-fixable and
      it removes the drift risk entirely. `composer stan` reproduces the
      same pre-existing, `continue-on-error: true` Drupal-stub false
      positives documented in [[0024-calendar-test-coverage]]; no new
      class of complaint introduced.

## Implementation notes
- This is comparable in size to tasks 0018 + 0019 + 0020 + 0021
  combined, applied to a new entity — budget accordingly; don't try to
  rush it into a single sitting without checkpointing.
- Reuse code/patterns from `HiveActionLog`/`HiveActionLogController`/
  `HiveActionLogForm`/`HiveActionLogAccessControlHandler` as directly as
  possible — this task is almost entirely "the same thing, one level up
  the hierarchy," not new architecture.

### Implementation summary (as built)
- `extractCalendarFilters()`, `calendarChecklistEmptyMessage()`, and
  `pendingActionTimingLabel()` were **duplicated** into `ApiaryController`
  rather than extracted to a shared trait — this is only the second
  occurrence of each (not the third, as item 5's design note
  speculated), so the module's established "duplicate small controller
  helpers" pattern still applies; revisit if a third occurrence appears.
- `HivelogCalendarFilterForm::buildForm()`'s `$reset_url` parameter change
  worked exactly as anticipated — `\Drupal::formBuilder()->getForm($class,
  $arg)` resolves positionally, no special handling needed.
- The existing `calendar_action` breadcrumb guard (`str_starts_with(...,
  'entity.calendar_action.')`) needed **no changes** to correctly avoid a
  spurious crumb on `hivelog.apiary_action_log.add` — confirmed via
  `testBuildApiaryActionLogAddRouteDoesNotAddCalendarActionCrumb`, exactly
  as item 4 predicted.
- `ApiaryController::fullCalendar()` and `fullCalendarTitle()` were added
  as new public controller methods; the Full Calendar page needed no new
  breadcrumb code (covered by the existing generic `apiary` parameter
  block), confirmed by `hivelogCustomRouteProvider`'s new `full calendar`
  case in the breadcrumb unit test.
- Several **pre-existing** tests that exercise `ApiaryController::view()`
  or `HiveController::view()` needed `scope` values added to their
  `CalendarAction::create()` fixtures once the two checklists became
  scope-filtered (they previously relied on the default, unfiltered
  "Seasonal Calendar" table): `HiveCalendarChecklistTest`
  (`testDisabledCalendarActionHiddenFromViewsButVisibleInCollection` was
  restructured into hive-scoped + apiary-scoped fixture pairs) and
  `ControllerCacheMetadataTest::testApiaryViewCacheMetadata`. Eight test
  files also needed `$this->installEntitySchema('apiary_action_log')`
  added alongside their existing `hive_action_log` install, since the
  apiary view now queries that storage.
- New `tests/src/Kernel/ApiaryCalendarChecklistTest.php` mirrors
  `HiveCalendarChecklistTest.php`'s default-status/year-preview/
  reporting-flow coverage for the apiary-scoped checklist, plus new
  coverage for `fullCalendar()` (both scopes, disabled items excluded)
  and confirms the checklist's Report buttons never construct a
  hive-scoped URL.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0003-code-defined-entity-schema]], [[0020-access-parity-custom-routes]]
- Commits::
