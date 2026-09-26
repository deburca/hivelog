---
type: project
tags: [hivelog/project]
status: done
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
Static index (in suggested execution order). **All 22 tasks are `done` as of
2026-09-26**, the last being [[0140-controller-and-form-test-gaps]]; none of
them has shipped in a tagged release yet (latest tag is 1.8.9):
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

Gap-analysis follow-ups (2026-09-23). **0133 and the delete-policy set jump
the queue**:
- [[0133-route-level-entity-access]] — **critical security**: routes
  never check per-entity access; ship with 0124 as a security release
- Delete policy ([[0103-delete-policy-for-records-with-children]], user
  direction: block where possible, else warn with links; the ADR
  inventories all 28 relationships):
  - [[0134-delete-dependency-framework]] — shared registry, counts, form
    sections, execution hook (do first)
  - [[0141-delete-block-relationships]] — 13 BLOCK rows
  - [[0142-delete-cascade-owned-records]] — 9 CASCADE rows (seeded
    calendar, inline log lines, telemetry, AI insights)
  - [[0143-delete-detach-optional-references]] — 3 DETACH rows (queen,
    hive-scoped sensor, log → inspection)
  - [[0144-delete-warn-historical-references]] — 3 WARN rows (task 0045's
    item / product warnings, now with links)
  - [[0145-orphan-report-and-cleanup-command]] — `drush hivelog:orphans`
    for the existing 1,024 / 9 orphans on `cms2`
- [[0137-align-lint-static-analysis-and-test-gates]] — local lint = CI,
  submodules analysed, access tests in the hard gate
- [[0136-missing-collection-link-templates]] — unblocks the generic
  collection handling in 0116 / 0119 / 0127
- [[0138-refresh-agents-md]] — AGENTS.md describes 5 of 21 entities and
  no submodules
- [[0140-controller-and-form-test-gaps]] — write before the refactors
  they guard
- [[0135-submodule-create-permissions]] — needs a decision
- [[0139-submodule-packaging-hygiene]] — needs small decisions

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

## Key findings (2026-09-23 gap analysis)
A follow-up sweep of what the page review didn't cover: entity
definitions, permissions / access, routing, schema, deletes, CI, tests,
assets and docs. Two findings were confirmed with throwaway kernel tests
on `cms2` (deleted afterwards); the orphan counts came from SQL there.

- **Routes never check per-entity access (IDOR).** Only one hivelog
  route has `_entity_access`, contrary to
  [[0020-access-parity-custom-routes]]. A user with only "own"
  permissions passes route access for another user's apiary / hive
  view, edit, delete and Insights pages, and can add a hive to their
  apiary. The entity access handlers are correct; nothing calls them →
  [[0133-route-level-entity-access]]. This also corrects
  [[0124-list-page-row-access-filter]], which had wrongly assumed
  canonical pages returned 403.
- **Deletes orphan children.** No delete handling exists anywhere.
  `cms2` has 1,024 calendar actions and 9 inventory items pointing at
  deleted apiaries. One rule doesn't fit every relationship:
  `Apiary::postSave()` seeds ~30 calendar actions per apiary, and five
  child types have no delete page of their own. So
  [[0103-delete-policy-for-records-with-children]] classifies all 28
  relationships (13 block, 3 warn, 9 cascade, 3 detach) →
  [[0134-delete-dependency-framework]] and 0141–0145.
- **Submodule permission model differs from core.** Sensor device, API
  client and AI provider config have no "add" permission (create is
  admin-only) but do have "own" edit / delete →
  [[0135-submodule-create-permissions]].
- **Five entity types lack a `collection` link template** despite having
  collection routes → [[0136-missing-collection-link-templates]].
- **Local and CI checks differ.** Local phpcs and phpstan skip
  `modules/`. phpstan and functional tests are advisory, and functional
  tests run for the core module only →
  [[0137-align-lint-static-analysis-and-test-gates]].
- **AGENTS.md is badly out of date**: 5 entities (actually 21), no
  submodules, latest update hook `_10013` (actually `_10028`), "one
  service" (3), "eleven list builders" (14) →
  [[0138-refresh-agents-md]].
- **Packaging**: `nexus` depends on `collective:collective`, not
  `hivelog:collective`. Submodule versions are stuck at `1.0.0`.
  `drupal/key` is required for every install though only nexus uses it →
  [[0139-submodule-packaging-hygiene]].
- **Test gaps**: three controllers and 28 form classes (including every
  filter form) aren't directly tested →
  [[0140-controller-and-form-test-gaps]].
- **Checked and clean**: no unused libraries, CSS files, SDC components
  or PHP classes. Declared permissions == referenced permissions. No
  pending entity schema changes on `cms2`, whose copy matches the repo.
  Every entity type has kernel tests; every test is in the `hivelog`
  group.

## Open questions
- ~~Should the full-page lists (Hives, Inspections, Queen Observations)
  get the same filter forms their embedded counterparts on the Apiary /
  Hive pages already have?~~ **Resolved 2026-09-23: yes**, the same
  filters (no new cross-apiary ones) →
  [[0132-filters-on-hive-inspection-observation-lists]].
- [[0131-single-detail-table-css-class]] renames theming-API class names
  (AGENTS.md "Theming HiveLog"). Keep the old names as aliases for one
  minor release, or break them in a major?
- No entity type has Views integration (`views_data` handler), so every
  list, report and export is custom controller code. Deliberate
  (ADR-0004's custom-controller approach), or wanted for site builders?
  No task created until decided.
- [[0103-delete-policy-for-records-with-children]]'s own open questions
  gate [[0134-delete-dependency-framework]]: confirm the CASCADE and
  DETACH treatments (recommended where neither block nor warn works),
  what to do when a user can't delete the blocking children, and user
  accounts.

## Related decisions
- [[0103-delete-policy-for-records-with-children]] (proposed)
- [[0020-access-parity-custom-routes]]
- [[0004-custom-controllers-over-view-builders]]
- [[0012-action-button-design-system]]
- [[0060-visual-identity-in-site-theme]]
- [[0099-submodule-canonical-page-panel-hook]]
