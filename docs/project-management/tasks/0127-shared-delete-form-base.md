---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0127-shared-delete-form-base
release:
depends-on: ["[[0116-breadcrumb-builder-parent-map-refactor]]"]
blocked-by:
---
# Task: One delete-form base with a single cancel / redirect rule

## Context
From the page-structure review of 2026-09-23. There are 16 delete-form
classes (838 lines), 13 in `src/Form/` plus one each in `collective`,
`nexus` and `nanoprobe`. Each hand-writes `getQuestion()`,
`getCancelUrl()` and a `submitForm()` that deletes, sets a message and
redirects. Where they send the user is inconsistent:

| Form | Cancel → | After delete → |
|---|---|---|
| Apiary | apiary | apiary collection |
| Hive, HiveInspection, Queen, QueenObservation | the entity | parent (hive / apiary / queen) |
| CalendarAction, Hive- / ApiaryActionLog, Requirement, Yield | **parent** | parent |
| Product | **apiary** | apiary |
| InventoryItem, InventoryPurchase | **collection** | **collection** (though apiary-scoped, unlike Product) |
| SensorDevice, ApiClient, AiProviderConfig | core default | collection |

Fallbacks when the parent is missing also vary. HiveInspection with no
hive redirects to the *apiary* collection, for example.

## Acceptance criteria
- [x] One rule, written in the base class docblock:
      - **Cancel** returns to the entity's canonical page when it has
        one, otherwise to its parent's canonical page (requirement /
        yield → calendar action). Cancel should undo the click that
        brought the user here.
      - **After delete**: go to the parent's canonical page, else the
        entity type's collection, else the dashboard.
      - A `?destination=` query (already used by e.g. the Apiary page's
        calendar rows) wins over both, as core does.
- [x] `HivelogEntityDeleteForm extends ContentEntityDeleteForm`
      implements the rule, resolving the parent from the same parent map
      [[0116-breadcrumb-builder-parent-map-refactor]] introduces. Extract
      that map into a small shared class or service so the breadcrumb
      builder and this base read one definition.
- [x] `getQuestion()` / the status message come from the entity type's
      singular label ("Are you sure you want to delete hive %name?",
      "Hive %name has been deleted."), so most subclasses become empty.
      Wire them directly in the entity attribute's `delete` form handler
      and delete the empty classes.
- [x] Classes with real extra logic keep only that logic:
      `InventoryItemDeleteForm` / `ProductDeleteForm` (the "N historical
      records reference this" description), `HiveDeleteForm` /
      `QueenDeleteForm` if they have cascade specifics, and the
      submodule forms if needed.
- [x] Kernel test per entity type (data provider): cancel URL and
      post-delete redirect match the rule, including the
      missing-parent fallback.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **The parent map moved wholesale**, not duplicated: `HivelogEntityHierarchy`
  (new, `src/HivelogEntityHierarchy.php`) now owns `PARENT_FIELD` and
  `COLLECTION_THREADED_TYPES` — the exact constants task 0116 put on
  `HivelogBreadcrumbBuilder` — plus a `resolveParent(FieldableEntityInterface): ?EntityInterface`
  helper. `HivelogBreadcrumbBuilder::addAncestryLinks()` was re-pointed
  at it in the same commit as this task, so the two classes have never
  been out of sync with two copies of the map.
- **`getCancelUrl()` and `getRedirectUrl()` share one fallback helper**,
  `parentOrFallbackUrl()`: parent's canonical (via the hierarchy), else
  the entity's own collection, else `hivelog.dashboard`. Cancel calls it
  only when the entity has no canonical page of its own (own canonical
  wins first); the post-delete redirect calls it unconditionally, since
  the entity is already gone. This single helper is what makes "cancel"
  and "after delete" impossible to drift apart the way the 16 old
  classes had.
- **`?destination=` needed no special-casing** — confirmed by reading
  core's `ConfirmFormHelper::buildCancelLink()` (checks
  `$request->query->has('destination')` before ever calling
  `getCancelUrl()`) and `ContentEntityDeleteForm::submitForm()` (core's
  form-redirect handling applies `destination` to any `setRedirectUrl()`
  call automatically). Both override points in the new base are only
  ever consulted when no `destination` is present.
