---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0119-single-source-navigation-registry
release:
depends-on:
blocked-by:
---
# Task: One source of truth for the navigation destinations

## Context
From the navigation and breadcrumb review of 2026-09-23. The list of
top-level HiveLog destinations is maintained by hand in three places,
each with a submodule extension of its own:

| Surface | Core | Submodules |
|---|---|---|
| Main-menu links | `hivelog.links.menu.yml` (8) | `collective` / `nexus` / `nanoprobe` `.links.menu.yml` (1 each) |
| In-app nav strip | `HivelogAppNavBuilder::builtInItems()` (8) | `hook_hivelog_app_nav_items()` (1 each) |
| Breadcrumb collection names | `$collections` map in `HivelogBreadcrumbBuilder` | same map (submodule types hard-coded in core) |

Titles and weights are also repeated in each route's `_title` and each
entity type's `label_collection`. `HivelogAppNavBuilder`'s docblock
says the copies are "kept in sync deliberately, not derived"
([[0105-submodule-navigation-menu-links]]). They have already drifted:
`HiveInspection`'s `label_collection` is "Hive Inspections", while the
route, menu, nav strip and breadcrumb all say "Inspections".

[[0105-submodule-navigation-menu-links]] kept the menu links on purpose,
as a best-effort integration for themes and toolbars that render menus.
This task keeps them but stops maintaining them separately.

## Acceptance criteria
- [ ] The app-nav item set (core built-ins plus
      `hook_hivelog_app_nav_items()`) is the single source of truth.
- [ ] Main-menu links are **derived** from it: `hivelog.links.menu.yml`
      keeps only the `hivelog.admin` parent plus one derivative entry
      (`deriver:` class) that emits one child link per nav item, same
      title, route and weight.
- [ ] `collective.links.menu.yml`, `nexus.links.menu.yml` and
      `nanoprobe.links.menu.yml` deleted. Each submodule contributes
      through its existing `hook_hivelog_app_nav_items()` only.
- [ ] Existing menu link IDs are preserved or migrated, so a site that
      customised a link's weight or enabled state in the menu UI doesn't
      end up with an orphaned override. Check
      `menu_tree` / `menu_link_content` behaviour for static →
      derivative ID changes and document the outcome.
- [ ] Breadcrumb collection crumbs read their label from one place: the
      entity type's `label_collection` (via the entity type manager),
      the route title, or the nav registry. No separate hand-maintained
      `$collections` label list. (Coordinate with
      [[0116-breadcrumb-builder-parent-map-refactor]] if both are in
      flight.)
- [ ] `HiveInspection::label_collection` changed to "Inspections" so
      every surface agrees (no update hook; not field storage).
- [ ] The existing `AppNavItemsTest` kernel tests in `collective`,
      `nanoprobe` and `nexus` still pass. Add a kernel
      test asserting the derived menu links match the nav items one to
      one.
- [ ] AGENTS.md: "Services" lists all three registered services
      (`hivelog.breadcrumb`, `hivelog.app_nav_builder`,
      `hivelog.stat_tile_builder`), not "only one service". Add a
      short "In-app navigation" subsection describing the nav strip,
      the hook, and the derived menu links.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- A menu-link deriver runs during menu-link discovery (cache rebuild,
  module install / uninstall), not per request.
  Calling `invokeAll('hivelog_app_nav_items')` from it is fine, but the
  items it returns hold `Url` objects; the deriver needs route names
  and parameters instead. Consider having the hook return route names
  (and letting `HivelogAppNavBuilder` build `Url`s), to keep the item
  shape serialisable.
- Key files: `src/HivelogAppNavBuilder.php`, `hivelog.api.php`,
  `hivelog.links.menu.yml`, a new
  `src/Plugin/Derivative/HivelogMenuLinks.php` (or similar), the three
  submodule `.module` / `.links.menu.yml` files,
  `src/Entity/HiveInspection.php`, AGENTS.md.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0099-submodule-canonical-page-panel-hook]] (the hook
  pattern `hook_hivelog_app_nav_items()` follows)
- Tasks:: [[0105-submodule-navigation-menu-links]],
  [[0120-app-nav-active-state-and-grouping]] (builds on this),
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
