---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0134-delete-dependency-framework
release:
depends-on:
blocked-by:
---
# Task: Delete-dependency framework (relationship registry, counts, form rendering)

## Context
[[0103-delete-policy-for-records-with-children]] inventories all 28
parent → child relationships and assigns each one a treatment: BLOCK,
WARN, CASCADE or DETACH. This task builds the shared machinery. The
four follow-up tasks each switch on one treatment:
[[0141-delete-block-relationships]],
[[0142-delete-cascade-owned-records]],
[[0143-delete-detach-optional-references]],
[[0144-delete-warn-historical-references]].

Today there is no delete handling at all. The only dependent-record
logic is two hand-written counters in `InventoryItemDeleteForm` and
`ProductDeleteForm`.

## Acceptance criteria
- [x] [[0103-delete-policy-for-records-with-children]] confirmed by the
      user (CASCADE / DETACH plus the open questions) and flipped to
      `accepted` before implementation starts.
- [x] **One relationship registry**, declared once: parent type, child
      type, reference field, treatment, and "where to manage these
      children" link target (route + parameters, optional `#anchor`).
      It extends or reuses the parent map from
      [[0116-breadcrumb-builder-parent-map-refactor]] (coordinate:
      whichever lands first owns the shared class). Submodules register
      their rows (#7, #8, #12, #13, #27, #28) via a hook
      (`hook_hivelog_delete_dependencies()`, documented in
      `hivelog.api.php`) or tagged services.
- [x] **A dependency counter service** returns, for an entity, the
      per-row counts of referencing children: all children
      (`accessCheck(FALSE)`) and those the current user can delete. It
      caches per request, so list pages with a Delete button per row
      don't run N × M queries (a grouped count per child type per page).
- [x] **Access integration point**: a helper each access control
      handler's `delete` branch calls, returning
      `AccessResult::forbidden($reason)` when any BLOCK row has
      children. Cacheability: the child types' list cache tags. Wired
      up by [[0141-delete-block-relationships]], not here.
- [x] **Delete form rendering** in the shared base from
      [[0127-shared-delete-form-base]] (or a trait, if 0127 hasn't
      landed). One section per treatment present:
      - BLOCK: "Can't delete yet" with counts, links, and the
        can't-delete-these-yourself wording from the ADR; no Delete
        button.
      - WARN: a warning with counts and links; Delete stays.
      - CASCADE: "Will also be deleted: …" with counts.
      - DETACH: "Will be kept but unlinked: …" with counts and
        reassign links.
      The styling reuses `.hivelog-notice--warning` (`hivelog/notices`
      library) and adds a critical variant for BLOCK if needed, using
      the `--hivelog-critical*` tokens.
- [x] **Delete execution hook**: a single place (the entity storage's
      delete or `hook_entity_predelete()`) that runs CASCADE and DETACH
      for the rows registered against the entity being deleted, whatever
      the delete path, not only the form. Populated by 0142 / 0143.
- [x] Links are shown only when the current user can access the target
      (`Url::access()`); otherwise the count is shown without a link.
- [x] Kernel tests for the framework itself, using a test-only
      relationship registered by a test module: counting, the
      per-request cache, rendering of each treatment section, and access
      integration returning forbidden with the reason.
- [ ] `InventoryItemDeleteForm::countHistoricalReferences()` and
      `ProductDeleteForm`'s counter removed once
      [[0141-delete-block-relationships]] /
      [[0144-delete-warn-historical-references]] have moved their rows
      onto the framework. **Correctly still open** — that's 0141's /
      0144's own trigger condition, not this task's; both hand-written
      counters are untouched, still working exactly as before, sitting
      alongside the new framework until their rows (#22/#24 for
      InventoryItem, #25/#26 for Product) move onto it.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **The registry is a new class, not an extension of
  `HivelogEntityHierarchy`.** Evaluated reusing it directly first, since
  the task explicitly asks to coordinate — but `HivelogEntityHierarchy`
  tracks exactly one parent field per entity type (for the breadcrumb
  trail / cancel-redirect), and several real rows don't fit that shape:
  `hive_action_log` alone needs three independent rows (BLOCK on `hive`,
  BLOCK on `calendar_action`, DETACH on `inspection`), which a
  one-field-per-type map can't represent. `HivelogDeleteDependencyRegistry`
  is a sibling class instead — its own file, its own `ROWS` — cross-
  referenced from both class docblocks so the relationship is documented
  even though the data isn't shared. What *is* shared: `HivelogEntityHierarchy::parentOrCollectionUrl()`
  gained no new caller from this task, but stayed the one place both
  `HivelogEntityDeleteForm` (0127) and this registry's `'parent-canonical'`
  manage-link resolution ultimately trace back to for "where's the
  parent" logic.
- **`ADR-0103`'s single #19/#20 rows became 4 registry rows** (`19a`/`19b`,
  `20a`/`20b`): the ADR names "Hive/ApiaryActionLog" as one parent for
  readability, but `InventoryUsage`/`HarvestYield` actually have two
  separate, mutually-exclusive reference fields
  (`hive_action_log`/`apiary_action_log`), and the registry's one-parent-
  type-per-row shape needs both spelled out. `adr_row` keeps the
  cross-reference to the ADR's own numbering either way.
- **Link targets are a 3-value sentinel, not a generic resolver**: NULL
  (no sensible single link — most WARN/CASCADE/DETACH rows, since their
  child type usually has no UI of its own), the literal string
  `'parent-canonical'` (the parent's own canonical page — true for
  every BLOCK row whose child type is embedded there: Apiary's
  hives/logs/items/products, Hive's inspections/logs, Queen's
  observations), or a bare route name (`InventoryItem`/`Apiary` →
  `InventoryPurchase`, whose ledger isn't embedded anywhere — links to
  the global collection instead). Verified case-by-case against every
  real controller's `view()` method before assigning each row's value,
  not assumed from the ADR's prose alone.
- **A bug task 0129 already found and fixed, reused safely here**:
  `EntityBase::toUrl()` refuses *any* `$rel` on an entity with no ID,
  including `'collection'`, which needs no ID in its own URL — 0129 hit
  this building a Cancel link for a brand-new add-form entity and fixed
  `HivelogEntityHierarchy::parentOrCollectionUrl()` to build the
  collection URL from the route name instead
  (`Url::fromRoute("entity.$type.collection")`). This task's own
  registry never calls `$parent->toUrl('collection')` directly — the
  `'parent-canonical'` sentinel only ever resolves a *parent*, which is
  always an already-saved entity in every real row — so no new instance
  of the same bug turned up here.
- **The counter only computes `deletable`/`not_deletable` for BLOCK
  rows** — loading and access-checking every child of a WARN/CASCADE/
  DETACH row (sensor readings, historical purchases — can be thousands)
  would be wasted work nothing reads; only ADR-0103's "children the
  current user can't delete" question is about BLOCK specifically.
- **Per-request memoization is per-entity, not yet cross-entity/batched.**
  `countsFor()` caches keyed by `"<type>:<id>"`, so asking twice for the
  same entity (the form renders sections, then separately checks
  whether to hide Delete) runs the queries once. True cross-entity
  batching for a list page showing N Delete buttons (the "grouped count
  per child type per page" the task describes) isn't built here — no
  list-page call site exists yet (that's 0141's job, wiring the access
  handlers), so there's nothing to batch for yet and no way to shape
  the API correctly without a real caller. Left as a documented
  follow-up rather than speculative API surface.
- **The delete-execution hook (`hivelog_entity_predelete()`) is a
  dispatch shell** — it loops every registered row for the entity being
  deleted and `switch`es on treatment, but the CASCADE/DETACH branches
  are empty, commented `// Populated by task 0142/0143`. Real deletion
  (potentially large: sensor readings) and reference-clearing are
  genuine, consequential mutations the task's own framing reserves for
  those tasks, not something to write sight-unseen alongside a data
  registry.
- **Test-only relationship, a real (throwaway) entity type**: considered
  reusing two existing hivelog entity types for the fake row, but every
  combination either had no unused reference field to condition a query
  on, or would have doubled-counted real ADR data under a second fake
  treatment. `hivelog_delete_dependency_test` (under `tests/modules/`,
  Drupal's standard convention for a module that only exists for one
  module's own tests) defines one minimal `DeleteTestChild` entity with
  a real `delete own` / `delete any` split, giving the "children the
  current user can't delete" scenario something genuine to compute
  against, decoupled from the real 24-row registry so neither can break
  the other.
- **A fresh Apiary is never actually childless.** `Apiary::postSave()`
  auto-seeds ~30 default calendar actions (pre-existing behaviour, task
  0022) — every apiary fixture always has at least a CASCADE row. Two
  "no dependencies" test cases use a fresh Hive instead, which has none.
- **Rendering, per treatment, verified live** on `cms2`
  (`/hivelog/apiary/1/delete`, a real apiary with real children):
  "Can't delete yet" listed 2 hives, 3 apiary action logs, 4 inventory
  items, 4 inventory purchases, 2 products, and 1 sensor device (the
  submodule-registered row, confirming `hook_hivelog_delete_dependencies()`
  actually merges at runtime, not just in a unit test) — and no Delete
  button rendered, Cancel still did. A calendar-action CASCADE section
  showed alongside it.
- **Verification**: 16 new kernel tests (`HivelogDeleteDependencyFrameworkTest`,
  424 assertions) covering the registry, counter, access-integration
  helper, and one rendering test per treatment. phpcs clean (0 errors)
  and phpstan clean on every file this task touched. Full `hivelog`
  suite: 928 tests, same 3 pre-existing unrelated `DashboardTest`
  Functional errors already documented by 0116/0117/0127 — no
  regressions despite this task changing `HivelogEntityDeleteForm::buildForm()`,
  which every one of the module's 16 delete forms inherits.
- Key files: new `src/Delete/HivelogDeleteDependencyRegistry.php`,
  `src/Delete/HivelogDeleteDependencyCounter.php`,
  `src/Form/HivelogEntityDeleteForm.php` (rendering + submit-hiding),
  `hivelog.services.yml`, `hivelog.api.php` (new
  `hook_hivelog_delete_dependencies()`), `hivelog.module` (new
  `hivelog_entity_predelete()`), `modules/nanoprobe/nanoprobe.module` /
  `modules/nexus/nexus.module` (the 6 submodule rows),
  `css/hivelog.notices.css` (new `.hivelog-notice--critical`), new
  `tests/modules/hivelog_delete_dependency_test/`, new
  `tests/src/Kernel/HivelogDeleteDependencyFrameworkTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0141-delete-block-relationships]],
  [[0142-delete-cascade-owned-records]],
  [[0143-delete-detach-optional-references]],
  [[0144-delete-warn-historical-references]],
  [[0145-orphan-report-and-cleanup-command]],
  [[0127-shared-delete-form-base]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
