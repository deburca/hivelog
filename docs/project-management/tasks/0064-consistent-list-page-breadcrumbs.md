---
type: task
tags: [hivelog/task]
status: review
priority: low
project:
area: navigation
created: 2026-09-08
branch: feature/0064-consistent-list-breadcrumbs
release:
depends-on:
blocked-by:
---
# Task: Consistent breadcrumbs on HiveLog list / report pages

## Context
`HivelogBreadcrumbBuilder::applies()` matches `entity.apiary.`,
`entity.hive.`, `entity.hive_inspection.`, `entity.queen.`,
`entity.queen_observation.`, `entity.calendar_action.`,
`entity.hive_action_log.`, `entity.apiary_action_log.` and `hivelog.`
routes — but `build()` only added a terminal (own-page-name) crumb for
`entity.apiary.collection`. So:

- `/hivelog/apiaries`, `/hivelog/inventory-items`, `/hivelog/products`, …
  showed `Home › HiveLog › <Name>` (apiary via this builder; the
  inventory / product collections are **not** matched by `applies()`, so
  the menu breadcrumb gives them the same shape).
- `/hivelog/hives`, `/hivelog/inspections`, `/hivelog/calendar-actions`,
  `/hivelog/apiaries/financial-report` showed only `Home › HiveLog` —
  `applies()` matched (suppressing the menu breadcrumb) but `build()`
  produced no terminal crumb.

Every such page should read `Home › HiveLog › <own page name>`, with the
last crumb non-clickable (the theme renders it as plain text).

## Acceptance criteria
- [x] `HivelogBreadcrumbBuilder::build()` — replace the single
      `entity.apiary.collection` special case with a `$leaf_pages` map
      covering all flat collections it handles plus
      `hivelog.apiaries.financial_report`; add the mapped terminal crumb
      and return.
- [x] After the apiary ancestor link, an `$apiary_page_crumbs` map adds a
      terminal crumb for the apiary-scoped non-canonical pages:
      `hivelog.apiary.inventory_cost_report` → "Financial Report",
      `hivelog.apiary.calendar_action.collection` → "Calendar". So those
      end `Home › HiveLog › <Apiary> › <page name>` instead of a bare
      linked apiary label.
- [x] The inventory-item / -purchase / product collections are left on
      the menu breadcrumb (not added to `applies()`, to avoid regressing
      their canonical / edit / delete pages, which have no ancestor block
      here). Output is already the desired shape.
- [x] Tests: `HivelogBreadcrumbBuilderTest` gains a data-provider case per
      flat leaf route + the combined report, and `testBuildApiaryFinancialReport`
      / `testBuildFullCalendarTerminalCrumb` for the apiary-scoped pages.
      91 tests green. phpcs clean.
- [x] `AGENTS.md` breadcrumb section documents the two maps.

## Implementation notes
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `AGENTS.md`.
- Verified on the ddev site: all six pages now end with the page name as
  the terminal crumb.
- The combined-report crumb uses the full page title
  "Financial Report: All Apiaries"; the per-apiary one is the shorter
  "Financial Report" since the apiary label already precedes it.

## Related
- Follows:: [[0061-beeswax-hivelog-skin]] (themed breadcrumb made the gap visible), [[0059-combined-financial-report]]
- Decisions:: [[0057-dashboard-information-architecture]], [[0013-breadcrumb-policy]]
- Commits::
