---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[inventory-and-yield-improvements]]"
area: theme
created: 2026-08-20
branch: feature/0040-style-hive-and-apiary-action-log-detail-tables
release:
depends-on:
blocked-by:
---
# Task: Style the Hive/Apiary Action Log detail-view tables

## Context
Found via a code audit while investigating the identical bug on the
calendar action detail page (fixed same-day): `HiveActionLogController.php`
and `ApiaryActionLogController.php` both render their Overview/Details
sections with `.hivelog-hive-action-log-table` /
`.hivelog-apiary-action-log-table` classes, but neither class was ever
added to `css/hivelog.tables.css`'s selector groups. `/hivelog/hive-action-log/{id}`
and `/hivelog/apiary-action-log/{id}` currently render fully unstyled
tables — same live symptom the user reported for
`/hivelog/calendar-action/{id}` before that was fixed.

## Acceptance criteria
- [x] `.hivelog-hive-action-log-table` and `.hivelog-apiary-action-log-table`
      added to every selector group in `css/hivelog.tables.css` (full-width/
      border-collapse, row dividers, header border, and the `@media
      (max-width: 768px)` responsive block including the first-column
      cap) — the exact same treatment `.hivelog-calendar-action-table`
      and `.hivelog-product-table` already received.
- [x] Verified live: both detail pages render with visible row borders,
      header emphasis, and correct mobile stacking, checked against a
      real Drupal site (not just `php -l`).
- [x] Kernel test: assert the rendered HTML for both controllers' `view()`
      contains the expected class name on the table markup (mirroring
      any existing `assertStringContainsString('hivelog-...-table', ...)`
      pattern already in this codebase, if one exists — otherwise a
      simple new assertion is enough).

## Implementation notes
- Key files: `css/hivelog.tables.css` (the actual fix), plus
  `tests/src/Kernel/HiveActionLogTest.php`/`ApiaryActionLogTest.php` (one
  new regression test each) — the CSS-only file estimate held, PHP
  changes were test-only.
- Re-ran the "every `#type => table` in the module, cross-checked against
  `css/hivelog.tables.css`" audit before closing this task: 13 total
  `#type => table` usages across the module, all 13 now accounted for by
  a styled class. Nothing else missed.

## Verification
- Full targeted run against `cms2` with `SIMPLETEST_DB=mysql`:
  `HiveActionLogTest`/`ApiaryActionLogTest`, 21 tests, 0 failures/errors.
- Confirmed live: fetched `/hivelog/hive-action-log/26` and its actual
  served (aggregated) CSS file — `.hivelog-hive-action-log-table`'s
  ruleset is present in the CSS actually sent to the browser, not just
  in the source file.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions::
- Commits:: df3f44f (CSS fix + regression tests)
