---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0126-unified-list-page-base
release:
depends-on: ["[[0124-list-page-row-access-filter]]"]
blocked-by:
---
# Task: One list-page implementation in `HivelogListBuilder`

## Context
From the page-structure review of 2026-09-23. Collection pages come in
three shapes (checked live on `cms2`):

1. **SDC table with heading** (8): Apiary, Queen, InventoryItem,
   InventoryPurchase, Product, SensorDevice, ApiClient,
   AiProviderConfig. Each overrides `render()` (~60 lines) to build a
   `hivelog-list-heading` with an Add button and a `hivelog:entity-table`,
   pre-rendering the operations cell.
2. **Core table, no heading** (5): Hive, HiveInspection,
   QueenObservation, HiveActionLog, ApiaryActionLog. They inherit core's
   `#type => 'table'` (`responsive-enabled`), so they have no heading, no
   Add button, different markup and different styling.
3. **Controller-built** (1): Calendar Actions
   (`CalendarActionController::collection()`), with a filter form.

Plus a **latent silent-truncation bug**. Core
`EntityListBuilder::$limit` is 50 and `load()` pages by it, but the
shape-1 builders' `render()` never outputs a `#type => 'pager'`. Only
`ApiaryListBuilder` does (with `$limit = 20`). Any of the other seven
lists would silently stop at row 50. Current data is under 50, so
nobody has hit it yet.

## Acceptance criteria
- [x] `HivelogListBuilder::render()` builds the whole page: an optional
      heading with action buttons, an `hivelog:entity-table` with
      pre-rendered operations, the empty-state message, and a pager
      whenever `$limit` is set.
- [x] Subclasses declare only what differs: `buildHeader()`,
      `buildRow()` (cells), and a small `getHeadingActions(): array` (Add
      and any cross-links) returning `hivelog:button` / `button-group`
      props. No subclass overrides `render()` except for a documented,
      genuinely extra need.
- [x] All 13 entity-type list builders (shapes 1 and 2) use it. The
      five shape-2 pages now render the same table style. Hive,
      Inspection and Queen Observation have no context-free add route, so
      they get a heading without an Add button (AGENTS.md "Routing,
      controllers and forms").
- [x] Paging works on every list: a kernel test with `$limit + 1`
      entities asserts a pager element and that the first page has
      `$limit` rows.
- [x] Row access filtering from [[0124-list-page-row-access-filter]]
      keeps working. Its test still passes.
- [x] Ad-hoc heading cross-links reviewed: "View all Queens" on
      `/hivelog/apiaries`, and the Items ↔ Purchases links on the
      inventory lists. Keep them only where they are a real workflow
      shortcut, and record the decision in Implementation notes.
- [x] Embedded child lists on the Hive page: the Inspections list's
      extra **View** row button is removed (the label already links to
      the inspection), matching every other list. Or it is kept with a
      recorded reason.
- [x] Empty-state wording follows one pattern ("No <plural> yet." plus
      the Add button when allowed) across all lists.
- [x] phpcs clean; kernel + unit suite green against `cms2`; spot-check
      all 14 collection pages live.

## Implementation notes
**Implemented 2026-09-24.**
- **Filter-before-page, option (a)** from the two listed here — load
  every matching ID (no query-level `LIMIT`/`OFFSET`), load the
  entities, filter by `access('view')`, *then* slice to the current
  page via `\Drupal::service('pager.manager')->createPager()`. Shared
  between `HivelogListBuilder::load()` and
  `CalendarActionController::collection()` (which had the identical
  pager-before-filter bug, fixed the same way) through a new
  `HivelogListPageTrait` (`paginateEntities()`,
  `buildEntityTable()`) — a trait rather than another base class,
  since the controller can't extend `HivelogListBuilder`.
- **`getHeadingActions(): array`** returns button *props* (not a
  pre-built component), always wrapped in one `hivelog:button-group` —
  including the single-button cases (Queen, Product, …), which render
  identically to a standalone button (the button-group CSS's
  `:first-child`/`:last-child` corner rules both match a single child).
  Simpler than keeping two different heading shapes.
- **Cross-links kept, both of them**: "View all Queens" on
  `/hivelog/apiaries` (queens have no other menu-independent path to
  their collection) and Items ↔ Purchases on the two inventory lists
  (recording a purchase is the very next step after cataloguing an
  item, and vice versa reviewing purchases against the catalog). Both
  predate this task and nothing about the unification argues for
  dropping either.
- **`CalendarActionListBuilder` is not dead**, just not routed to
  directly — `entity.calendar_action.collection` uses
  `CalendarActionController::collection()` for its filter form, but the
  list builder is still the registered `list_builder` handler,
  reachable through the generic entity API, and `HiveCalendarChecklistTest`
  already exercises it directly as an unfiltered "management view"
  comparison point against the controller's scope/enabled-filtered
  checklist view. Migrated to the shared base like the other 12; left
  registered.
- **Redundant "View" button removed from three embedded lists**, not
  just the one the acceptance criterion named: the Hive page's
  Inspections column, the Hive page's Queen Observations column, and
  the Queen page's own Observations column all had the same pattern (a
  View button alongside a row whose own label already links to the
  same canonical page) — fixing only one would have left the other two
  inconsistent, undermining the point.
- **Calendar Actions' empty-state wording is a deliberate exception**
  to the "No `<plural>` yet." pattern: `CalendarActionController::
  collection()` distinguishes "no calendar actions have been added
  yet" from "no calendar actions match the current filters" — a
  generic message would be misleading when filters are active. Every
  other list (all 13 `HivelogListBuilder` subclasses, none of which
  have filters) uses the one shared message from `render()`.
- **`declare(strict_types=1)`** was already inconsistent across the 13
  files before this task (present in some, absent in others); left as
  each file already had it rather than adding it as a drive-by change.
- **A real bug found via the Functional suite, not Kernel**: the first
  version of `render()` passed `$this->buildOperations($entity)`
  directly into `$this->renderer->renderInIsolation()`, which takes its
  argument by reference — "Only variables should be passed by
  reference." Kernel tests didn't surface it (PHP notices aren't fatal
  there); `PermissionMatrixTest` (Functional, stricter error handling)
  did. Fixed by assigning to a local variable first, matching every
  other `renderInIsolation()` call site in the module.
- Key files: `src/HivelogListBuilder.php`, new
  `src/HivelogListPageTrait.php`, all 13 `*ListBuilder.php` files (10 in
  `src/`, 1 each in `collective` / `nexus` / `nanoprobe`),
  `src/CalendarActionListBuilder.php`,
  `src/Controller/CalendarActionController.php` (pagination fix + shared
  table helper), `src/Controller/HiveController.php` and
  `src/Controller/QueenController.php` (redundant View button removal),
  new `tests/src/Kernel/HivelogListBuilderPaginationTest.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0124-list-page-row-access-filter]],
  [[0068-replace-dropbutton-operations]],
  [[0064-consistent-list-page-breadcrumbs]]
- Commits::
