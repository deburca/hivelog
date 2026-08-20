---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `.hivelog-hive-action-log-table` and `.hivelog-apiary-action-log-table`
      added to every selector group in `css/hivelog.tables.css` (full-width/
      border-collapse, row dividers, header border, and the `@media
      (max-width: 768px)` responsive block including the first-column
      cap) — the exact same treatment `.hivelog-calendar-action-table`
      and `.hivelog-product-table` already received.
- [ ] Verified live: both detail pages render with visible row borders,
      header emphasis, and correct mobile stacking, checked against a
      real Drupal site (not just `php -l`).
- [ ] Kernel test: assert the rendered HTML for both controllers' `view()`
      contains the expected class name on the table markup (mirroring
      any existing `assertStringContainsString('hivelog-...-table', ...)`
      pattern already in this codebase, if one exists — otherwise a
      simple new assertion is enough).

## Implementation notes
- Key files: `css/hivelog.tables.css` only — no PHP changes expected,
  this is a pure CSS-selector-list gap.
- Grep the whole module for every `'#attributes' => ['class' => [...]]`
  used on a `'#type' => 'table'` render array and cross-check each class
  name against `css/hivelog.tables.css` once more before closing this
  task — the same audit that found this gap found exactly these two;
  worth re-confirming nothing else was missed.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions::
- Commits::
