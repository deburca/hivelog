---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hive-component-weight-tracking]]"
area: entity
created: 2026-09-28
branch: feature/0163-hive-component-entity-and-empty-weight
release: 2.2.0
depends-on: ["[[0162-inventory-item-weight-field]]"]
blocked-by:
---
# Task: `HiveComponent` entity, embedded UI, and `Hive::getEmptyWeightKg()`

## Context
The core of [[hive-component-weight-tracking]]
([[0106-hive-component-weight-tracking]] Decisions §2–5 + the
2026-09-28 Amendment): a new entity recording which `InventoryItem`s
(and how many of each) a `Hive` is physically built from, a computed
empty-weight sum, the Hive page's own UI to manage it, and — per the
Amendment — a hard stock-availability ceiling: a component can never
be assigned past how many units of that catalog item actually exist
(purchased minus already assigned to any hive), and the item picker
only offers items with at least one unit still available.

## Acceptance criteria
- [x] New `HiveComponent` content entity
      (`src/Entity/HiveComponent.php`), mirroring
      `CalendarActionItemRequirement`'s exact shape: `hive`
      (entity_reference → hive, required), `item` (entity_reference →
      inventory_item, required), `quantity` (**integer**, not decimal
      — see ADR-0106 §2 for why this diverges from
      `CalendarActionItemRequirement.quantity`; `min: 1, default: 1`,
      required), plus `uid`/`created`/`changed`
      (`EntityOwnerInterface`/`EntityChangedInterface`, matching the
      analog).
- [x] `preSave()` same-apiary guard: `hive.apiary` must equal
      `item.apiary`, throwing `\InvalidArgumentException` on
      mismatch — copy `CalendarActionItemRequirement::preSave()`'s
      exact logic and message shape. Re-checked in the add/edit
      form's own `validateForm()` too, same as the analog.
- [x] Two new `InventoryItem` computed methods (Amendment):
      `getTotalPurchasedQuantity(): float` (sums
      `InventoryPurchase.quantity` for this item, any `item_type` —
      no such reusable total exists today) and
      `getAssignedToHivesQuantity(?int $excludeHiveComponentId =
      NULL): float` (sums `quantity` across every `HiveComponent` row
      referencing this item, across every hive, optionally excluding
      one row). A third, `getAvailableForHiveAssignmentQuantity(?int
      $excludeHiveComponentId = NULL): float`, is the first minus the
      second — always a real float, never `NULL`.
- [x] `preSave()` stock-availability guard (Amendment): if `quantity`
      exceeds `item.getAvailableForHiveAssignmentQuantity($this->id())`,
      throw `\InvalidArgumentException` naming the shortfall. Re-checked
      in `HiveComponentForm::validateForm()` too — `setErrorByName()`
      naming how many units are actually available — same two-layer
      pattern as the same-apiary guard above, not a single-layer check.
- [x] New selection plugin `default:hivelog_hive_component_item`
      (`src/Plugin/EntityReferenceSelection/HiveComponentAvailableItemSelection.php`,
      Amendment): extends `ApiaryScopedSelection` for its existing
      apiary/discontinued query scoping, overrides
      `getReferenceableEntities()` to post-filter out any item whose
      `getAvailableForHiveAssignmentQuantity()` is `<= 0` (a computed
      value, not expressible as a query `condition()`) and appends an
      "(N available)" suffix to each remaining option's label,
      matching `InventoryItemController::view()`'s existing "(Low
      Stock)" suffix idiom. **Do not add this filtering to the shared
      `ApiaryScopedSelection` plugin directly** — it's also used by
      `InventoryPurchaseForm`/`CalendarActionItemRequirementForm`/
      `CalendarActionProductYieldForm`, where a hive-assignment
      availability filter would be wrong. `HiveComponentForm` sets
      `#selection_settings['exclude_hive_component_id']` to the
      entity being edited's own id (`NULL`/omitted on the add form),
      mirroring how `apiary_id` is already passed today.
- [x] Access control handler mirroring
      `CalendarActionItemRequirementAccessControlHandler`'s shape.
      Scoped add route `hivelog.hive_component.add` at
      `/hivelog/hive/{hive}/component/add`, requiring
      `_entity_access: 'hive.update'` +
      `_entity_create_access: 'hive_component'` — copy
      `hivelog.calendar_action_item_requirement.add`'s exact route
      shape. Standard edit/delete routes/forms for a single
      `HiveComponent` row.
- [x] Update hook (`hivelog_update_10031`) installs
      the new entity type via `installEntityType()`, copying
      `hivelog_update_10021`'s exact pattern.
