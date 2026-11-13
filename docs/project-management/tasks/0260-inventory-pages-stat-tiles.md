---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/inventory-pages-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Inventory item, purchase and product pages as tile strips

## Context
The inventory item page shows an Overview table, a "Type & Depreciation" table and a separate "Stock on Hand" paragraph at the bottom, with low stock as text in parentheses. The purchase page has Overview, Cost and an optional Disposal table. The product page is a single Overview table. Stock on hand is the number a beekeeper opens the item page for, and it is the last thing on it.

## Acceptance criteria
- [ ] Item: Stock on hand as the lead tile (critical sub-line "Low stock", warning "Unknown"), plus Type, Unit weight, Useful life (durables), Total purchased; name and category as the page heading/subtitle
- [ ] Purchase: Quantity, Unit price, Total cost, Date, Item (linked); a Disposed tile with the reason as warning sub-line once disposed
- [ ] Product: Unit, Expected price, Status
- [ ] Item's "Stock on Hand" section and the parenthesised low-stock text are removed; durable items show no stock tile
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/InventoryItemController.php`, `InventoryPurchaseController.php`, `ProductController.php`
- Stock comes from `InventoryItem::getStockOnHand()` / `isLowStock()`; cache tags for purchases are already attached.
- The product page is low value alone; do it here for consistency.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
