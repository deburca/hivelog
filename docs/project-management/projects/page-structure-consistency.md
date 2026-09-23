---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-23
---
# Project: Page Structure Consistency

## Goal
Every HiveLog page of the same kind (detail page, list page, add / edit
form, delete confirmation) is built from one shared implementation. It
then looks, behaves and is access-checked the same way, whichever entity
type or submodule it belongs to. Beekeepers get predictable pages;
maintainers get one place to fix each page type instead of 9–16 copies.

## Scope
- In scope: canonical (detail) page controllers, collection list
  builders, add / edit / delete forms, the embedded child lists on the
  Apiary and Hive pages, heading hierarchy, and the per-page CSS classes
  those pages emit. Covers `hivelog` core and the `collective`, `nexus`
  and `nanoprobe` submodules.
- Out of scope: breadcrumbs, menus, the nav strip and local tasks (see
  [[breadcrumb-consistency]]; the two projects meet at
  [[0118-page-owned-edit-delete-then-retire-local-tasks]]). Also out of
  scope: button colour and size ([[action-button-consistency]]), the
  dashboard's own layout ([[dashboard-landing-page]]), and new page
  content or features.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT priority asc, file.name asc
```
Static index (in suggested execution order):
- [[0124-list-page-row-access-filter]] — **security**: list pages
  show other users' records (high, do first)
- [[0125-shared-detail-page-builder]] — one helper for the detail-page
  sections copied into 9 controllers; pairs with
  [[0118-page-owned-edit-delete-then-retire-local-tasks]]
- [[0126-unified-list-page-base]] — one list-page implementation;
  fixes the silent 50-row cutoff
- [[0127-shared-delete-form-base]] — one delete-form base with a single
  cancel / redirect rule
- [[0128-page-heading-hierarchy]] — no skipped heading levels
- [[0129-cancel-link-on-add-edit-forms]] — a way back from every form
- [[0130-shared-calendar-checklist-helpers]] — de-duplicate the Apiary /
  Hive calendar helpers
- [[0131-single-detail-table-css-class]] — one CSS class instead of 13
- [[0132-filters-on-hive-inspection-observation-lists]] — the full Hives /
  Inspections / Queen Observations lists get their embedded versions'
  filters (user decision, 2026-09-23); after 0126

## Key findings (2026-09-23 page-structure review)
Read all 21 page controllers, 15 list builders and 32 forms, and fetched
32 pages live on `cms2` (`quick_silver`, logged in as admin).

- **List pages leak other users' rows.** Only
  `SensorDeviceListBuilder::load()` filters rows by `access('view')`.
  Core `EntityListBuilder`'s `accessCheck(TRUE)` doesn't filter an entity
  type that has no query-access handler. Proven with a throwaway kernel
  test (deleted afterwards): a user with only `view own apiary` /
  `view own hive` sees another user's apiary and hive names on
  `/hivelog/apiaries` and `/hivelog/hives` →
  [[0124-list-page-row-access-filter]].
- **Silent 50-row cutoff.** Core `EntityListBuilder::$limit` is 50 and
  `load()` pages by it. Seven builders override `render()` and never
  output a pager (only `ApiaryListBuilder` does, with `$limit = 20`), so
  rows past 50 simply don't appear. Latent: current data is under 50 →
  [[0126-unified-list-page-base]].
- **Two list-page families.** Eight builders render the
  `hivelog:entity-table` SDC with a heading and Add button. Hives,
  Inspections, Queen Observations and both action-log lists fall back to
  core's `#type => 'table'` (`responsive-enabled`): no heading, no Add,
  different styling. Calendar Actions is a third shape (a controller
  with filters) → [[0126-unified-list-page-base]].
- **Detail-page code copied 9 times.** `buildActions()` (22 lines),
  `buildSection()` (25) and `buildRows()` (18) are identical apart from
  the entity variable and class name in the Queen, QueenObservation,
  HiveInspection, CalendarAction, HiveActionLog, ApiaryActionLog,
  InventoryItem, InventoryPurchase and Product controllers (~585 lines).
  `buildFieldValue()` shares a common core with per-type cases.
  `buildPhotosGrid()` is copied twice → [[0125-shared-detail-page-builder]].
- **Edit / Delete missing or duplicated.** Apiary and Hive pages have
  no page-owned Edit / Delete (only the Navigation top-bar tabs). Sensor
  device, API client and AI provider config have neither →
  [[0118-page-owned-edit-delete-then-retire-local-tasks]] (tracked in
  [[breadcrumb-consistency]]; builds on
  [[0125-shared-detail-page-builder]]).
- **Delete forms disagree on where to go.** 16 classes, 838 lines.
  Cancel goes to the entity (5 forms), to its parent (5) or to the
  collection (inventory item / purchase). After delete, apiary-scoped
  inventory items / purchases go to the global collection while
  products go to their apiary → [[0127-shared-delete-form-base]].
- **Heading levels skip.** Detail pages go H1 → H3. The Hive page runs
  H4 → H3 → H2 → H3. Insights and the dashboard use H2 →
  [[0128-page-heading-hierarchy]].
- **No Cancel on any add / edit form** (16 forms). Combined with
  [[0117-breadcrumb-terminal-crumb-on-form-pages]]'s bug, there is no
  in-page way back → [[0129-cancel-link-on-add-edit-forms]].
- **Duplicated calendar helpers.** `extractCalendarFilters()` and
  `pendingActionTimingLabel()` are identical in the Apiary and Hive
  controllers, and `calendarChecklistEmptyMessage()` differs only in
  wording. `secondsUntilNextIsoWeek()` appears in 3 controllers and
  `viewableApiaries()` in 2. The Apiary and Hive controllers are the two
  largest (1,272 / 1,414 lines) → [[0130-shared-calendar-checklist-helpers]].
- **13 per-entity detail-table classes** (`hivelog-queen-table`,
  `hivelog-product-table`, …), each with the same 9 selector
  occurrences in `css/hivelog.tables.css` →
  [[0131-single-detail-table-css-class]].
- Already consistent, worth keeping: detail-page layout (Edit / Delete
  at top, "Overview" first, two-column label / value tables), the
  "Sub-page: Entity" title pattern (`Insights: …`, `Full Calendar: …`,
  `Sensor Readings: …`), and button styling.

## Open questions
- ~~Should the full-page lists (Hives, Inspections, Queen Observations)
  get the same filter forms their embedded counterparts on the Apiary /
  Hive pages already have?~~ **Resolved 2026-09-23: yes**, the same
  filters (no new cross-apiary ones) →
  [[0132-filters-on-hive-inspection-observation-lists]].
- [[0131-single-detail-table-css-class]] renames theming-API class names
  (AGENTS.md "Theming HiveLog"). Keep the old names as aliases for one
  minor release, or break them in a major?

## Related decisions
- [[0004-custom-controllers-over-view-builders]]
- [[0012-action-button-design-system]]
- [[0060-visual-identity-in-site-theme]]
- [[0099-submodule-canonical-page-panel-hook]]
