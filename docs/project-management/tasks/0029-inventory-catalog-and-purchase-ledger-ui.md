---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[inventory-tracking-and-depreciation]]"
area: routing
created: 2026-08-19
branch: feature/0029-inventory-catalog-and-purchase-ledger-ui
release:
depends-on: ["[[0028-inventory-item-and-purchase-entities]]"]
blocked-by:
---
# Task: Inventory catalog and purchase ledger UI

## Context
Makes [[0028-inventory-item-and-purchase-entities]]'s two entities usable:
scoped-add routes, controllers, list builders, and access control,
mirroring the existing `Hive`/`CalendarAction` patterns exactly. Also
where stock-on-hand first becomes visible, since it's the natural payoff
of having a purchase ledger.

## Acceptance criteria
- [ ] Standard entity CRUD routes for both entity types
      (`entity.inventory_item.*`, `entity.inventory_purchase.*`), plus
      apiary-scoped add routes (`hivelog.inventory_item.add`,
      `hivelog.inventory_purchase.add`) mirroring `hivelog.hive.add`.
- [ ] `InventoryItemAccessControlHandler` / `InventoryPurchaseAccessControlHandler`
      using `ApiaryAccessTrait::resolveApiary()`, matching
      `CalendarActionAccessControlHandler`.
- [ ] `InventoryItemListBuilder` (self-built "Add Inventory Item" heading
      using the `hivelog:entity-table` SDC component, per the pattern
      `ApiaryListBuilder`/`QueenListBuilder` already use — **not** the
      default core table + Local Actions block, which this module's
      front-end menu placement can't rely on) — columns: name, category,
      unit, type, status, current stock (consumables only — see below).
- [ ] `InventoryPurchaseListBuilder`, same self-built-heading pattern —
      columns: item, date, quantity, unit price, total cost, supplier.
- [ ] Stock-on-hand helper: `InventoryItem::getStockOnHand(): ?float`
      (returns `NULL` for durable items, since "stock" isn't a meaningful
      concept for them) — computed as `Σ InventoryPurchase.quantity −
      Σ InventoryUsage.quantity` for the item, mirroring
      `Hive::getActiveQueen()`'s query-not-stored-state style. Note:
      `InventoryUsage` doesn't exist until
      [[0031-inventory-usage-and-action-log-reporting-integration]] — until
      then this method only sums purchases (usage side simply returns 0
      rows, so the method is correct from day one and needs no later
      rework).
- [ ] Apiary canonical page: an "Inventory" section (or a dedicated
      "View Inventory" button, matching the "View Full Calendar" button
      pattern already added to the hive page) linking to the item
      collection for that apiary.
- [ ] Kernel tests: route access parity (mirroring
      `ApiaryScopedAccessTest`'s existing coverage style for
      `CalendarAction`), list builder rendering, stock-on-hand
      calculation with multiple purchases.
- [ ] `ddev drush updb -y && ddev drush cr` clean (no schema change
      expected in this task, but re-verify since routing/permissions
      config can still need a cache rebuild).

## Implementation notes
- Key files: `src/Controller/InventoryItemController.php` (if a
  scoped-add controller is needed — check whether the standard
  `_entity_form: 'inventory_item.add'` route suffices first, since
  `InventoryItem` doesn't have `HiveController::addForm()`'s
  pre-population need beyond setting `apiary`), `src/InventoryItemListBuilder.php`,
  `src/InventoryPurchaseListBuilder.php`,
  `src/InventoryItemAccessControlHandler.php`,
  `src/InventoryPurchaseAccessControlHandler.php`,
  `src/Form/InventoryItemForm.php`, `src/Form/InventoryPurchaseForm.php`,
  `hivelog.routing.yml`, `hivelog.links.menu.yml` (children of
  `hivelog.admin`, consistent with `hivelog.hives`/`hivelog.queens`,
  understanding that reachability shouldn't depend on this menu placement
  alone — see the self-built-heading requirement above).
- Follow `src/QueenListBuilder.php` (post-fix, see
  `fix(list-builders): stringify entity links...`) as the template for
  the self-built-heading + `hivelog:entity-table` pattern — call
  `->toString()` on any `Link` explicitly rather than relying on an
  `instanceof \Stringable` check, which doesn't work for `Link` objects
  in this Drupal version.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0019-authorisation-model]], [[0020-access-parity-custom-routes]]
- Commits::
