---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] One shared implementation (a trait such as
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
- [ ] All nine controllers use it. Their copies are deleted and only
      genuinely type-specific field rendering remains in each.
- [ ] Rendered output is **unchanged** for all nine page types (same
      markup and classes). The class names are kept here;
      [[0131-single-detail-table-css-class]] changes them separately.
- [ ] Available to submodules (`hivelog` core namespace, no submodule
      dependency), so
      [[0118-page-owned-edit-delete-then-retire-local-tasks]] can use it
      for SensorDevice / ApiClient / AiProviderConfig.
- [ ] Existing controller kernel tests pass unchanged. Add a direct test
      of the shared `buildActions()` access behaviour (buttons present
      with update / delete access, absent without).
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- A trait keeps each controller's `create()` / DI untouched and matches
  how `ApiaryScopedAutocompleteTrait` is already used in `src/Form/`. A
  service is easier to unit-test. Both are fine; don't do both.
- Section / table classes are currently built from the entity type ID
  (`hivelog-queen-section`, `hivelog-inventory-purchase-table`). Derive
  them the same way (`str_replace('_', '-', $entity->getEntityTypeId())`)
  to keep output identical. Watch `hive_inspection` →
  `hivelog-inspection-*` (not `hivelog-hive-inspection-*`), which needs
  an explicit mapping or a class-prefix parameter.
- Key files: the nine controllers in `src/Controller/`, plus the new
  trait or service.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0004-custom-controllers-over-view-builders]],
  [[0012-action-button-design-system]]
- Tasks:: [[0118-page-owned-edit-delete-then-retire-local-tasks]] (uses
  this), [[0131-single-detail-table-css-class]]
- Commits::
