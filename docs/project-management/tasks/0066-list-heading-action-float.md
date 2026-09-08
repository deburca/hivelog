---
type: task
tags: [hivelog/task]
status: review
priority: low
project:
area: theme
created: 2026-09-08
branch: feature/0066-list-heading-action-float
release:
depends-on:
blocked-by:
---
# Task: Float the collection / report action bar right, with spacing

## Context
On the dashboard the ISO-week badge + CBR line float to the right with
space above and below. On `/hivelog/apiaries`, `/hivelog/inventory-items`,
`/hivelog/products` (and the financial reports) the action bar ("Add …",
the report year selector) sat hard against the left with little or no
margin.

Root cause: the `.hivelog-list-heading` **layout** rules
(`display: flex`, `justify-content`, `margin`, and
`.hivelog-list-heading__action { margin-left: auto }`) lived in
`css/hivelog.filter-form.css` → the `hivelog/filter_form` library. But a
list builder attaches only `hivelog/buttons` for that row, and most
collection pages have no filter form, so the layout CSS never loaded —
the row was an unstyled `<div>`. The financial-report year selector had
no wrapper at all.

## Acceptance criteria
- [x] Move the `.hivelog-list-heading` / `__title` / `__action` layout
      (and its `@media` rules) from `css/hivelog.filter-form.css` to
      `css/hivelog.buttons.css`, which every list heading already
      attaches. `hivelog.filter-form.css` keeps only the filter-form
      rules; `@file` comments updated on both.
- [x] Tune the row: `justify-content: space-between` +
      `.hivelog-list-heading__action { margin-left: auto }` float the
      bar right whether or not a `__title` is present (canonical-page
      section headings still use `__title`); `margin: 1.5rem 0` gives
      the vertical breathing room.
- [x] `InventoryReportController`: new `yearSelector()` helper wraps the
      year-selector button group in a `.hivelog-list-heading` /
      `__action` row (and attaches `hivelog/buttons`). Both `costReport()`
      and `combinedReport()` use it.
- [x] Tests: `CombinedFinancialReportTest::testYearSelectorSitsInFloatedHeadingRow`.
      `ListCollectionTitleTest` / `EmbeddedTableFilterPaginationTest`
      still green (heading structure unchanged). phpcs clean.

## Implementation notes
- Key files: `css/hivelog.buttons.css`, `css/hivelog.filter-form.css`,
  `src/Controller/InventoryReportController.php`,
  `tests/src/Kernel/CombinedFinancialReportTest.php`.
- No beeswax change needed — the layout is module CSS in `hivelog/buttons`
  (loaded on every heading), and the buttons themselves already pick up
  the beeswax palette via the `--hivelog-btn-*` token override (task 0061).
- The `@media (max-width: 768px)` heading rules move too — the bar drops
  to left-aligned full-width on small screens (unchanged behaviour, now
  in the right file).

## Related
- Follows:: [[0063-strip-list-page-title-prefix]] (removed the <h3>, leaving the bare bar), [[0065-report-table-number-alignment]]
- Decisions:: [[0012-action-button-design-system]], [[0011-responsive-design-strategy]]
- Commits::
