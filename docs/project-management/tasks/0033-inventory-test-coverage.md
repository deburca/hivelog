---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Full kernel + unit suite passes (`--group hivelog`), zero
      errors/failures, against a real Drupal site — not just `php -l`.
- [ ] Access-control parity coverage for all four new entity types
      (`InventoryItem`, `InventoryPurchase`, `CalendarActionItemRequirement`,
      `InventoryUsage`), mirroring `ApiaryScopedAccessTest`'s existing
      style for `CalendarAction`/`HiveActionLog`.
- [ ] An end-to-end integration test: create an `InventoryItem`
      (consumable) and a `CalendarActionItemRequirement` for it, report a
      `HiveActionLog` as `done` with the pre-filled quantity accepted
      as-is, and assert the resulting `InventoryUsage` row, the item's
      `getStockOnHand()`, and the cost report's total all agree.
- [ ] An end-to-end depreciation test: purchase a durable item, assert
      `getAnnualDepreciation()` is non-zero for years within its useful
      life and exactly zero for years outside it, at both boundaries.
- [ ] Regression check: existing `HiveTest`, `QueenTest`,
      `EmbeddedTableFilterPaginationTest`, and
      `ApiaryCalendarChecklistTest`/`HiveCalendarChecklistTest` suites
      still pass unmodified (or with only mechanical updates for new
      unrelated fields, e.g. new `setUp()` schema installs) — this
      project should not need to change any of that existing test logic.

## Implementation notes
- Run via the project's documented command (see `README.md`'s Testing
  section) against `cms2` (or an equivalent real Drupal install), the
  same way every other task in this session was verified — `php -l` is
  not a substitute for actually running PHPUnit.
- If the full-suite run surfaces a real bug rather than a missing test
  (as happened once already in this session — the `Link`/`Stringable`
  issue in `QueenListBuilder`/`ApiaryListBuilder`), fix it here rather
  than filing a separate task, and note it in this task's own commit.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