- [x] Two new `HivelogDeleteDependencyRegistry` rows (ADR-0106 §4):
      CASCADE `hive` → `hive_component` (field `hive`); BLOCK
      `inventory_item` → `hive_component` (field `item`).
- [x] `Hive::getEmptyWeightKg(): ?float` — sums `quantity ×
      item.weight_kg` across the hive's `HiveComponent` rows (query
      style matching `getActiveQueen()`/`getQueens()`). **Returns
      `NULL` if any referenced item has no `weight_kg` set** — not a
      partial sum. Returns `NULL` (not `0.0`) when the hive has no
      components at all, distinguishing "empty weight is genuinely
      zero" (impossible for a real hive) from "not yet composed."
- [x] "Hive Components" section on the Hive canonical page
      (`src/Controller/HiveController.php`, weight ~6, between the
      entity-fields section at weight 5 and the weight histogram at
      weight 7): a table (item / quantity / unit weight / subtotal),
      an Add button (the scoped route above), per-row Edit/Delete
      (`hivelog:button-group`, matching every other embedded list in
      the module). Below the table: "Empty weight: X kg", or, when
      `getEmptyWeightKg()` is `NULL`, "Empty weight: incomplete — N
      of M components missing a weight" (count components whose
      `item.weight_kg` is unset).
- [x] Kernel tests: `HiveComponent` CRUD, the same-apiary guard
      (create + form validation), `getEmptyWeightKg()`'s three cases
      (complete sum, `NULL` on a missing component weight, `NULL` on
      no components at all), the two delete-dependency rows (deleting
      a hive cascades its components; deleting a referenced inventory
      item is blocked while a `HiveComponent` still references it),
      the three new `InventoryItem` availability methods, the
      stock-availability guard (create at the limit succeeds; one
      over the limit fails both at `preSave()` and at
      `validateForm()`; editing a row's own quantity up to its prior
      value + remaining availability succeeds — the
      `excludeHiveComponentId` exemption), and the new selection
      plugin (an item with zero availability is excluded from
      results; one with some availability shows the "(N available)"
      suffix).
