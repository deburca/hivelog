---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[inventory-tracking-and-depreciation]]"
area: tests
created: 2026-08-19
branch: feature/0033-inventory-test-coverage
release:
depends-on: ["[[0032-inventory-cost-and-depreciation-report]]"]
blocked-by:
---
# Task: Inventory feature test coverage backstop

## Context
Each of [[0028-inventory-item-and-purchase-entities]] through
[[0032-inventory-cost-and-depreciation-report]] already lists its own
kernel tests in its acceptance criteria — unlike
[[seasonal-calendar-and-hive-action-tracking]], which deferred essentially
all kernel testing to one final task
([[0024-calendar-test-coverage]]), this project threads tests through
each task as it's built. This task exists as a backstop: run the full
suite (`--group hivelog`) after every task above is done, close any
coverage gaps that emerged from integration between tasks (rather than
from any single task in isolation), and confirm no regressions in
existing calendar/hive/queen coverage.

## Acceptance criteria
- [x] Full kernel + unit suite passes (`--group hivelog`), zero
      errors/failures, against a real Drupal site — not just `php -l`.
      Run with `SIMPLETEST_DB=mysql://...` against `cms2` specifically
      (matching CI's backend exactly), not the sqlite driver used
      elsewhere this session for faster local iteration — sqlite surfaced
      two pre-existing, unrelated flakes (`QueenTest::testCreateQueen`
      decimal formatting, `ApiaryCalendarChecklistTest::
      testFullCalendarFiltersNarrowResults`) that don't reproduce under
      MySQL, so MySQL is the authoritative local check going forward.
- [x] Access-control parity coverage for all four new entity types was
      already in place from each task's own tests: `InventoryItem` and
      `InventoryPurchase` via `InventoryAccessTest` (task 0029),
      `CalendarActionItemRequirement` via
      `CalendarActionItemRequirementAccessTest` (task 0030),
      `InventoryUsage` via `InventoryUsageAccessTest` (task 0031) — all
      already mirror `ApiaryScopedAccessTest`'s owner/beekeeper/outsider ×
      view/edit/delete × public/private matrix. No gap found here.
- [x] `InventoryEndToEndTest::
      testUsageStockAndCostReportAgreeOnPreFilledQuantity` — the one real
      coverage gap: reads the form's own pre-filled recipe default
      (rather than hardcoding it, so "accepted as-is" is genuinely
      exercised), reports `done`, and asserts the resulting
      `InventoryUsage` row, `InventoryItem::getStockOnHand()`, and the
      cost report's rendered total all agree on the same figure.
- [x] `InventoryEndToEndTest::
      testDepreciationIsNonZeroWithinLifeAndZeroOutsideAtBothBoundaries` —
      standalone purchase-only scenario matching this criterion's wording
      directly, complementing `InventoryCostReportTest::
      testDepreciationWindowBoundaries` (task 0032) which already covered
      the same boundaries via the report path.
- [x] Regression check: confirmed `HiveTest.php`, `QueenTest.php`, and
      `EmbeddedTableFilterPaginationTest.php` were never touched by any
      commit in this project (tasks 0028-0033); `HiveCalendarChecklistTest.php`
      and `ControllerCacheMetadataTest.php` received only the mechanical
      `installEntitySchema('calendar_action_item_requirement')` addition
      allowed by this criterion (commit 1c89d6a, task 0030) — no test
      logic changed. All pass under the MySQL run above.

## Implementation notes
- Run via the project's documented command (see `README.md`'s Testing
  section) against `cms2` (or an equivalent real Drupal install), the
  same way every other task in this session was verified — `php -l` is
  not a substitute for actually running PHPUnit.
- If the full-suite run surfaces a real bug rather than a missing test
  (as happened once already in this session — the `Link`/`Stringable`
  issue in `QueenListBuilder`/`ApiaryListBuilder`), fix it here rather
  than filing a separate task, and note it in this task's own commit.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 385 tests, 0 failures/errors, 10 deprecations
  (all pre-existing geofield/symfony deprecation noise unrelated to this
  project).
- No real bug surfaced during this task's full-suite run — unlike the
  `Link`/`Stringable` issue found earlier this session, this run only
  confirmed a genuine coverage gap (the cross-task integration test),
  not a code defect.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits:: 56d8f05 (InventoryEndToEndTest: cross-task integration +
  depreciation boundary tests)
