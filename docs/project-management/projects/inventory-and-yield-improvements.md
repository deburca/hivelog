---
type: project
tags: [hivelog/project]
status: active
target:
created: 2026-08-20
---
# Project: Inventory & yield improvements

## Goal
A follow-up round of fixes and enhancements to the inventory and
yield/income features built across
[[inventory-tracking-and-depreciation]] and
[[honey-wax-propolis-yield-and-potential-income]], surfaced by a gap
analysis of the shipped functionality (2026-08-20) rather than by new
user-facing requirements. Two genuine bugs, four usability gaps, and
eight further-out extensions explicitly deferred by those projects'
own "out of scope" notes.

## Scope
- In scope: the 14 items listed under Tasks below, spanning both prior
  projects' entities (`InventoryItem`, `InventoryPurchase`,
  `CalendarActionItemRequirement`, `InventoryUsage`, `Product`,
  `CalendarActionProductYield`, `HarvestYield`) and the shared
  `InventoryReportController` financial report.
- Out of scope: anything not listed here. In particular, several
  "could-have" tasks below (0046 real sales ledger, 0047 cross-apiary
  aggregates) are recorded as backlog placeholders only — they may need
  their own ADR before implementation starts, and should not be treated
  as scoped/designed by this project note alone.
- Priority mapping from the gap analysis: must-have → `high`, should-have
  → `medium`, could-have → `low`. Priority reflects how broken/missing
  something is, not build order — see each task's own `depends-on`.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0040-style-hive-and-apiary-action-log-detail-tables]] — done (two
  more unstyled table classes found and fixed, matching the calendar
  action/product table fixes; verified live against `cms2`'s actual
  served CSS)
- [[0041-scope-item-and-product-autocomplete-to-current-apiary]] — done
  (one generic `ApiaryScopedSelection` handler covering both
  `inventory_item`/`product`, wired via a shared trait onto all three
  affected forms; verified end-to-end against a real Drupal site, full
  suite at 447 tests/0 failures)
- [[0042-low-stock-warning]] — done (optional per-item
  `low_stock_threshold` field + `isLowStock()`, surfaced as an inline
  "(Low Stock)" suffix on the Stock on Hand cell on both the item's own
  page and the apiary page's embedded table; full suite at 452 tests/0
  failures)
- [[0043-hide-discontinued-items-and-products-from-selection]] — done
  (sequenced directly on 0041's `ApiaryScopedSelection`: an always-on
  status filter, plus a trait behavior change so the filter applies even
  on the standalone no-apiary add form; full suite at 461 tests/0
  failures)
- [[0044-multi-year-cost-and-income-trend-view]] — done (a 5-year-plus-
  current trend table on the financial report, sharing a new
  `computeApiaryYearTotals()` helper with the existing single-year
  summary so the two can't drift apart; full suite at 461 tests/0
  failures)
- [[0045-warn-before-deleting-referenced-items-and-products]] — done
  (`getDescription()` overrides on both delete forms warn with a
  reference count when historical purchase/usage/yield records exist;
  full suite at 461 tests/0 failures)
- [[0046-real-sales-ledger]] — backlog (could-have)
- [[0047-cross-apiary-aggregate-views]] — backlog (could-have)
- [[0048-unit-conversion]] — backlog (could-have)
- [[0049-asset-disposal-and-write-off-tracking]] — backlog (could-have)
- [[0050-fifo-lot-costing]] — backlog (could-have)
- [[0051-expected-unit-price-audit-trail]] — backlog (could-have)
- [[0052-merge-usage-and-yield-form-traits]] — backlog (could-have)
- [[0053-product-category-field]] — backlog (could-have)

All six must-have/should-have tasks (0040-0045) are done as of this
update. Only the eight could-haves (0046-0053) remain, to be picked up
opportunistically — none of them block anything else in this list. 0046
and 0047 are the two most speculative; confirm real demand before
starting either.

## Open questions
- Should 0046 (real sales ledger) get its own ADR before any
  implementation work starts, given both parent projects explicitly
  deferred it as needing one? Leaning yes — it changes ADR-0034's
  "aggregate assumption, not a ledger" decision, not just extends it.
- Does 0052 (merging the two form traits) still make sense once 0041's
  autocomplete scoping and 0043's discontinued-item filtering are done —
  both touch the same trait pair and might be a more natural moment to
  reconsider the duplication than doing it in isolation?

## Related decisions
- [[0027-inventory-tracking-and-depreciation]]
- [[0034-honey-wax-propolis-yield-and-potential-income]]
- [[inventory-tracking-and-depreciation]] (parent project)
- [[honey-wax-propolis-yield-and-potential-income]] (parent project)