- **11 of 16 delete-form classes deleted outright**
  (`ApiaryDeleteForm`, `HiveDeleteForm`, `HiveInspectionDeleteForm`,
  `QueenDeleteForm`, `QueenObservationDeleteForm`, `CalendarActionDeleteForm`,
  `CalendarActionItemRequirementDeleteForm`, `CalendarActionProductYieldDeleteForm`,
  `HiveActionLogDeleteForm`, `ApiaryActionLogDeleteForm`,
  `InventoryPurchaseDeleteForm`) — every entity attribute's
  `handlers.form.delete` now points at `HivelogEntityDeleteForm::class`
  directly. Neither `HiveDeleteForm` nor `QueenDeleteForm` turned out to
  have cascade-specific logic beyond the redirect (cascading deletes, if
  any, live elsewhere) — they were pure boilerplate.
- **5 classes kept, stripped to only their real logic**:
  `InventoryItemDeleteForm` / `ProductDeleteForm` keep just
  `getDescription()` + `countHistoricalReferences()` (the "N historical
  records reference this" warning); the three submodule forms
  (`SensorDeviceDeleteForm`, `ApiClientDeleteForm`,
  `AiProviderConfigDeleteForm`) keep just their own `getDescription()`
  text. All five now `extends HivelogEntityDeleteForm` instead of
  `ContentEntityDeleteForm` directly.
- **`getQuestion()` and the deletion message were dropped entirely**,
  not reimplemented — every hivelog entity type already declares
  `label_singular` on its `#[ContentEntityType]` attribute (checked
  across all 16 before relying on it), and `ContentEntityDeleteForm`'s
  own default question/message already read it. The wording changes
  slightly per type (e.g. "Are you sure you want to delete the apiary
  %name?" instead of the old hand-written "Are you sure you want to
  delete apiary %name?") — accepted, per this task's own criterion.
- **Behaviour changes, precisely identified** by comparing all 16 old
  `getCancelUrl()` / `submitForm()` implementations against the new
  single rule:
  - **Cancel now goes to the entity's own canonical page** for every
    type that has one, even where the old code skipped it: `Product`
    (was the apiary's page), `InventoryItem` (was the item's own
    collection), and `SensorDevice` / `ApiClient` / `AiProviderConfig`
    (was their own collection, core's default). This is the headline
    behaviour change and the direct point of the task.
  - **After delete**, `InventoryItem` / `InventoryPurchase` now land on
    the apiary's canonical page instead of the global collection,
    matching `Product` — flagged in this task from the start.
  - **After delete**, `QueenObservation` with no queen now falls to its
    *own* collection instead of the queen's; `HiveInspection` /
    `CalendarAction` / the requirement / yield forms with a missing
    parent now fall to the dashboard instead of jumping to the apiary
    collection (the exact grandparent-jump drift this task's Context
    section called out for `HiveInspection`). `Hive` with no apiary and
    `Queen` with no hive were already correct and are unchanged.
- **Kernel test covers the 13 core hivelog entity types** via one
  data-provider test (`HivelogEntityDeleteFormTest`, 13 scenarios):
  for each, the normal case (parent present) asserts both the cancel URL
  and the post-delete redirect, and for every type with a parent field,
  a second "orphan" fixture (built with the parent reference left empty
  — entity save doesn't enforce the base field's `required` flag, so
  this is a plain, no-mocking way to reproduce a deleted-apiary /
  unassigned-queen state) asserts the fallback. The three submodule
  types (`sensor_device`, `ai_provider_config`, `api_client`) are
  collection-threaded (no parent field at all, so no fallback case to
  cover) and were checked live on `cms2` instead of in-suite, to avoid
  pulling their own dependency chains (the `key` module, JWT, …) into
  this module's kernel bootstrap.
- **Verification**: phpcs clean (no errors; only pre-existing warnings
  elsewhere in the module) and phpstan clean on every file this task
  touched — the module has a large pre-existing backlog of phpstan
  findings unrelated to this change (entity base-field API gaps,
  missing DI, etc.), left untouched. Full `hivelog` group: 901 tests,
  14685 assertions, 3 errors — the same 3 pre-existing, unrelated
  `DashboardTest` Functional cache-redirect errors task 0116 already
  documented (a core `VariationCache` warning, nothing to do with delete
  forms).
- Key files: new `src/HivelogEntityHierarchy.php`, new
  `src/Form/HivelogEntityDeleteForm.php`, `src/Breadcrumb/HivelogBreadcrumbBuilder.php`
  (re-pointed at the shared hierarchy), 11 deleted `src/Form/*DeleteForm.php`,
  `src/Form/InventoryItemDeleteForm.php` / `ProductDeleteForm.php` and the
  three submodule `*DeleteForm.php` (slimmed), the corresponding 16
  entity attributes' `handlers.form.delete`, new
  `tests/src/Kernel/HivelogEntityDeleteFormTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]] (the shared
  parent map), [[0129-cancel-link-on-add-edit-forms]] (the same
  "where does Cancel go" rule for add / edit forms)
- Commits::
