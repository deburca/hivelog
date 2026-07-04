---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: tests
created: 2026-07-03
branch: feature/0024-calendar-test-coverage
release:
depends-on: ["[[0017-calendar-action-entity-and-schema]]", "[[0018-hive-action-log-entity-and-schema]]", "[[0019-calendar-routing-controllers-and-access]]", "[[0020-apiary-and-hive-calendar-ui]]", "[[0021-hive-calendar-filtering-and-report-actions]]", "[[0022-seed-default-calendar-on-apiary-creation]]", "[[0023-link-hive-action-log-to-inspection]]"]
blocked-by:
---
# Task: Kernel test coverage for the seasonal calendar

## Context
Closes out the project with test coverage matching the existing suite's
depth (`tests/src/Kernel/HiveTest.php`, `QueenObservationTest.php`,
`ApiaryScopedAccessTest.php`) per [[0008-testing-strategy]]. Converts the
extensive manual `drush php:eval` verification performed throughout
[[0017-calendar-action-entity-and-schema]] through
[[0023-link-hive-action-log-to-inspection]] into permanent, automated
regression coverage.

## Acceptance criteria
- [x] `tests/src/Kernel/CalendarActionTest.php`: CRUD, required-field
      validation (`apiary`, `title`, `description`, `week_start`),
      `category` allowed-value validation, `enabled`/`recurring` default to
      `TRUE`, the `min`/`max` (1–53) constraint rejects out-of-range
      `week_start`/`week_end` values, and `week_end < week_start` is
      rejected on save.
- [x] `tests/src/Kernel/HiveActionLogTest.php`: CRUD, required-field
      validation (`hive`, `calendar_action`), `status` allowed-value
      validation (`pending`/`done`/`ignored`), `year` defaults to the
      current calendar year, `week_completed` range validation, and an
      explicit test confirming multiple logs per
      `(hive, calendar_action, year)` are permitted.
- [x] Extended `tests/src/Kernel/ApiaryScopedAccessTest.php` with two new
      sections (calendar action, hive action log) covering the
      `ApiaryAccessTrait::resolveApiary()` matrix — own/any view/edit/
      delete parity, owner-only delete for calendar actions (mirrors
      Hive), owner-or-creator delete for hive action logs (mirrors
      HiveInspection). Also extended `testAnyPermissionBypassesMembership`
      and the large `testCrossUserEditScenario` end-to-end scenario to
      include both new entity types.
- [x] Extended `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`
      with `entity.calendar_action.*` / `entity.hive_action_log.*` `applies()`
      and `build()` cases, plus the dual-parameter
      `hivelog.hive_action_log.add` case explicitly asserting the calendar
      action does **not** get an erroneous crumb there (previously only
      hand-verified).
- [x] Extended `tests/src/Kernel/ControllerCacheMetadataTest.php` — the
      apiary/hive view tests now also create a `calendar_action`/
      `hive_action_log` and assert their list + entity cache tags are
      present; added two new dedicated tests for
      `CalendarActionController`/`HiveActionLogController` (the latter
      also covering the linked-inspection cache tag); extended the
      dependency-injection smoke test to cover both new controllers.
- [x] New `tests/src/Kernel/HiveCalendarChecklistTest.php` covering all
      five behaviours called out in this task, each verified against the
      real controllers (not reproduced logic): disabled actions hidden
      from apiary/hive views but visible in the collection list builder;
      the checklist's unreported/current-year default; the next-year
      preview surfacing a recurring action as unreported; reporting
      removing an item from the default view and re-surfacing it under
      the matching status filter; and bulleted `description` rendering
      with an explicit `<script>`-payload XSS guard.
- [x] New `tests/src/Kernel/CalendarActionSeedingTest.php`: exact seeded
      count (31, read from the constant itself so the test doesn't need
      updating if the list grows), spot-checked content, all seeded rows
      pass full `validate()`, no duplication on update, and a genuine
      fault-injection test (see below) for the "seeding failure doesn't
      block apiary creation" requirement.
- [x] New `tests/src/Kernel/HiveActionLogInspectionLinkTest.php`: a
      checked "done" report creates exactly one linked `HiveInspection`
      with the expected `action_taken` text and redirect; unchecked or
      `ignored` never creates one (even if force-submitted); a user
      without `add hive inspection` never sees the checkbox and cannot
      trigger creation even via a forced submission; and editing an
      already-linked log does not re-offer the checkbox.
- [x] All new/extended tests carry `#[Group('hivelog')]` (inherited from
      the class-level attribute in every file) and pass under the
      documented `ddev exec` command.
- [x] `composer lint` / `composer stan` — see the Verification section
      below for what was actually checked and why the two commands behave
      differently in this repo.

