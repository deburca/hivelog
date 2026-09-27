---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `HivelogMenuLinks::getDerivativeDefinitions()`: an item with a
      `parent` key that resolves to another *derivable* item (routed,
      present in `getAllItems()`) gets `'parent' => 'hivelog.nav_item:' . $parent_key`
      instead of `hivelog.links.menu.yml`'s static `parent: hivelog.admin`
      default; an item with no `parent`, or whose `parent` doesn't
      resolve, keeps parenting under `hivelog.admin` exactly as today
      (same fallback reasoning as [[0148-two-tier-app-nav-strip-rendering]]'s
      "resolves to nothing → top level").
- [ ] `hivelog.links.menu.yml`'s own `hivelog.nav_item` entry keeps its
      `parent: hivelog.admin` — that's still correct as the *default*
      the deriver overrides per-item, not removed.
- [ ] **No update hook.** Confirm explicitly (per ADR-0104's own
      reasoning) that no derived plugin ID changes shape — only the
      `parent` value inside an unchanged-ID derivative definition — so
      `core.menu.static_menu_link_overrides` rows keyed by these plugin
      IDs on an existing site are untouched. If this turns out wrong
      once implemented, this task's scope grows to include a
      `hivelog_update_NNNNN` re-key (see `hivelog_update_10029` for the
      precedent) and the acceptance criteria above must be revised
      before merging.
- [ ] Kernel test asserting the derived definitions: a `parent: 'apiaries'`
      item's derivative carries `parent === 'hivelog.nav_item:apiaries'`;
      a top-level item's derivative still carries `parent === 'hivelog.admin'`.
- [ ] Manual check on `cms2`: Structure › Menus › Main navigation (or
      the site's own menu-admin UI) shows the expected two-level tree
      under "HiveLog", and the front-end main menu (wherever the theme
      renders it) still works if the theme's menu block renders nested
      levels — note in this task's own Implementation notes if the
      site's theme does *not* render a menu depth beyond one level (the
      same caveat AGENTS.md already documents for the top-level "Add"
      action), since that's a pre-existing theme limitation, not a bug
      in this task.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Plugin/Derivative/HivelogMenuLinks.php`, its test
  file, `hivelog.links.menu.yml` (comment update only, likely no
  functional change needed there).
- No entity schema change → **no update hook required** (see
  acceptance criteria above for the condition under which that
  changes).

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0119-single-source-navigation-registry]]
- Commits::
