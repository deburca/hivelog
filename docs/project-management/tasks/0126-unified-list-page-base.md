---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] `HivelogListBuilder::render()` builds the whole page: an optional
      heading with action buttons, an `hivelog:entity-table` with
      pre-rendered operations, the empty-state message, and a pager
      whenever `$limit` is set.
- [ ] Subclasses declare only what differs: `buildHeader()`,
      `buildRow()` (cells), and a small `getHeadingActions(): array` (Add
      and any cross-links) returning `hivelog:button` / `button-group`
      props. No subclass overrides `render()` except for a documented,
      genuinely extra need.
- [ ] All 13 entity-type list builders (shapes 1 and 2) use it. The
      five shape-2 pages now render the same table style. Hive,
      Inspection and Queen Observation have no context-free add route, so
      they get a heading without an Add button (AGENTS.md "Routing,
      controllers and forms").
- [ ] Paging works on every list: a kernel test with `$limit + 1`
      entities asserts a pager element and that the first page has
      `$limit` rows.
- [ ] Row access filtering from [[0124-list-page-row-access-filter]]
      keeps working. Its test still passes.
- [ ] Ad-hoc heading cross-links reviewed: "View all Queens" on
      `/hivelog/apiaries`, and the Items ↔ Purchases links on the
      inventory lists. Keep them only where they are a real workflow
      shortcut, and record the decision in Implementation notes.
- [ ] Embedded child lists on the Hive page: the Inspections list's
      extra **View** row button is removed (the label already links to
      the inspection), matching every other list. Or it is kept with a
      recorded reason.
- [ ] Empty-state wording follows one pattern ("No <plural> yet." plus
      the Add button when allowed) across all lists.
- [ ] phpcs clean; kernel + unit suite green against `cms2`; spot-check
      all 14 collection pages live.

## Implementation notes
- The pager and post-load access filtering pull against each other:
  filtering after a paged load shortens pages. Options:
  (a) filter before paging, i.e. load all IDs, filter, and page in PHP.
      Simple and correct, but costly on large lists.
  (b) a query-level access condition per entity type (owner /
      `apiary.beekeepers` membership) so the entity query pages the
      right rows.
  Recommend (a) now, given realistic HiveLog list sizes (tens to low
  hundreds). Note (b) as a follow-up if a list grows.
- Calendar Actions (shape 3) stays controller-built because of its
  filter form, but should render through the same table / pager helper.
- Key files: `src/HivelogListBuilder.php`, all 13 `*ListBuilder.php`
  files above (10 in `src/`, 1 each in `collective` / `nexus` /
  `nanoprobe`), plus `CalendarActionListBuilder` (defined but not used
  by the controller-built collection route; check whether it is dead),
  `src/Controller/CalendarActionController.php`,
  `components/entity-table/`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0124-list-page-row-access-filter]],
  [[0068-replace-dropbutton-operations]],
  [[0064-consistent-list-page-breadcrumbs]]
- Commits::
