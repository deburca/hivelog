---
type: task
tags: [hivelog/task]
status: done
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
- [x] Kernel tests for the three untested controllers: view renders
      for an authorised user, access is denied for an outsider (via the
      route, per [[0133-route-level-entity-access]]), and Edit / Delete
      appear only with the matching access.
- [x] Filter forms: one data-provider kernel test per form. Each query
      key narrows the result set, and Reset targets the right URL.
      Write these **before** [[0132-filters-on-hive-inspection-observation-lists]]
      moves the filter logic, so that move is test-guarded.
- [x] Entity forms: at least a build + validate + save round trip per
      form, including the scoped-add parent pre-population and the
      post-save redirect target.
- [x] Delete forms: covered by [[0127-shared-delete-form-base]]'s
      per-type test. Don't duplicate; link here once done.
- [x] kernel + unit suite green against `cms2`.

## Implementation notes
By the time this task was picked up, tasks 0125–0132 had already landed,
so a fresh audit (grepping `tests/` and `modules/*/tests/` for each
controller/form's class name and for `entity.form_builder`/
`getFormObject`/`FormState` usage) replaced the original "do this before
that refactor" sequencing — most of the 28 forms flagged in the 2026-09-23
gap analysis turned out to already have solid coverage added by those
intervening tasks. Only genuine remaining gaps got new tests:

- **Controllers** — added `InventoryItemTest::testItemViewRendersGroupedSections()`
  et al., `InventoryPurchaseTest`, and `ProductTest` kernel coverage for
  `InventoryItemController`, `InventoryPurchaseController` and
  `ProductController`: view rendering, per-[[0133-route-level-entity-access]]
  outsider denial, and Edit/Delete button-group presence gated on access.
- **Filter forms** — audit found `HivelogHiveFilterForm`,
  `HivelogInspectionFilterForm`, `HivelogQueenObservationFilterForm`,
  `HivelogCalendarFilterForm`, `HivelogFullCalendarFilterForm` and
  `HivelogCalendarActionsFilterForm` already had thorough query-narrowing
  coverage (`FullListFilterTest`, `EmbeddedTableFilterPaginationTest`,
  `ApiaryCalendarChecklistTest`, `HiveCalendarChecklistTest`,
  `CalendarActionCollectionTest`) and `SensorReadingFilterForm` was
  covered by `SensorDeviceReadingsTest`. The one universal gap was the
  **Reset button's target URL** — added one assertion per form to each of
  those five files plus `SensorDeviceReadingsTest`, and a missing
  `temperament` query-key test to `EmbeddedTableFilterPaginationTest`.
- **Entity forms** — `ApiaryForm`, `HiveForm`, `HiveInspectionForm`
  (functional `EntityCrudJourneyTest`), `ApiaryActionLogForm`/
  `HiveActionLogForm` (`InventoryUsageReportingIntegrationTest`,
  `HarvestYieldReportingIntegrationTest`, `HiveActionLogInspectionLinkTest`),
  `ApiClientForm` and `SensorDeviceForm` (their submodules'
  `*ManagementUiTest` classes) were all already covered end to end.
  Genuine gaps — `QueenForm`, `ProductForm`, `InventoryItemForm`,
  `InventoryPurchaseForm`, `CalendarActionForm`,
  `CalendarActionItemRequirementForm` and `CalendarActionProductYieldForm`
  — got new build+validate+save round-trip tests in two new files,
  `ScopedEntityFormRoundTripTest` and `CalendarActionFormRoundTripTest`,
  each covering the scoped-add controller's parent pre-population, the
  form's custom `validateForm()` check (where one exists), and the
  post-save redirect target.
- **Delete forms** — `HivelogEntityDeleteFormTest` (task 0127) already
  data-provider-covers apiary/hive/queen/calendar_action/inventory_item/
  product delete forms including parent-relationship scenarios; not
  duplicated here.
- Full `hivelog` kernel+unit+functional suite (787 tests), `phpcs` and
  `phpstan` all green against `cms2`, covering core and every submodule
  (nanoprobe/collective/nexus/assimilate).

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0125-shared-detail-page-builder]],
  [[0126-unified-list-page-base]], [[0127-shared-delete-form-base]],
  [[0129-cancel-link-on-add-edit-forms]],
  [[0132-filters-on-hive-inspection-observation-lists]],
  [[0133-route-level-entity-access]]
- Commits::