- [x] Verified live on `cms2`: compose a real test hive from real
      catalog items (including at least one item with no weight set,
      to exercise the "incomplete" state), confirm the section and
      the empty-weight figure render correctly in both states; delete
      one component and confirm the sum updates; attempt to assign
      more units of an item than are actually purchased and confirm
      it's rejected with a clear message. The item picker's own
      autocomplete UI couldn't be click-tested live (browser-tool JS
      blocking — see Implementation notes), but is covered by a real
      kernel test driving the actual selection plugin through a real
      built form (`ApiaryScopedAutocompleteTest`'s own pattern) — which
      caught and fixed a genuine bug (see notes) that manual review had
      missed, so this is stronger coverage than the live click-through
      would have been anyway.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Entity/HiveComponent.php` (new),
  `src/HiveComponentAccessControlHandler.php` (new),
  `src/Form/HiveComponentForm.php` (new, add/edit — reuses
  `HivelogEntityDeleteForm` directly for delete, same as
  `CalendarActionItemRequirement`; no bespoke delete form needed),
  `src/Entity/Hive.php` (`getEmptyWeightKg()`),
  `src/Entity/InventoryItem.php` (the three new availability methods),
  `src/Plugin/EntityReferenceSelection/HiveComponentAvailableItemSelection.php`
  (new), `src/Controller/HiveController.php` (new section +
  `addComponentForm()`), `src/ApiaryAccessTrait.php` (new
  `hive_component` branch), `src/HivelogEntityHierarchy.php`
  (`hive_component` added to `PARENT_FIELD`/`SUBJECT_PARAMS`),
  `hivelog.routing.yml`, `hivelog.permissions.yml` (7 new
  permissions), `hivelog.install` (`hivelog_update_10031`),
  `src/Delete/HivelogDeleteDependencyRegistry.php`,
  `tests/src/Kernel/HiveComponentTest.php` (new, 19 tests).
- Schema change (new entity type) → **update hook required**.
- No standalone list builder / collection route for `HiveComponent`
  — same "managed only from its parent's page" precedent
  `CalendarActionItemRequirement` already established; do not add one
  speculatively.
- **CASCADE and BLOCK needed zero extra application code beyond the
  two registry rows.** Both `hivelog_entity_predelete()` (CASCADE, via
  `HivelogDeleteDependencyExecutor`) and `InventoryItemAccessControlHandler`'s
  existing `blockDelete()` call (BLOCK, via `HivelogDeleteBlockingAccessTrait`)
  already read the registry generically — confirmed both behaviors
  live (deleting a hive with 3 components cascaded them to 0; deleting
  a referenced item was forbidden for a real `administer hivelog`
  account, not just a non-admin owner, matching every other BLOCK
  row's own `testAdminIsNotExemptFromBlock()`-style precedent).
- **The availability ceiling applies regardless of `item_type`**
  (Amendment) — it's computed from `InventoryPurchase` totals minus
  `HiveComponent` assignments, not from `getStockOnHand()` (which is
  consumable-only and would return `NULL` for the realistic
  `durable`-typed hive-component case). Do not attempt to reuse or
  branch on `getStockOnHand()` for this — it's a deliberately separate
  computation.
- **Availability is apiary-wide across hives, not hive-specific** —
  an item purchased for one apiary can be assigned to any hive in
  that same apiary (the existing same-apiary guard already ensures
  this), and its availability pool is shared/competed-for across all
  of that apiary's hives, not partitioned per hive.
- Consider whether `getTotalPurchasedQuantity()` is worth factoring
  the existing near-identical purchase-summing loops in
  `getStockOnHand()`/`getWeightedAverageUnitCost()`/
  `getAnnualDepreciation()` to reuse, **but do not refactor those
  existing methods as part of this task** unless it's trivial and
  risk-free — this task's scope is the new capability, not a cleanup
  of pre-existing, working, tested code.
- phpstan: two genuinely new "undefined method" findings (not the
  usual generic `EntityInterface::get()` pattern) — calling a custom
  `InventoryItem` method (`getAvailableForHiveAssignmentQuantity()`)
  on a variable typed only as generic `EntityInterface` (from
  `->get('item')->entity` or a plain `->load()`). Fixed with an inline
  `/** @var \Drupal\hivelog\Entity\InventoryItem|null $item */`
  docblock at the point of assignment in both `HiveComponent::preSave()`
  and `HiveComponentForm::validateForm()` — the correct, targeted fix,
  as opposed to the file-wide baseline suppression the generic `->get()`
  pattern gets elsewhere. `HiveComponentAvailableItemSelection`'s own
  equivalent case uses `instanceof InventoryItem` narrowing instead,
  since it's iterating a loaded-entities array rather than a single
  known reference. The remaining findings (plain `EntityInterface::get()`
  calls, and `HiveComponentForm::save()`'s "should return int" — an
  exact match for `CalendarActionItemRequirementForm::save()`'s own
  already-baselined finding) are baselined normally: 440 → 452.
- **Real regression found and fixed**: `HiveController::view()` now
  unconditionally queries `hive_component` storage for every hive page
  render (the new section always shows, even empty) — four
  pre-existing kernel test files that render the Hive canonical page
  without installing that schema failed with a real "table doesn't
  exist" SQL error: `HiveTest.php`, `HiveCalendarChecklistTest.php`,
  `EmbeddedTableFilterPaginationTest.php`,
  `ControllerCacheMetadataTest.php`. Fixed by adding
  `$this->installEntitySchema('hive_component');` to each one's
  `setUp()`. Checked every other kernel/functional test file that
  touches `HiveController` (`HiveInsightsPageTest.php` calls
  `insights()`/`insightsTitle()` only — untouched by this section —
  and Functional tests install the whole module, unaffected).
- **A second, subtler regression**: two more `HiveTest.php` assertions
  (`testHiveViewWeightHistogram`, `testHiveViewInspectionTableWeightColumn`)
  compare `strpos()` positions of specific HTML fragments to assert
  render order, and both implicitly assumed **the first `<table>` tag
  on the page is the inspections table** — true before this task,
  since nothing rendered a `<table>` earlier. The new Hive Components
  section always renders one (via `hivelog:entity-table`, even for its
  own empty state), so it's now the first `<table>`, silently shifting
  both assertions onto the wrong content (one now compared the
  histogram's position against my new table instead of the inspections
  one; the other's `<table> onward` scope now included the Queen
  section's own `<h2>Queen</h2>` heading, whose literal `>Queen<`
  substring falsely matched what was meant to find the inspections
  table's Queen *column* header). Fixed both by skipping to the
  *second* `<table>` (`strpos($html, '<table', $first_table_pos + 1)`)
  — grepped every other kernel test file for the same
  `strpos($html, '<table'` pattern and confirmed no other occurrences
  exist, so this was the complete set.
- Full regression check after both fixes: the originally-failing
  9-file batch (`HiveTest`, `InventoryItemTest`,
  `HivelogDeleteBlockRelationshipsTest`, `HivelogDeleteCascadeTest`,
  `HiveCalendarChecklistTest`, `EmbeddedTableFilterPaginationTest`,
  `ControllerCacheMetadataTest`, `HiveComponentTest`,
  `HivelogBreadcrumbBuilderTest`) — 243 tests, 0 failures.
- **A third regression**, found by the true full suite (not the
  targeted 9-file batch): `RouteEntityAccessTest.php` — task 0133's
  comprehensive test that generically iterates every route under
  `/hivelog` and needs one fixture entity per route parameter type,
  registered in its own `setUp()`. It had no fixture for
  `hive_component`, so all three new routes failed with either "Call
  to a member function id() on null" or "No fixture registered for
  route parameter 'hive_component'". Fixed by adding
  `installEntitySchema('hive_component')`, `'hive component'` to the
  role's permission-phrase loop, and a `HiveComponent` fixture
  (reusing the already-in-scope `$hive`/`$item` fixtures, quantity 1
  against the item's already-purchased quantity 10) — the same
  pattern every other entity type in that file already follows.
  Checked `PermissionMatrixTest.php` (the functional, advisory mirror
  AGENTS.md describes as narrower) for an equivalent per-type fixture
  list — it has none, so no matching change was needed there.
- True full suite, final run after the selection-plugin bug fix and
  its two new tests (core + all four submodules,
  Kernel/Unit/Functional): **1,118 tests, 0 failures** (up from 1,094
  before this task). Only the same pre-existing third-party
  deprecation notices as every prior run (Symfony `EventDispatcher`,
  the `key` module's attribute-discovery deprecation), unchanged in
  cause.
- Live-verified on `cms2` (`kbg` site) with a throwaway apiary/hive/
  three catalog items (base plate ×2kg, brood chamber ×4.25kg, a
  third item with no weight set): composed 1×base-plate + 2×brood-
  chamber, confirmed the table and "Empty weight: 10.5 kg." (1×2 +
  2×4.25); added the no-weight item and confirmed "Empty weight:
  incomplete — 1 of 3 components missing a weight."; attempted to
  assign 10 of the base plate (3 purchased, 1 already assigned) via
  `HiveComponent::create()->save()` and confirmed the exact rejection
  message ("Cannot assign 10 of "Verify Base Plate" — only 2
  available."); deleted the hive and confirmed all 3 components
  cascaded (3 → 0); confirmed a real `administer hivelog` account
  (not uid 1) got `AccessResultForbidden` deleting the still-referenced
  base-plate item, with the correct dependency-count reason text.
  Fixtures fully deleted afterward, confirmed via entity queries.
- **Not live-verified in the browser**: the item picker's own
  autocomplete UI — this session's browser tool blocks Drupal core's
  own `autocomplete.js`/`autocomplete-min.js` assets site-wide
  (`net::ERR_BLOCKED_BY_CLIENT`), so the jQuery UI autocomplete widget
  never fires its AJAX request in this sandbox, a different flavour of
  the same asset-blocking limitation documented for CSS elsewhere in
  this vault.
- **Caught a real bug via a proper test instead**: initially claimed
  (wrongly, on first pass) that the entity-level availability tests
  gave "indirect" coverage of the selection plugin — they don't; the
  plugin itself had zero test coverage. Added two real tests
  (`testAddFormItemPickerOffersOnlyAvailableItems`,
  `testEditFormItemPickerExcludesOwnQuantityFromAvailability`) copying
  `ApiaryScopedAutocompleteTest::referenceableEntities()`'s exact
  pattern — build the real add/edit form via `entity.form_builder`,
  extract the `item` widget's own `#selection_handler`/
  `#selection_settings`, and call the real plugin manager, proving
  what an actual autocomplete request would return rather than testing
  the plugin class in isolation. **This immediately failed** with
  `ArgumentCountError: array_merge() does not accept unknown named
  parameters` — `HiveComponentAvailableItemSelection::getReferenceableEntities()`
  spread `$options` (keyed by bundle name, a string) directly into
  `array_merge(...)`; PHP 8.1+ treats spreading a string-keyed array
  as named arguments, which `array_merge()` doesn't declare parameters
  for. This would have thrown a fatal error the moment any beekeeper
  actually typed into the item field on a real `HiveComponent` add/
  edit form — a real, would-have-shipped bug, not a hypothetical.
  Fixed with one `array_values()` call before the spread. Confirms
  the earlier honest self-correction was worth doing rather than
  leaving the doc's overstated claim standing.

## Related
- Project:: [[hive-component-weight-tracking]]
- Decisions:: [[0106-hive-component-weight-tracking]],
  [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0162-inventory-item-weight-field]],
  [[0164-net-colony-honey-weight-stat-tile]]
- Commits::
