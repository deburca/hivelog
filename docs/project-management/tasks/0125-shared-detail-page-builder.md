---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0125-shared-detail-page-builder
release:
depends-on:
blocked-by:
---
# Task: One shared builder for detail-page sections

## Context
From the page-structure review of 2026-09-23. Nine canonical-page
controllers each carry their own copy of the same four helpers:

| Helper | Lines per copy | Difference between copies |
|---|---|---|
| `buildActions()` | 22 | entity variable name only |
| `buildSection()` | 25 | entity variable plus `hivelog-<type>-section` / `-table` class |
| `buildRows()` | 18 | entity variable only |
| `buildFieldValue()` | 32–62 | shared core (entity refs, list options, dates, booleans, text) plus per-type special cases |

The nine are the Queen, QueenObservation, HiveInspection,
CalendarAction, HiveActionLog, ApiaryActionLog, InventoryItem,
InventoryPurchase and Product controllers: about 585 lines of verbatim
duplication before `buildFieldValue()`. `buildPhotosGrid()` is also
copied between `HiveInspectionController` and
`QueenObservationController` (54 lines each, only classes differ), with
a third variant, `HiveController::buildImagesGrid()`.

The copies are why the pages that *don't* have them (Apiary, Hive,
SensorDevice, ApiClient, AiProviderConfig) have no page-owned Edit /
Delete; see [[0118-page-owned-edit-delete-then-retire-local-tasks]].

## Acceptance criteria
- [x] One shared implementation (a trait such as
      `HivelogDetailPageTrait`, or a small `hivelog.detail_page_builder`
      service; pick one and justify it in Implementation notes)
      provides:
      - `buildActions(EntityInterface $entity)`: Edit / Delete
        `hivelog:button-group`, access-checked, Delete in the danger
        variant.
      - `buildSection($title, EntityInterface $entity, array $fields)`
        and `buildRows()`.
      - `buildFieldValue()`: the shared core, with a protected hook
        method (or per-field callback map) that controllers override for
        their own special cases.
      - `buildPhotosGrid(EntityInterface $entity, string $field = 'images')`.
- [x] All nine controllers use it. Their copies are deleted and only
      genuinely type-specific field rendering remains in each.
- [x] Rendered output is **unchanged** for all nine page types (same
      markup and classes). The class names are kept here;
      [[0131-single-detail-table-css-class]] changes them separately.
- [x] Available to submodules (`hivelog` core namespace, no submodule
      dependency), so
      [[0118-page-owned-edit-delete-then-retire-local-tasks]] can use it
      for SensorDevice / ApiClient / AiProviderConfig.
- [x] Existing controller kernel tests pass unchanged. Add a direct test
      of the shared `buildActions()` access behaviour (buttons present
      with update / delete access, absent without).
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **Trait, not a service**: `src/HivelogDetailPageTrait.php`. Keeps each
  controller's `create()` / DI untouched, matches
  `ApiaryScopedAutocompleteTrait`'s existing precedent in `src/Form/`,
  and lets `formatFieldValue()` — the per-type hook — stay a normal
  overridable method rather than a callback-map argument threaded
  through a service call.
- **Hook method, not a callback map**: `buildFieldValue()` does the one
  shared thing every copy did (empty → em dash) and delegates to an
  `abstract protected function formatFieldValue(FieldableEntityInterface
  $entity, string $field_name, FieldItemListInterface $field): array`
  that each controller implements — the type-specific `switch`
  statement moved over verbatim (module-private field-name string, so a
  callback map would have added indirection for no benefit).
- **`FieldableEntityInterface`, not `EntityInterface`**, on every trait
  method — `EntityInterface` has no `get()`; every hivelog entity is
  fieldable (and any future submodule consumer — SensorDevice,
  ApiClient, AiProviderConfig — is too), so this is the correct, not
  just convenient, type. Avoids ~50 spurious `method.notFound` phpstan
  findings the plain `EntityInterface` would have caused across nine
  consuming classes.
- **Class-prefix mapping**: `detailPageClassPrefix()` special-cases
  `hive_inspection` → `inspection`; every other type mechanically
  `str_replace('_', '-', $entity->getEntityTypeId())`. Verified live on
  `cms2` (`/hivelog/inspection/7` renders `hivelog-inspection-*`, not
  `hivelog-hive-inspection-*`).
- **Verification**: all 9 controllers' existing kernel test suites
  (121 tests, 2055 assertions) pass unchanged — the strongest signal
  the rendered markup didn't move. New
  `tests/src/Kernel/HivelogDetailPageTraitTest.php` exercises
  `buildActions()` directly via reflection on `QueenController` across
  four permission combinations. Full `hivelog` suite (645 tests) green;
  spot-checked queen/inspection/product pages live on `cms2`.
- **`buildPhotosGrid()`** requires `$this->fileUrlGenerator` on the
  consuming class; only `HiveInspectionController` and
  `QueenObservationController` declare it and call it. The other seven
  don't call it, so it's inert there — documented in the trait's own
  docblock rather than split into a second trait, since the task asks
  for "one shared implementation" providing all four builders.
- Key files: the nine controllers in `src/Controller/`, the new
  `src/HivelogDetailPageTrait.php`, new
  `tests/src/Kernel/HivelogDetailPageTraitTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0004-custom-controllers-over-view-builders]],
  [[0012-action-button-design-system]]
- Tasks:: [[0118-page-owned-edit-delete-then-retire-local-tasks]] (uses
  this), [[0131-single-detail-table-css-class]]
- Commits::
