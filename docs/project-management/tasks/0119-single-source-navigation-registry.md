---
type: task
tags: [hivelog/task]
status: review
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
- [x] The app-nav item set (core built-ins plus
      `hook_hivelog_app_nav_items()`) is the single source of truth.
- [x] Main-menu links are **derived** from it: `hivelog.links.menu.yml`
      keeps only the `hivelog.admin` parent plus one derivative entry
      (`deriver:` class) that emits one child link per nav item, same
      title, route and weight.
- [x] `collective.links.menu.yml`, `nexus.links.menu.yml` and
      `nanoprobe.links.menu.yml` deleted. Each submodule contributes
      through its existing `hook_hivelog_app_nav_items()` only.
- [x] Existing menu link IDs are preserved or migrated, so a site that
      customised a link's weight or enabled state in the menu UI doesn't
      end up with an orphaned override. Check
      `menu_tree` / `menu_link_content` behaviour for static →
      derivative ID changes and document the outcome.
- [x] Breadcrumb collection crumbs read their label from one place: the
      entity type's `label_collection` (via the entity type manager),
      the route title, or the nav registry. No separate hand-maintained
      `$collections` label list. (Coordinate with
      [[0116-breadcrumb-builder-parent-map-refactor]] if both are in
      flight.)
- [x] `HiveInspection::label_collection` changed to "Inspections" so
      every surface agrees (no update hook; not field storage).
- [x] The existing `AppNavItemsTest` kernel tests in `collective`,
      `nanoprobe` and `nexus` still pass. Add a kernel
      test asserting the derived menu links match the nav items one to
      one.
- [x] AGENTS.md: "Services" lists all three registered services
      (`hivelog.breadcrumb`, `hivelog.app_nav_builder`,
      `hivelog.stat_tile_builder`), not "only one service". Add a
      short "In-app navigation" subsection describing the nav strip,
      the hook, and the derived menu links.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**`HivelogAppNavBuilder::getAllItems()` is the single registry**, new in
this task: `build()` (the nav strip) now just filters its output by
`Url::access()`; the new `HivelogMenuLinks` deriver
(`src/Plugin/Derivative/`) reads the same unfiltered list to emit one
`hivelog.nav_item:<key>` menu link per item, parented under the single
remaining hand-written `hivelog.admin` entry in `hivelog.links.menu.yml`.
`collective.links.menu.yml` / `nexus.links.menu.yml` /
`nanoprobe.links.menu.yml` are deleted outright — each submodule's
existing `hook_hivelog_app_nav_items()` implementation is now sufficient
on its own for both surfaces.

**The nav-item hook contract did not need to change.** The task's own
implementation-notes hint suggested having the hook return route
names/parameters instead of `Url` objects, since a deriver "needs route
names and parameters instead." In practice every implementation in this
codebase (core's 8 built-ins and all three submodules' hooks) builds its
`url` via a plain `Url::fromRoute($route_name)` with no parameters, and
`Url::getRouteName()`/`getRouteParameters()` read exactly that back out —
so the deriver just calls those instead of changing what implementations
return. `Url::isRouted()` guards the one case this can't handle (an
external `Url::fromUri()`, which has no route to derive a link from) by
skipping that item rather than throwing; documented on the hook itself in
`hivelog.api.php` so a future implementation knows the constraint.

**Menu link ID migration, not preservation** — the AC explicitly allows
either. A derived plugin ID always carries a `:` (`hivelog.nav_item:hives`
etc.), which is structurally impossible to make identical to the old bare
static ID (`hivelog.hives`), so literal preservation isn't achievable
when converting static → derivative. Per-site menu-UI customisations
(weight/enabled/expanded) are not tied to `menu_link_content` at all —
those persist independently as page content and are unaffected regardless.
They *are* tied to `core.menu.static_menu_link_overrides`, a single
config object keyed by plugin ID (`StaticMenuLinkOverrides`), so
`hivelog_update_10029` re-keys any of the 11 old static IDs it finds
there onto the corresponding new derived ID via `loadOverride()`/
`saveOverride()`/`deleteOverride()`, rather than losing the customisation.
Verified live on `cms2`: seeded a `weight: 99` override on the old
`hivelog.hives` ID, ran the hook directly, confirmed it now lives on
`hivelog.nav_item:hives` and the old key is gone; cleaned up afterward.
`cms2` itself had no real overrides to migrate (confirmed via `drush
updb`).

**Breadcrumb collection labels now read `label_collection` off the
entity type manager**, reintroducing the `$entityTypeManager` dependency
task 0116 deliberately dropped — that task's own notes say deriving
labels was "considered and rejected" specifically to keep 0116 a
behaviour-preserving pure refactor; this task is exactly the follow-up
that makes it safe, by first fixing the one label that had drifted
(`HiveInspection::label_collection`: "Hive Inspections" → "Inspections",
matching the route's own `_title` and every other surface, which
already agreed). The list of *which* entity types have a collection
route at all — 14 of hivelog's ~20 types; `calendar_action_item_requirement`/
`calendar_action_product_yield` are notably excluded, edit/delete only —
still has to be named explicitly somewhere (not mechanically derivable
from the existing `PARENT_FIELD`/`COLLECTION_THREADED_TYPES` constants),
so it's a new `HivelogEntityHierarchy::COLLECTION_TYPES` constant
alongside those two, consistent with that class's existing role as the
one declarative registry of hierarchy facts. `hasDefinition()` guards
each lookup so a route for a not-installed submodule's type is never
even attempted (a route that isn't registered can't be the current one
either, so nothing is ever missing a label it needed).

**Verification.** phpcs clean (0 errors module-wide, `--warning-severity=0`);
phpstan clean module-wide with no baseline changes (the one new finding —
"Unsafe usage of new static()" in the deriver's `create()` — was avoided
by using `new self()` instead, matching the memory note that the baseline
is only for dropping fixed entries, never for waving through a new one).
Full kernel+unit suite: 715 tests, 12404 assertions, zero failures/errors
outside the 3 pre-existing, unrelated `DashboardTest` Functional
cache-redirect errors already documented in task 0116's own notes
(Functional is advisory per CI policy anyway). One pre-existing kernel
test, `ApiaryTest::testGlobalCollectionRoutesAndMenuLinksExist`, asserted
the old static menu link IDs directly and needed updating to the new
derived ones — caught by the full-suite run, not the initial targeted
one, which is why running the whole module's suite mattered here even
though the change looked narrowly scoped. Live-verified on `cms2`: the
full 11-item derived menu tree renders with correct titles/weights/
parents; the Inspections collection breadcrumb reads "Inspections", not
"Hive Inspections"; a submodule collection breadcrumb (Sensor Devices)
resolves its label correctly too.

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