## Verification (against `/Users/paddy/Development/cms2`)
- **Full suite, final state: 178 kernel tests / 3175 assertions and 70
  unit tests / 249 assertions — all pass, zero errors, zero PHP notices.**
  (Up from the pre-task baseline of 124 kernel / 54 unit.)
- **Found and fixed a real notice-level bug of my own** while first
  running the suite: `RendererInterface::renderInIsolation()` takes its
  argument by reference; four lines in
  `HiveCalendarChecklistTest.php` passed a controller's `view()` call
  directly instead of assigning it to a variable first (every other test
  in the codebase already does this correctly), triggering "Only
  variables should be passed by reference" notices. Fixed by assigning to
  an intermediate variable, matching the established convention.
- `composer lint` (`phpcs --standard=Drupal,DrupalPractice`): all 8
  new/extended test files, plus the calendar-related `src/` changes made
  across this project, are **error-free**. Two genuinely new errors were
  introduced and fixed during this task (a >120-column array in
  `HiveController.php`'s new "View Inspection" button, and a docblock
  `@param` description starting with a backtick instead of a capital
  letter) — both confirmed via `git diff`/`git show HEAD` to be
  newly-introduced rather than pre-existing. Remaining phpcs errors
  (`Queen.php`, `HiveController.php`'s weight-histogram `$month_names`
  array) were confirmed via `git show HEAD` to **predate this entire
  session** — not this task's responsibility. Real CI runs phpcs with
  `--warning-severity=0`, so the remaining style *warnings* in these
  files (long docblock summary lines, "blank line after inline comment"
  section dividers) — all matching pre-existing conventions used
  throughout each file — do not affect the actual gate.
- `composer stan` (`phpstan --level=2`): running it standalone (without a
  full `drupal/recommended-project` scaffold) reproduces ~160
  Drupal-stub-related false positives (`EntityInterface::get()`,
  `FieldDefinitionInterface::setLabel()`, `new static()`, etc.) across
  **every** entity/list-builder file in the module, old and new alike —
  confirmed by direct comparison (e.g. `CalendarAction.php`'s
  `$fields['uid']->setLabel()` call is flagged identically to the exact
  same pattern in `Apiary.php`, `Hive.php`, `Queen.php`, `HiveInspection.php`,
  and `QueenObservation.php`). Checked `.github/workflows/ci.yml` directly:
  this is a **known, documented, `continue-on-error: true` step** in the
  real CI pipeline specifically because bare phpstan level 2 can't resolve
  Drupal's field-definition fluent API without a full Drupal core
  bootstrap (`mglaman/phpstan-drupal`'s extension needs a real
  `drupal_root`, which only exists once CI builds the full scaffold in
  the `test` job) — the workflow's own comment states "phpcs and PHPUnit
  remain the hard gates." My new code introduces no *new class* of
  phpstan complaint beyond what every pre-existing entity/list-builder
  file already exhibits.
- All test-created database rows are properly isolated to each kernel
  test's own per-method schema (`KernelTestBase` installs a fresh,
  separately-prefixed table set per test) — confirmed the persistent
  `cms2` dev database's `hivelog_calendar_action`/`hivelog_hive_action_log`
  tables are still empty after the full run, and the table-rename
  fault-injection test in `CalendarActionSeedingTest.php` restores the
  real table name in a `finally` block regardless of assertion outcome.

## Implementation notes
- Key files: all 8 listed in the acceptance criteria above.
- `CalendarActionSeedingTest::testSeedingFailureDoesNotBlockApiaryCreation()`
  reproduces the exact fault-injection technique used manually in
  [[0022-seed-default-calendar-on-apiary-creation]] (rename the live
  table via `\Drupal::database()->schema()->renameTable()`, attempt the
  operation, restore in a `finally` block) — this is a legitimate,
  repeatable way to force a genuine database exception inside a kernel
  test's isolated schema, rather than mocking around the real behaviour.
- `HiveActionLogInspectionLinkTest.php` calls `HiveActionLogForm::save()`
  directly (a public method) with a manually-populated entity and a bare
  `FormState`, rather than driving a full `\Drupal::formBuilder()->
  submitForm()` cycle — the latter proved fragile for
  `entity_reference_autocomplete` widgets outside a real HTTP POST when
  first attempted by hand during [[0023-link-hive-action-log-to-inspection]].
  Calling `save()` directly tests the actual business logic that matters
  without fighting Drupal's form-submission internals.
- Functional coverage (`tests/src/Functional/`) remains optional follow-up,
  per [[0008-testing-strategy]] and this task's original scope — not
  added here.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]], [[0008-testing-strategy]], [[0020-access-parity-custom-routes]], [[0017-output-sanitisation-policy]]
- Commits::
