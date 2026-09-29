---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hive-component-weight-tracking]]"
area: entity
created: 2026-09-28
branch: feature/0162-inventory-item-weight-field
release: 2.2.0
depends-on:
blocked-by:
---
# Task: `weight_kg` field on Inventory Item

## Context
First step of [[hive-component-weight-tracking]]
([[0106-hive-component-weight-tracking]] Decision §1): an optional
weight value on `InventoryItem`, so a catalog row like "10×12 Brood
Chamber (Wood, empty)" can carry its own weight — no restriction to a
particular `category`/`item_type`, any catalog item may set it.

## Acceptance criteria
- [x] `InventoryItem::baseFieldDefinitions()` gains `weight_kg`:
      `decimal`, `precision: 10, scale: 3`, `min: 0`, optional (not
      required — most catalog items won't set it), form/view display
      options matching `low_stock_threshold`'s exact existing shape
      (`src/Entity/InventoryItem.php`).
- [x] Update hook (`hivelog_update_10030`) installs the new field
      storage via `installFieldStorageDefinition()`, copying
      `hivelog_update_10026`'s exact shape (the `low_stock_threshold`
      precedent) — reads the field definition from
      `InventoryItem::baseFieldDefinitions()`, guarded with
      `isset($field_definitions['weight_kg'])`.
- [x] `InventoryItemListBuilder`'s row/header gains a Weight column
      (blank when unset, matching how other optional numeric fields
      already render blank).
- [x] `HivelogInventoryItemFilterForm` — **no filter field added for
      weight** (a numeric-range filter isn't part of this task's
      scope; the existing Category/Type/Status/Name filters are
      unaffected by this new field) — confirmed unchanged.
- [x] Kernel tests: setting/reading `weight_kg` on an `InventoryItem`,
      it being genuinely optional (zero validation violations unset),
      the list column, and the canonical-page display (both set and
      unset — see Implementation notes for a real gap this last one
      caught). No dedicated update-hook kernel test exists for this
      field-addition shape anywhere in the codebase (confirmed by
      checking — `low_stock_threshold`'s own hook has none either);
      the hook itself was instead verified directly against real
      `cms2` data via `drush updb`, matching how every other
      `hivelog_update_N` in this module is actually validated.
- [x] Verified live on `cms2`: set a weight on a real catalog item,
      confirm it displays on the item's own page and in the Inventory
      Items list.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Entity/InventoryItem.php`, `src/InventoryItemListBuilder.php`,
  `src/Controller/InventoryItemController.php`,
  `tests/src/Kernel/InventoryItemTest.php`, `hivelog.install`
  (`hivelog_update_10030`).
- Schema change → **update hook required** — see AGENTS.md "Entity
  schema changes". Copied `hivelog_update_10026`'s shape exactly (a
  new optional field on an existing entity type, no data migration
  since it's new).
- **Real gap caught during live verification**: the new field
  rendered correctly in the list column but was completely absent
  from the item's own canonical page (`InventoryItemController::view()`
  builds two hand-picked field lists, `overview` and `type`, via
  `buildSection()` — new fields don't appear automatically just by
  existing in `baseFieldDefinitions()`, unlike a generic Drupal entity
  view). Fixed by adding `weight_kg` to the `overview` section's field
  list, plus a `formatFieldValue()` case (trimmed decimal + "kg"
  suffix, matching `low_stock_threshold`'s own formatting exactly) —
  `buildFieldValue()`'s shared "empty → em dash" rule already handles
  the unset case for free, no extra code needed for that.
- phpstan: `InventoryItemListBuilder.php`'s new `$entity->get('weight_kg')`
  call pushed an already-baselined `EntityInterface::get()` false
  positive (the well-known no-mglaman-extension gap this module's
  `phpstan.neon` documents) from a `count: 6` to `count: 7` on an
  *existing* baseline entry — regenerated and diffed to confirm only
  that one count changed, not a new baseline entry.
- Regression check: full `InventoryItemTest` suite (29 tests, up from
  24) plus `InventoryProductFilterTest`/`InventoryPurchaseTest`/
  `ProductTest` (30 tests) — all green, no regressions from the new
  field or the controller section change.
- Update hook applied cleanly via `drush updb` against both `cms2`
  multisites running `hivelog` (`kbg` and `vdg`) — confirmed
  `drush updatedb-status` clean on both afterward.
- Live-verified on `cms2` (`kbg`): a throwaway durable item
  ("Verify Brood Chamber", 4.25 kg) showed "4.25 kg" in the Inventory
  Items list's new Weight column, on its own canonical page's
  Overview section, and pre-filled correctly ("4.250") on its edit
  form; an item with no weight set showed a blank list cell and an em
  dash on its own page. Fixture deleted afterward, confirmed gone via
  entity queries.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0106-hive-component-weight-tracking]]
- Tasks:: [[0163-hive-component-entity-and-empty-weight]]
- Commits::
