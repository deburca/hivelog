---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: routing
created: 2026-09-28
branch: feature/0160-financial-report-crosslink-on-products-list
release:
depends-on:
blocked-by:
---
# Task: "View Financial Report" cross-link on the Products list

## Context
User recalled a page showing costs and products/yields over several
years that had "disappeared from the navigation" and asked, if it's
still in the project but just unreachable from nav, to surface it from
the Products page.

Investigation confirmed the report is not gone, never had a nav entry
to begin with, and was excluded on purpose: `InventoryReportController::
combinedReport()` (route `hivelog.apiaries.financial_report`, path
`/hivelog/apiaries/financial-report`) — a per-year cost-vs-income
summary across every apiary the caller can view, plus a 6-year trend
table (current year + 5 prior — "past several years", not literally 4).
Task 0120 ("Setup"/two-tier-nav item registry work) explicitly decided
neither this nor the per-apiary `hivelog.apiary.inventory_cost_report`
belongs in `HivelogAppNavBuilder::builtInItems()`: *"a report is a
different feature from 'manage apiaries'... The combined financial
report needed no entry either."* Its only existing access paths are a
"View Financial Report" button on the apiary canonical page
(`ApiaryController.php`) and the dashboard's "Net YTD" stat tile.

Rather than reopen that nav-registry decision (which would also need
touching `HivelogMenuLinks`, the two-tier grouping, and re-litigating
0120's own reasoning), this adds the one cross-link the user actually
asked for — the same lightweight pattern task 0159 just established for
Hives/Inspections/Queen Observations, and the same one
`InventoryPurchaseListBuilder`'s "View Inventory Items" already is:
`ProductListBuilder`'s heading gets a "View Financial Report" button
next to "Add Product", since Products is the entity type whose income
half the combined report is actually summarizing.

## Acceptance criteria
- [x] `ProductListBuilder::getHeadingActions()` returns a second button,
      "View Financial Report", linking to `hivelog.apiaries.financial_report`
      (no `variant` — "Add Product" stays the sole primary action).
- [x] Verified live on `cms2`: `/hivelog/products` shows both buttons;
      "View Financial Report" opens the combined report.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key file: `src/ProductListBuilder.php` (docblock + `getHeadingActions()`
  only).
- No entity schema change, no new route → **no update hook required**.
- Deliberately did *not* add a nav-strip/main-menu entry for either
  report route — that was task 0120's own considered decision, not an
  oversight, and reopening it is out of scope for what the user asked.
- Deliberately did *not* also cross-link from Inventory Items/Purchases
  — the user named the Products page specifically, and the report's
  income figures (not its cost figures) are the half Products actually
  represents.
- `hivelog.apiaries.financial_report`'s own permission requirement
  (`view own inventory item+view any inventory item+administer
  hivelog`) is a different permission than Products' own
  (`view own product+view any product+administer hivelog`) — a user
  with only product-view access and no inventory-item access would hit
  Drupal's access-denied page clicking through. Not gated here, since
  no existing cross-link in this codebase (`InventoryPurchaseListBuilder`'s
  "View Inventory Items", `InventoryItemListBuilder`'s "View
  Purchases", task 0159's three new ones) checks target-route access
  before rendering either — `administer hivelog` (the common case)
  bypasses all of these regardless.
- Live-verified on `cms2` (`kbg` site, after `drush cr` — the same
  stale-render-cache gotcha task 0159 hit): `/hivelog/products` shows
  "Se økonomirapport" (Danish translation of "View Financial Report")
  next to "Tilføj produkt" ("Add Product"), `href="/hivelog/apiaries/
  financial-report"`; followed it through and confirmed the report page
  itself renders ("Financial Report: All Apiaries" title, cost/income
  tables).

## Related
- Tasks:: [[0159-parent-collection-crosslinks-on-hive-inspection-observation-lists]]
- Commits::
