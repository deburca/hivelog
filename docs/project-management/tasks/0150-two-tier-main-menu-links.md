---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: routing
created: 2026-09-27
branch: feature/0150-two-tier-main-menu-links
release:
depends-on: ["[[0147-app-nav-registry-parent-key]]"]
blocked-by:
---
# Task: Two-tier main-menu links

## Context
`HivelogMenuLinks` (task 0119) derives one main-menu link per
`getAllItems()` entry, every one parented under the single hand-written
`hivelog.admin` link — a flat tree regardless of the nav strip's own
shape. [[0104-two-tier-in-app-navigation]] mirrors the strip's two
tiers here too: a secondary item's derived link should parent under its
own primary item's derived plugin ID, not under `hivelog.admin`
directly. Independent of [[0148-two-tier-app-nav-strip-rendering]]/
[[0149-two-tier-app-nav-css-and-mobile-behaviour]] — only needs the
`parent` key from [[0147-app-nav-registry-parent-key]] to exist, so it
can be implemented in either order relative to those two.

## Acceptance criteria
- [x] `HivelogMenuLinks::getDerivativeDefinitions()`: an item with a
      `parent` key that resolves to another *derivable* item (routed,
      present in `getAllItems()`) gets `'parent' => 'hivelog.nav_item:' . $parent_key`
      instead of `hivelog.links.menu.yml`'s static `parent: hivelog.admin`
      default; an item with no `parent`, or whose `parent` doesn't
      resolve, keeps parenting under `hivelog.admin` exactly as today
      (same fallback reasoning as [[0148-two-tier-app-nav-strip-rendering]]'s
      "resolves to nothing → top level").
- [x] `hivelog.links.menu.yml`'s own `hivelog.nav_item` entry keeps its
      `parent: hivelog.admin` — that's still correct as the *default*
      the deriver overrides per-item, not removed.
- [x] **No update hook.** Confirmed: derived plugin IDs are unchanged
      (`hivelog.nav_item:hives` etc — only the `parent` *value* inside
      each definition changed), so `core.menu.static_menu_link_overrides`
      rows on an existing site stay correctly keyed.
- [x] Kernel test asserting the derived definitions: a `parent: 'apiaries'`
      item's derivative carries `parent === 'hivelog.nav_item:apiaries'`;
      a top-level item's derivative still carries `parent === 'hivelog.admin'`.
- [x] Manual check on `cms2`: confirmed the real, live menu tree via
      `\Drupal::menuTree()->load()` (data-level, not a screenshot — see
      below) — genuinely two levels: `hivelog.admin` → `apiaries` →
      {hives, inspections, queens, queen_observations, inventory_items,
      inventory_purchases, products}, and `hivelog.admin` → `setup` →
      {collective_api_clients, nanoprobe_sensor_devices,
      nexus_ai_provider_configs}. **`cms2`'s own theme (`quick_silver`)
      does not render menu depth beyond one level** — its front-end
      "Main navigation" block shows only the top-level `HiveLog` entry,
      no children at all, confirmed via the same page load this task
      already had open. Pre-existing theme limitation (AGENTS.md
      already documents the identical caveat for the top-level "Add"
      action), not a bug in this task — the underlying menu-link data
      is correct regardless of what any one theme's block chooses to
      render.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- **Design, unchanged from the plan:** `getDerivativeDefinitions()`
  now computes which keys will get a derivative *before* building any
  of them (`$derivable_keys`), so an item's own position in
  `getAllItems()`'s iteration order never matters — a child can be
  discovered before or after its parent with the same result. Each
  item's `parent` key is looked up against that set; a hit sets
  `'parent' => 'hivelog.nav_item:' . $parent_key` in the per-item
  definition array, a miss leaves `'parent'` unset so
  `hivelog.links.menu.yml`'s own default (`hivelog.admin`) applies via
  the existing `+ $base_plugin_definition` merge — no separate
  fallback branch needed.
- **Closed a real gap in task 0148's own coverage while building this
  task's test fixture.** 0148's acceptance criteria named "the
  fallback-to-top-level case for an unresolvable `parent`" as required
  test coverage, but no fixture existed to exercise it at the time and
  it was marked done anyway. Added a second test-only item to
  `hivelog_app_nav_test` (`parent: 'nonexistent_hub'`, an outright
  typo case) and a new `HivelogAppNavBuilderTest::testUnresolvableParentFallsBackToTopLevel()`
  alongside this task's own equivalent test for the menu deriver
  (`testUnresolvableParentFallsBackToHivelogAdmin()`) — both pass.
- **The full multi-path suite caught 4 real regressions a targeted
  test-file run missed** — a live example of exactly the gap
  [[0147-app-nav-registry-parent-key]]'s own notes describe. Four
  *pre-existing* tests hard-coded "every derived link's parent is
  `hivelog.admin`", written before any item had a different one:
  `ApiaryTest::testGlobalCollectionRoutesAndMenuLinksExist()` (asserted
  it for `hives`/`inspections`/`queens`/`queen_observations`) and each
  submodule's own `AppNavItemsTest::test*ItemGetsDerivedMenuLink()`
  (`collective`, `nanoprobe`, `nexus`). All four updated to the correct
  new value (`hivelog.nav_item:apiaries` / `hivelog.nav_item:setup`)
  once the real full-suite run surfaced them — genuine required
  updates, not a bug in this task's own code.
- Key files: `src/Plugin/Derivative/HivelogMenuLinks.php`,
  `tests/src/Kernel/HivelogMenuLinksTest.php` (rewrote the
  now-incorrect "every link's parent is hivelog.admin" assertion,
  added 3 new tests), `hivelog.links.menu.yml` (comment only, no
  functional change), `tests/modules/hivelog_app_nav_test/hivelog_app_nav_test.module`
  (second fixture item), `tests/src/Kernel/HivelogAppNavBuilderTest.php`
  (the 0148 gap-closing test), `tests/src/Kernel/ApiaryTest.php` and
  each submodule's `tests/src/Kernel/AppNavItemsTest.php` (the 4
  regression fixes above).
- No entity schema change → **no update hook required**.
- phpcs clean; phpstan clean (module-wide, no baseline changes). Full
  kernel/unit/functional suite against `cms2`, core and every
  submodule: 1,076 tests, 0 failures (after the 4 fixes above).

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0119-single-source-navigation-registry]]
- Commits::
