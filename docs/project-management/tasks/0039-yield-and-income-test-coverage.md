---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Full kernel + unit suite passes (`--group hivelog`), zero
      errors/failures, against a real Drupal site with
      `SIMPLETEST_DB=mysql` (matching CI's backend, per the lesson from
      [[0033-inventory-test-coverage]] — sqlite surfaced two unrelated
      flakes there that don't reproduce under MySQL).
- [ ] Access-control parity coverage for all three new entity types
      (`Product`, `CalendarActionProductYield`, `HarvestYield`) — audit
      first whether each task's own tests (0035-0037) already cover this
      before writing anything new, following 0033's approach exactly
      (it found the parity coverage for inventory's four entity types
      was already complete by the time it ran, and only needed to add
      the one genuinely missing cross-task test).
- [ ] An end-to-end integration test: create a `Product` and a
      `CalendarActionProductYield` for it, report a `HiveActionLog` as
      `done` with the pre-filled quantity accepted as-is (reading the
      form's own default rather than hardcoding it, matching
      `InventoryEndToEndTest`'s pattern), and assert the resulting
      `HarvestYield` row and the cost report's potential-income total
      agree.
- [ ] An end-to-end test covering a calendar action with *both* a
      `CalendarActionItemRequirement` (jars) and a
      `CalendarActionProductYield` (honey) reported `done` together in
      one save: assert both an `InventoryUsage` row and a `HarvestYield`
      row are created correctly from the same form submission, and that
      `InventoryItem::getStockOnHand()` and the cost report's income
      figure both reflect it. This is the one scenario no single task
      0035-0038 tests end-to-end on its own — inventory usage and yield
      have to work correctly *together* on the same form.
- [ ] A net-figure test: an apiary/year with cost exceeding income,
      confirming the report's net figure renders as a signed negative
      number (not zero-floored), per
      [[0038-potential-income-in-the-cost-report]]'s acceptance
      criterion.
- [ ] Regression check: existing `HiveTest`, `QueenTest`,
      `EmbeddedTableFilterPaginationTest`,
      `ApiaryCalendarChecklistTest`/`HiveCalendarChecklistTest`, and every
      test from [[inventory-tracking-and-depreciation]] (`InventoryItemTest`,
      `InventoryPurchaseTest`, `InventoryUsageTest`,
      `InventoryUsageAccessTest`, `InventoryUsageReportingIntegrationTest`,
      `InventoryCostReportTest`, `InventoryEndToEndTest`, etc.) still pass
      unmodified, or with only mechanical `setUp()` schema-install
      additions for the three new entity types — this project should not
      need to change any of that existing test logic, matching
      [[0033-inventory-test-coverage]]'s own regression discipline.

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

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits::
