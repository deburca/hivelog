---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0129-cancel-link-on-add-edit-forms
release:
depends-on:
blocked-by:
---
# Task: Cancel link on every add / edit form

## Context
From the page-structure review of 2026-09-23. None of the 16 add / edit
entity forms (13 in `src/Form/`, plus `ApiClientForm`,
`AiProviderConfigForm`, `SensorDeviceForm`) offers a Cancel action.
Delete confirmation forms do, via core's `ConfirmFormBase`. Combined
with the breadcrumb bug in [[0117-breadcrumb-terminal-crumb-on-form-pages]]
(the crumb you'd go back to is plain text), the only way off a form
without saving is the browser's back button or the nav strip.

## Acceptance criteria
- [x] Every add / edit entity form has a **Cancel** link in
      `form-actions`, after Save (and after Delete on edit forms),
      rendered as a `hivelog:button` in the default variant, so it is
      styled by `hivelog.buttons.css` (`.hivelog-entity-form
      .form-actions` is already a registered context wrapper).
- [x] Cancel target rule, same spirit as
      [[0127-shared-delete-form-base]]:
      - `?destination=` in the request wins.
      - Edit form: the entity's canonical page.
      - Scoped add form: the parent's canonical page (e.g.
        `/hivelog/apiary/43/hive/add` → apiary 43).
      - Site-wide add form: the collection.
- [x] Implemented once, e.g. a `HivelogEntityFormTrait::addCancelAction()`
      or a shared `HivelogContentEntityForm` base in `hivelog` core that
      submodule forms can use. Not 16 copies.
- [x] Kernel test (data provider over the form handlers): the cancel link
      exists and points at the expected URL for an edit form, a scoped
      add form and a site-wide add form.
- [x] Visual check on `cms2` for one form per module.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **A trait, exactly as the task's own first-choice suggestion**:
  `HivelogEntityFormTrait::actions()` (new,
  `src/Form/HivelogEntityFormTrait.php`) overrides `actions()` — calling
  `parent::actions()` then appending a `cancel` element — and every one
  of the 16 add/edit form classes just adds `use HivelogEntityFormTrait;`.
  None of them defined `actions()` before (confirmed by grep across all
  16 first), so there was no override to preserve and no trait-collision
  risk with the other traits three of them already use
  (`ApiaryScopedAutocompleteTrait`, `InventoryUsageFormTrait`,
  `HarvestYieldFormTrait` — none define `actions()` either).
- **Cancel's position needed an explicit high weight, not array order.**
  Core's `EntityForm::actionsElement()` always physically moves `delete`
  to the end of the actions array *before* it assigns weights in
  (post-move) array order — so appending `cancel` after `delete` in the
  array `HivelogEntityFormTrait::actions()` returns isn't enough on its
  own; core's own reordering would still put Cancel before Delete.
  Giving `cancel` an explicit `#weight => 100` (higher than anything
  core assigns — submit gets 5, delete gets 10) sidesteps that: PHP's
  `+=` array union leaves an already-set `#weight` alone, so Cancel
  reliably renders last whether or not Delete exists. Confirmed both by
  the kernel test and live (`Save, Delete, Cancel` in that order on
  `/hivelog/hive/1/edit`).
- **The parent map, actually shared this time.** `HivelogEntityHierarchy`
  gained one new static method, `parentOrCollectionUrl()` — the exact
  "parent's canonical, else the entity's own collection, else the
  dashboard" chain `HivelogEntityDeleteForm::parentOrFallbackUrl()`
  already had, extracted so both classes (and now this trait's
  `cancelUrl()`) read one definition rather than two identical copies.
  `HivelogEntityDeleteForm` was refactored to delegate to it — a pure
  extraction, re-verified against its own full kernel/unit suite
  afterward with no change in behaviour.
- **A new, real bug in that extraction, caught immediately by the
  kernel test**: `EntityBase::toUrl()` refuses *any* `$rel` — including
  `'collection'`, which needs no ID in its URL — on an entity with no ID
  yet (`EntityMalformedException`). The delete-form's post-delete
  context never hit this (the entity object keeps its ID in memory after
  `->delete()`), but a brand-new, unsaved add-form entity does. Fixed by
  building the collection URL from the route name
  (`entity.<type>.collection`) instead of `$entity->toUrl('collection')`
  — works in both contexts, so this was a latent problem in the shared
  helper that this task's edge cases happened to be the first to trip.
- **Collection-threaded types (`sensor_device`, `ai_provider_config`,
  `api_client`) cancel to their own collection, not a hive/apiary**,
  for both their edit forms and (for `sensor_device`) its two scoped add
  routes — a direct, deliberate consequence of reusing
  `HivelogEntityHierarchy`'s existing design (these types have no entry
  in `PARENT_FIELD` at all, by task 0116's own original decision) rather
  than a new special case. Verified live on
  `/hivelog/sensor-device/2/edit`.
- **`?destination=` handled the same way core's own
  `ConfirmFormHelper::buildCancelLink()` does** (`UrlHelper::parse()` +
  `Url::fromUserInput()`, wrapped in the same try/catch) — this trait
  builds a plain link, not a `ConfirmFormInterface` form, so core's
  automatic destination handling for confirm-form cancel links doesn't
  apply here and has to be replicated explicitly.
- **Verification**: 10 new kernel tests (`HivelogEntityFormCancelActionTest`,
  131 assertions) covering every scenario the acceptance criteria name
  plus two the criteria didn't spell out but the rule implies: the
  no-canonical-page fallback (`calendar_action_item_requirement`'s edit
  and scoped-add forms, which have no canonical page of their own and
  so fall through to their calendar action's), and the `?destination=`
  override. phpcs clean (0 errors) and phpstan clean on every file this
  task touched. Full `hivelog` suite: 912 tests, same 3 pre-existing
  unrelated `DashboardTest` Functional errors task 0116/0127/0117 already
  documented. Live-verified one form per module — hive edit
  (`hivelog`), sensor device edit (`nanoprobe`), API client edit
  (`collective`), AI provider config edit (`nexus`) — plus an
  apiary-scoped hive add and a site-wide queen add, all showing the
  correct Cancel target and button styling.
- Key files: new `src/Form/HivelogEntityFormTrait.php`,
  `src/HivelogEntityHierarchy.php` (new `parentOrCollectionUrl()`),
  `src/Form/HivelogEntityDeleteForm.php` (refactored to reuse it), the
  13 core `src/Form/*Form.php` add/edit classes and the three submodule
  entity forms (one `use` line each), new
  `tests/src/Kernel/HivelogEntityFormCancelActionTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0117-breadcrumb-terminal-crumb-on-form-pages]],
  [[0127-shared-delete-form-base]]
- Commits::
