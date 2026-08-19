---
type: task
tags: [hivelog/task]
status: done
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
- [x] Standard entity CRUD routes for both entity types
      (`entity.inventory_item.*`, `entity.inventory_purchase.*`), plus
      apiary-scoped add routes (`hivelog.inventory_item.add`,
      `hivelog.inventory_purchase.add`) mirroring `hivelog.hive.add`.
      Also added the standalone `entity.inventory_item.add_form` /
      `entity.inventory_purchase.add_form` routes (no apiary pre-fill),
      matching the `entity.queen.add_form` precedent — used by each list
      builder's own "Add" heading button.
- [x] `InventoryItemAccessControlHandler` / `InventoryPurchaseAccessControlHandler`
      using `ApiaryAccessTrait::resolveApiary()` (extended with two new
      branches), matching `CalendarActionAccessControlHandler`. Item
      delete is owner-only (mirrors `CalendarAction` — foundational
      catalog structure); purchase delete is owner-or-creator (mirrors
      `HiveActionLog` — a per-transaction log).
- [x] `InventoryItemListBuilder` (self-built "Add Inventory Item" +
      "View Purchases" heading using the `hivelog:entity-table` SDC
      component) — columns: name, apiary, category, unit, type, stock on
      hand (consumables only), status, operations.
- [x] `InventoryPurchaseListBuilder`, same self-built-heading pattern
      ("Add Purchase" + "View Inventory Items") — columns: item, apiary,
      date, quantity, unit price, total cost, supplier, operations.
- [x] Stock-on-hand helper: `InventoryItem::getStockOnHand(): ?float`
      (returns `NULL` for durable items and unsaved items) — computed as
      `Σ InventoryPurchase.quantity − Σ InventoryUsage.quantity`,
      mirroring `Hive::getActiveQueen()`'s query-not-stored-state style.
      Guards on `inventory_usage` not existing yet via
      `$entityTypeManager->hasDefinition()` rather than assuming an
      empty result set — querying a storage for an undefined entity type
      would throw, not return zero rows. No rework needed once
      [[0031-inventory-usage-and-action-log-reporting-integration]]
      ships.
- [x] Apiary canonical page: an "Inventory" heading with "View Inventory
      Items" / "Add Inventory Item" buttons, matching the "Seasonal
      Calendar" heading's two-button layout exactly. No embedded table —
      the catalog/ledger pages have their own filtering and don't need
      duplicating here.
- [x] Kernel tests: `InventoryAccessTest` (apiary-scoped access parity
      for both entities, mirroring `ApiaryScopedAccessTest`'s style —
      owner/beekeeper/outsider × view/update/delete × public/private
      apiary), list builder rendering (`hivelog:entity-table` +
      heading buttons present, in both `InventoryItemTest` and
      `InventoryPurchaseTest`), stock-on-hand calculation (multiple
      purchases, durable-returns-null, unsaved-returns-null).
- [x] `ddev drush updb -y && ddev drush cr` — confirmed "No pending
      updates" (access-handler wiring and routing/permissions changes
      don't need an update hook, only a cache rebuild) — verified
      against `cms2`.
- [x] Full kernel + unit suite re-run against `cms2`: 340 tests, 4767
      assertions, 0 failures/errors (up from the 318/4550 baseline
      after task 0028).
- [x] End-to-end smoke test against `cms2` via `drush php:eval`: created
      a real `InventoryItem` + `InventoryPurchase`, confirmed
      `getStockOnHand()` returns the purchased quantity, the apiary
      page's Inventory heading and buttons render, the item collection
      renders via `hivelog:entity-table` and shows the computed stock
      column, and the item canonical page shows its "Stock on Hand"
      section — then cleaned up the test data.

## Implementation notes
- Key files: `src/Controller/InventoryItemController.php`,
  `src/Controller/InventoryPurchaseController.php` (both needed — each
  entity's apiary-scoped `addForm()` plus a sectioned canonical `view()`,
  following `CalendarActionController`'s exact
  buildActions/buildSection/buildRows/buildFieldValue shape, since every
  other entity in the module uses a custom controller rather than the
  default view builder), `src/InventoryItemListBuilder.php`,
  `src/InventoryPurchaseListBuilder.php`,
  `src/InventoryItemAccessControlHandler.php`,
  `src/InventoryPurchaseAccessControlHandler.php`, `src/ApiaryAccessTrait.php`
  (two new `resolveApiary()` branches), `src/Controller/ApiaryController.php`
  (Inventory heading), `hivelog.routing.yml`, `hivelog.links.menu.yml`,
  `css/hivelog.tables.css` (new detail-table classes — see below).
- Followed `src/QueenListBuilder.php` (post-fix, see the
  `fix(list-builders): stringify entity links...` commit from task 0028)
  as the template for the self-built-heading + `hivelog:entity-table`
  pattern — calls `->toString()` on every `Link` explicitly rather than
  relying on an `instanceof \Stringable` check, which doesn't work for
  `Link` objects in this Drupal version.
- Discovered while building the canonical view pages: `CalendarActionController`'s
  `.hivelog-calendar-action-section`/`.hivelog-calendar-action-table`
  classes have **no CSS rules anywhere** — a pre-existing gap predating
  this session, left alone as out of scope. Added
  `.hivelog-inventory-item-table`/`.hivelog-inventory-purchase-table` to
  the actually-styled selector groups in `css/hivelog.tables.css`
  (alongside `.hivelog-inspection-table`/`.hivelog-queen-table`/
  `.hivelog-queen-observation-table`) instead of copying the unstyled
  calendar-action class names.
- Redirect targets for both `Form`/`DeleteForm` pairs go to the entity's
  own collection page (`entity.inventory_item.collection`/
  `entity.inventory_purchase.collection`), not the parent apiary — unlike
  `Hive`/`CalendarAction`, the apiary canonical page doesn't embed either
  table, so redirecting there wouldn't show the just-saved/deleted record.

## Related
- Project:: [[inventory-tracking-and-depreciation]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]], [[0019-authorisation-model]], [[0020-access-parity-custom-routes]]
- Commits:: f03831d
