---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[honey-wax-propolis-yield-and-potential-income]]"
area: tests
created: 2026-08-20
branch: feature/0039-yield-and-income-test-coverage
release:
depends-on: ["[[0038-potential-income-in-the-cost-report]]"]
blocked-by:
---
# Task: Yield and income feature test coverage backstop

## Context
Each of [[0035-product-catalog-entity-and-ui]] through
[[0038-potential-income-in-the-cost-report]] already lists its own kernel
tests in its acceptance criteria — this task exists purely as a backstop,
mirroring [[0033-inventory-test-coverage]]'s own framing exactly: run the
full suite after every task above is done, close any coverage gaps that
emerged from integration between tasks (rather than from any single task
in isolation), and confirm no regressions in existing calendar/hive/
queen/inventory coverage.

## Acceptance criteria
- [x] Full kernel + unit suite passes (`--group hivelog`), zero
      errors/failures, against a real Drupal site with
      `SIMPLETEST_DB=mysql` (matching CI's backend, per the lesson from
      [[0033-inventory-test-coverage]] — sqlite surfaced two unrelated
      flakes there that don't reproduce under MySQL).
- [x] Access-control parity coverage for all three new entity types
      (`Product`, `CalendarActionProductYield`, `HarvestYield`) — audited
      first: `ProductAccessTest` (9 tests), `CalendarActionProductYieldAccessTest`
      (8 tests), `HarvestYieldAccessTest` (7 tests) already existed from
      tasks 0035-0037 and covered the full owner/beekeeper/outsider ×
      view/update/delete × public/private matrix. No gap found here,
      exactly matching 0033's own outcome.
- [x] `YieldEndToEndTest::testYieldAndIncomeReportAgreeOnPreFilledQuantity`
      — creates a `Product` and a `CalendarActionProductYield` for it,
      reads the form's own pre-filled default (rather than hardcoding
      it), reports `done` submitting exactly that default, and asserts
      the resulting `HarvestYield` row and the financial report's
      potential-income total agree.
- [x] `YieldEndToEndTest::testUsageAndYieldTogetherReflectInStockOnHandAndIncome`
      — a calendar action with *both* a `CalendarActionItemRequirement`
      (jars) and a `CalendarActionProductYield` (honey) reported `done`
      together in one save: asserts both an `InventoryUsage` row and a
      `HarvestYield` row are created from the same submission, and that
      `InventoryItem::getStockOnHand()` and the report's income figure
      both reflect it. This was the one scenario no single task 0035-0038
      tested end-to-end on its own.
- [x] Net-figure loss-year coverage — already satisfied by
      `InventoryCostReportTest::testNetFigureIsSignedNegativeForLossYear`,
      written as part of [[0038-potential-income-in-the-cost-report]]
      itself. No gap here; nothing new added.
- [x] Regression check: confirmed via `git log`/`git show` audit that
      `HiveTest.php` and `QueenTest.php` were never touched by any commit
      in this project (tasks 0035-0039), and that
      `EmbeddedTableFilterPaginationTest.php`,
      `ApiaryCalendarChecklistTest.php`, `HiveCalendarChecklistTest.php`,
      and `ControllerCacheMetadataTest.php` received only mechanical
      `installEntitySchema()` additions (`product`,
      `calendar_action_product_yield`) — no test logic changed. All pass
      under the full MySQL run below.

## Implementation notes
- Run via the project's documented command (see `README.md`'s Testing
  section) against `cms2` (or an equivalent real Drupal install) — `php
  -l` is not a substitute for actually running PHPUnit, and neither is a
  sqlite-backed run alone (see the acceptance criteria above).
- If the full-suite run surfaces a real bug rather than a missing test,
  fix it here rather than filing a separate task, and note it in this
  task's own commit — same discipline
  [[0033-inventory-test-coverage]] and earlier tasks this session
  followed.
- Expect the same class of "unconditional query breaks an older test's
  schema" regression this entire session has repeatedly hit whenever a
  shared controller/form method (`HiveActionLogForm::save()`,
  `ApiaryController::view()`, `CalendarActionController::view()`) gains a
  new unconditional query — search for every kernel test that calls
  those methods directly and check its `setUp()` before considering this
  task done, not just the tests this project's own new files added.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 441 tests, 0 failures/errors (up from 439
  before this task).
- No real bug surfaced during this task's full-suite run — the run only
  confirmed a genuine coverage gap (the two new
  `YieldEndToEndTest` scenarios), not a code defect, matching
  [[0038-potential-income-in-the-cost-report]]'s own outcome.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits:: a955296 (`YieldEndToEndTest`: cross-task integration tests)
