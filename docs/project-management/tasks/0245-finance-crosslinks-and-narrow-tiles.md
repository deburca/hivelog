---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-29
branch: ui/inventory-crosslinks
release: 3.6.0
depends-on: ["[[0237-ui-polish-under-beeswax]]"]
blocked-by:
---
# Task: Finance pages link to one another; stat tiles sit two to a row on a landscape phone

## Context
Found on an iPhone, portrait and landscape. Inventory Items linked to Purchases but not to the financial report, the
report linked to nothing, and Purchases linked to both, so the four pages (Items, Purchases, Products, Report) read
differently. Separately, a phone held sideways is 480 to 768px wide, which the stat tiles treated as a phone in
portrait: one full-width tile per row.

## Acceptance criteria
- [x] Items gains "View Financial Report"; Purchases and Products keep theirs
- [x] The combined and per-apiary reports offer "View Inventory Items", "View Purchases", "View Products" (and the per-apiary one "View All Apiaries"), at the right of the year-selector row, each only where the viewer may open it (`InventoryReportController::financeLinks()`)
- [x] Stat tiles: two to a row from 481 to 768px, one to a row at 480px and below
- [x] `ListCrossLinksTest::testFinancePagesLinkToOneAnother`

## Related
- Follows:: [[0172-decide-nav-reachability-of-calendar-logs-and-report]]
