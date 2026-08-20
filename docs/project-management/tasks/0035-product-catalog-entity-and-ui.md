---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[honey-wax-propolis-yield-and-potential-income]]"
area: entity
created: 2026-08-20
branch: feature/0035-product-catalog-entity-and-ui
release:
depends-on:
blocked-by:
---
# Task: `Product` entity and catalog UI

## Context
Foundational for [[honey-wax-propolis-yield-and-potential-income]] — the
sellable-output catalog. Nothing else in this project can be built until
it exists, per
[[0034-honey-wax-propolis-yield-and-potential-income]]. Combines what
[[inventory-tracking-and-depreciation]] split across two tasks (0028
entity, 0029 UI) into one, since `Product` has no separate ledger entity
the way `InventoryItem` has `InventoryPurchase` — `expected_unit_price`
lives directly on `Product` (see the ADR's confirmed "aggregate
assumption, not a ledger" decision).

## Acceptance criteria
- [x] `src/Entity/Product.php` — `ContentEntityBase` with
      `#[ContentEntityType]`, base table `hivelog_product`, entity keys
      (`id`, `label` → `name`, `uuid`, `owner` → `uid`).
- [x] Fields: `apiary` (required entity_reference → `apiary`), `name`
      (required string), `unit` (required string, free text — same
      no-conversion trade-off as `InventoryItem.unit`),
      `expected_unit_price` (required decimal — the current best-guess
      sale price per unit, directly editable, never a computed/derived
      value), `status` (list_string, default `active`: `active` |
      `discontinued`), plus `uid`/`created`/`changed`.
- [x] `ProductAccessControlHandler` using `ApiaryAccessTrait::resolveApiary()`
      (new branch for `product`), matching `InventoryItemAccessControlHandler`.
      Delete is owner-only (foundational catalog structure, mirroring
      `InventoryItem`/`CalendarAction` — not a per-transaction log).
- [x] Standard entity CRUD routes (`entity.product.*`) plus an
      apiary-scoped add route (`hivelog.product.add`), mirroring
      `hivelog.inventory_item.add`.
- [x] `ProductListBuilder` — self-built "Add Product" heading using the
      `hivelog:entity-table` SDC component, matching
      `InventoryItemListBuilder`'s pattern exactly. Columns: name,
      apiary, unit, expected unit price, status, operations.
- [x] `ProductController` — sectioned canonical `view()` page following
      `InventoryItemController`'s buildActions/buildSection/buildRows/
      buildFieldValue shape.
- [x] Apiary canonical page: a "Products" heading + embedded,
      apiary-scoped, paginated table — following the *current* state of
      the apiary page's Inventory section (embedded table, not a
      link-out), not the link-out-only shape [[0029-inventory-catalog-and-purchase-ledger-ui]]
      originally shipped and was later changed away from. Match
      `ApiaryController`'s Hives/Inventory table pattern: own pager
      element (distinct from `HIVES_PAGER_ELEMENT`/
      `INVENTORY_ITEMS_PAGER_ELEMENT`). Only an "Add Product" button in
      the heading — no separate "View" link-out, matching the Inventory
      heading's current shape after its own redundant "View Inventory
      Items" button was removed (the embedded table below already is
      the view).
- [x] `hivelog.permissions.yml`: `view own product`, `view any product`,
      `add product`, `edit own product`, `edit any product`, `delete own
      product`, `delete any product`.
- [x] `hivelog_update_NNNN` in `hivelog.install` installs the new entity
      type; add `product` to `hivelog_uninstall()`'s cleanup list before
      `apiary`.
- [x] Kernel tests (`ProductTest`, `ProductAccessTest`): CRUD, field
      defaults/required-field validation, apiary-scoped access parity
      (owner/beekeeper/outsider × view/update/delete × public/private
      apiary, mirroring `InventoryAccessTest`'s style), list builder
      rendering, the embedded apiary-page table rendering with real
      products present.
- [x] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/Product.php`, `src/Form/ProductForm.php`,
  `src/Form/ProductDeleteForm.php`, `src/ProductListBuilder.php`,
  `src/ProductAccessControlHandler.php`, `src/Controller/ProductController.php`,
  `src/ApiaryAccessTrait.php` (new `resolveApiary()` branch),
  `src/Controller/ApiaryController.php` (new "Products" heading + table),
  `hivelog.routing.yml`, `hivelog.links.menu.yml`, `hivelog.permissions.yml`,
  `hivelog.install`.
- Embed the table directly on the apiary page from the start, rather than
  shipping a link-out-only version and having to redo it — the inventory
  project's original link-out design ([[0029-inventory-catalog-and-purchase-ledger-ui]])
  was reworked after the fact once real usage showed the embedded table
  was expected; no reason to repeat that detour here now the precedent is
  established.
- `expected_unit_price` is a plain editable field with no `preSave()`
  auto-derivation (unlike `InventoryPurchase.total_cost`) — it is the
  input, not a computed output.
- `ApiaryController::view()` gained a third embedded table (Hives,
  Inventory, now Products), each needing its own pager element
  (`PRODUCTS_PAGER_ELEMENT = 2`) since all three tables live on the same
  page.
- `ProductForm`/`ProductDeleteForm` redirect to the parent apiary
  (embedding parent), matching `InventoryItemForm`'s current redirect
  target after its own post-0029 rework — not the original
  redirect-to-collection shape 0029 shipped.
- Discovered while wiring the embedded table's own kernel test: a fresh
  `KernelTestBase` test with no explicit `User::create()` call keeps the
  acting user as anonymous (uid 0, no permissions), while every other
  test in this session that renders `ApiaryController::view()` with real
  access-controlled content creates one plain `User::create()` (no
  explicit role) that becomes uid 1 — Drupal's hardcoded
  all-permissions-bypass uid — relying on that rather than granting
  explicit permissions. `ProductTest::testApiaryPageEmbedsProductsTable`
  needed the same setup; the file's other tests (CRUD, validation) don't
  go through access-controlled rendering and were unaffected.

## Verification
- Full kernel+unit suite against `cms2` with `SIMPLETEST_DB=mysql`
  (matching CI's backend): 401 tests, 0 failures/errors (up from 385
  before this task).
- `ddev drush updb -y`: `hivelog_update_10023` applied cleanly, installing
  the `product` entity type.
- End-to-end smoke test via `drush php:eval`: created a real `Apiary` +
  `Product` ("Honey", 12.50/kg), confirmed it renders on both the apiary
  page's embedded Products table and the product's own canonical page —
  then cleaned up.

## Related
- Project:: [[honey-wax-propolis-yield-and-potential-income]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]], [[0027-inventory-tracking-and-depreciation]]
- Commits:: 9aed8a1 (entity, access control, forms, list builder,
  controller, embedded apiary-page table, permissions, install hook,
  tests)
