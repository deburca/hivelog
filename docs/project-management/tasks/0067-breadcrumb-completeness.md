---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: navigation
created: 2026-09-08
branch: feature/0067-breadcrumb-completeness
release:
depends-on:
blocked-by:
---
# Task: Complete breadcrumb hierarchy on every HiveLog page

## Context
`/hivelog/product/1` showed `Home › Honey` — no `HiveLog`, no ancestor,
no `Products`. `HivelogBreadcrumbBuilder::applies()` matched an explicit
route-name-prefix list that omitted `entity.inventory_item.`,
`entity.inventory_purchase.`, `entity.product.`,
`entity.calendar_action_item_requirement.`,
`entity.calendar_action_product_yield.` and the
`layout_builder.overrides.*` routes for hivelog entities — so those pages
fell through to whichever lower-priority builder ran (core / menu), which
produced a stub trail. `build()` also had no ancestor blocks for those
entities.

## Acceptance criteria
- [x] **`applies()` is path-based.** Any route whose path is `/hivelog`
      or under `/hivelog/` is handled (module entity routes, `hivelog.*`
      controllers, and `layout_builder.overrides.<entity>.*`), minus the
      `$non_page_routes` exclusion list. No more per-route-name allow
      list to drift.
- [x] **`build()` covers every entity type.** New blocks for
      `product` / `inventory_item` / `inventory_purchase` (apiary-direct,
      `entity.<type>.canonical` crumb) and for
      `calendar_action_item_requirement` / `calendar_action_product_yield`
      (thread `Apiary → Calendar action`, `<nolink>` terminal — no
      canonical page). The `calendar_action` block guard is broadened so
      the requirement / yield "add" forms (nested under a calendar
      action) also get the action crumb, while the hive / apiary
      action-log "add" routes still thread via hive / apiary.
- [x] **Collections + site-wide add forms.** `$leaf_pages` gains the
      inventory-item / -purchase / product collections. A new block
      routes `entity.<type>.add_form` to `Home › HiveLog › <Plural>`.
- [x] Layout Builder override pages (`/hivelog/<entity>/{id}/layout`)
      thread the full entity trail (their entity param drives the
      existing blocks once `applies()` matches).
- [x] Graceful with orphans — a deleted apiary / unassigned queen just
      shortens the trail, no fatal.
- [x] Tests: `HivelogBreadcrumbBuilderTest` +14 (105 total) —
      product / inventory canonical + collection + add-form, requirement
      / yield edit, a Layout Builder route, the requirement "add" form.
      `createRouteMatch()` now mocks `getRouteObject()` so the
      path-based `applies()` is exercised. phpcs clean.
- [x] `AGENTS.md` breadcrumb section rewritten for the path-based
      `applies()` and the `build()` shape.

## Implementation notes
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `AGENTS.md`.
- Verified on the ddev site across product / inventory-item /
  inventory-purchase (canonical / edit / delete / layout / add / list)
  and the requirement / yield edit pages.
- Supersedes task 0064's compromise (inventory / product collections
  "left on the menu breadcrumb") — they are now first-class here, so
  their canonical pages get real trails too.

## Related
- Follows:: [[0064-consistent-list-page-breadcrumbs]]
- Decisions:: [[0013-breadcrumb-policy]] (this extends its coverage), [[0057-dashboard-information-architecture]]
- Commits::
