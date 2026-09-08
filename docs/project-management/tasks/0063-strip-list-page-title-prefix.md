---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: theme
created: 2026-09-08
branch: feature/0063-strip-list-page-title-prefix
release:
depends-on:
blocked-by:
---
# Task: Drop the "HiveLog - " page-title prefix on list pages

## Context
The entity-collection routes hard-code `_title: 'HiveLog - Apiaries'`,
`'HiveLog - Hives'`, etc. On a themed site (the beeswax skin, task 0061)
the page `<h1>` then reads "HiveLog - Apiaries" while the list builder
renders a second `<h3 class="hivelog-list-heading__title">Apiaries</h3>`
just below it — the two say the same thing, only the prefix differs, and
the section is already inside the HiveLog area so "HiveLog -" is
redundant. The `HiveLog` wordmark belongs on the dashboard masthead, not
stamped on every sub-page title.

## Acceptance criteria
- [x] **`hivelog.routing.yml`** — strip the `HiveLog - ` prefix from every
      static `_title` (11 collection routes): apiary / hive / inspection /
      queen / queen-observation / calendar-action / hive-action-log /
      apiary-action-log / inventory-item / inventory-purchase / product.
      `Add …` / `Edit …` / `Delete …` titles never carried the prefix;
      `_title_callback` routes (canonical pages, the financial reports)
      are unchanged.
- [x] **Drop the redundant section heading.** The five list builders
      that render a `hivelog-list-heading` with a `title` sub-element
      (`ApiaryListBuilder`, `QueenListBuilder`, `InventoryItemListBuilder`,
      `InventoryPurchaseListBuilder`, `ProductListBuilder`) lose that
      `<h3>` — its text is now identical to the page `<h1>`. The
      `hivelog-list-heading` container and its "Add …" action button stay
      (`.hivelog-list-heading__action` keeps `margin-left: auto`, so the
      button still sits top-right). The controller sub-list headings
      (`ApiaryController` "Hives", `HiveController` "Inspections", …) are
      **not** touched — there the `<h3>` names a section of a page whose
      `<h1>` is the entity label, so it is not a duplicate.
- [x] Tests: a kernel test asserting the collection routes carry no
      `HiveLog - ` prefix and the five builders render
      `hivelog-list-heading` (with the add action) but no
      `hivelog-list-heading__title`. phpcs clean.

## Implementation notes
- Key files: `hivelog.routing.yml`, `src/ApiaryListBuilder.php`,
  `src/QueenListBuilder.php`, `src/InventoryItemListBuilder.php`,
  `src/InventoryPurchaseListBuilder.php`, `src/ProductListBuilder.php`,
  `tests/src/Kernel/ListCollectionTitleTest.php`.
- `.hivelog-list-heading` stays `display:flex; justify-content:
  space-between` with `.hivelog-list-heading__action { margin-left:
  auto }`, so a title-less heading still parks the "Add" button at the
  right. No CSS change.
- The plain `EntityListBuilder` collections (hive, inspection,
  calendar-action, the two action logs) have no custom heading at all —
  they only need the routing change.
- `HivelogBreadcrumbBuilder` builds its own "Apiaries" crumb text and is
  unaffected.

## Related
- Follows:: [[0061-beeswax-hivelog-skin]] (surfaced the duplication)
- Decisions:: [[0057-dashboard-information-architecture]]
- Commits::
