---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: reporting
created: 2026-09-08
branch: feature/0065-report-table-number-alignment
release:
depends-on:
blocked-by:
---
# Task: Right-align figures in the financial-report tables

## Context
On `/hivelog/apiaries/financial-report` (and the per-apiary report) the
amount columns were left-aligned, so the figures didn't line up down each
column, and the combined report's "All apiaries" total row was built with
`header => TRUE` cells — rendering as `<th>` (browser bold + centred),
which reads as a different typeface from the `<td>` data rows.

## Acceptance criteria
- [x] **`InventoryReportController::combinedReport()`** — the total row is
      now plain cells with a `hivelog-inventory-report-total` row class
      (no `header => TRUE`), so it keeps the data rows' typeface and
      alignment; emphasis comes from CSS. The summary table gains a
      `hivelog-inventory-report-table--labelled` modifier (its first
      column is the apiary-name label).
- [x] **`css/hivelog.tables.css`** — financial-report figures are
      `text-align: right` with `font-variant-numeric: tabular-nums`;
      `thead th` included in the selectors to outrank the generic
      "left-align titles" rule. Exceptions: the first column of the
      `--labelled` summary and of the trend tables (apiary / year) stays
      left; the breakdown table (Item / Type / Quantity text) right-aligns
      only its last column (Amount). `.hivelog-inventory-report-total td`
      gets `font-weight: 600` + a `border-top` rule. Breakdown / trend
      sections get a `margin-top`.
- [x] Tests: `CombinedFinancialReportTest::testSummaryTableAlignmentHooks`
      asserts the `--labelled` class and the plain-cell total row with
      its class. The trend-table tests still read `#rows` as flat arrays
      (row structure unchanged there). phpcs clean; 17 report tests green.

## Implementation notes
- Key files: `src/Controller/InventoryReportController.php`,
  `css/hivelog.tables.css`,
  `tests/src/Kernel/CombinedFinancialReportTest.php`.
- Trend-row `#rows` stay flat `[year, n, n, …]` so
  `InventoryCostReportTest` / `CombinedFinancialReportTest`'s
  `$row[0]` / `$row[5]` assertions keep working — alignment there is
  pure CSS via the `.hivelog-inventory-report-trend` container.
- The per-apiary `costReport()` summary has no label column, so it
  omits `--labelled` and every cell right-aligns — correct, all five are
  amounts.
- The beeswax skin (task 0061, cms2) drops its earlier heuristic
  selectors and now just recolours the total row and sets the mono
  figure face off these same classes.

## Related
- Follows:: [[0059-combined-financial-report]], [[0061-beeswax-hivelog-skin]]
- Commits::
