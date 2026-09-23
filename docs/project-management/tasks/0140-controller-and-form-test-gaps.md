---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[page-structure-consistency]]"
area: tests
created: 2026-09-23
branch: feature/0140-controller-and-form-test-gaps
release:
depends-on:
blocked-by:
---
# Task: Close the controller and form test gaps

## Context
From the 2026-09-23 gap analysis. Every entity type has kernel tests,
and all 77 test classes carry `#[Group('hivelog')]`. But some page code
is never exercised by name:

- **Controllers with no test at all:** `InventoryItemController`,
  `InventoryPurchaseController`, `ProductController`.
- **Forms not referenced by any test (28):** most entity forms
  (`ApiaryForm`, `HiveForm`, `QueenForm`, `ProductForm`,
  `InventoryItemForm`, `ApiaryActionLogForm`,
  `CalendarActionItemRequirementForm`, `ApiClientForm`,
  `SensorDeviceForm`, …), most delete forms, and **every filter form**
  (`HivelogHiveFilterForm`, `HivelogInspectionFilterForm`,
  `HivelogQueenObservationFilterForm`, `HivelogCalendarFilterForm`,
  `HivelogFullCalendarFilterForm`, `HivelogCalendarActionsFilterForm`,
  `SensorReadingFilterForm`).

Several tasks in this project restructure exactly this code
([[0125-shared-detail-page-builder]], [[0126-unified-list-page-base]],
[[0127-shared-delete-form-base]], [[0129-cancel-link-on-add-edit-forms]],
[[0132-filters-on-hive-inspection-observation-lists]]). Tests written
against today's behaviour first make those refactors safe.

## Acceptance criteria
- [ ] Kernel tests for the three untested controllers: view renders
      for an authorised user, access is denied for an outsider (via the
      route, per [[0133-route-level-entity-access]]), and Edit / Delete
      appear only with the matching access.
- [ ] Filter forms: one data-provider kernel test per form. Each query
      key narrows the result set, and Reset targets the right URL.
      Write these **before** [[0132-filters-on-hive-inspection-observation-lists]]
      moves the filter logic, so that move is test-guarded.
- [ ] Entity forms: at least a build + validate + save round trip per
      form, including the scoped-add parent pre-population and the
      post-save redirect target.
- [ ] Delete forms: covered by [[0127-shared-delete-form-base]]'s
      per-type test. Don't duplicate; link here once done.
- [ ] kernel + unit suite green against `cms2`.

## Implementation notes
- Sequence: filter-form tests before 0132, detail-controller tests
  before 0125. Order these subtasks by whichever refactor is scheduled
  first rather than finishing this whole task up front.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0125-shared-detail-page-builder]],
  [[0126-unified-list-page-base]], [[0127-shared-delete-form-base]],
  [[0129-cancel-link-on-add-edit-forms]],
  [[0132-filters-on-hive-inspection-observation-lists]],
  [[0133-route-level-entity-access]]
- Commits::
