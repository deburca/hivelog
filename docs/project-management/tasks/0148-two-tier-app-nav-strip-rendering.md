---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: theme
created: 2026-09-27
branch: feature/0148-two-tier-app-nav-strip-rendering
release:
depends-on: ["[[0147-app-nav-registry-parent-key]]"]
blocked-by:
---
# Task: Render the nav strip as two tiers

## Context
[[0104-two-tier-in-app-navigation]]'s rendering decision: no new SDC
component, no JavaScript — `HivelogAppNavBuilder::build()` stays plain
render arrays, restructured to partition accessible items into primary
(no `parent`) and secondary (grouped by `parent`), nesting each hub's
children underneath it and cascading active-state to the parent. This
is the task that actually changes what markup ships; [[0149-two-tier-app-nav-css-and-mobile-behaviour]]
is the CSS that makes the new markup behave like a dropdown.

## Acceptance criteria
- [x] `build()` partitions `getAllItems()` (plus the synthetic Dashboard
      entry) into primary items and, for each, its accessible
      `parent`-matching children — an item whose declared `parent`
      resolves to nothing (typo, or the parent itself inaccessible)
      falls back to rendering at the top level rather than disappearing
      silently.
- [x] Each primary item's render array is a wrapper containing its own
      link plus (if it has ≥1 accessible child) a nested list of child
      links — child links keep the exact same `#type => 'link'` shape
      and `hivelog-app-nav__link` base class they have today, so
      existing CSS for the link itself still applies; only the
      containers around them are new.
- [x] `resolveActiveItem()`'s existing two rules are unchanged for
      *which* item matches; `build()` additionally marks a primary item
      `has-active-child` when the active item is one of its children
      (the child itself still gets `is-active` exactly as today).
- [x] The existing group-separator divider (`hivelog-app-nav__separator`)
      renders **within** a hub's own children (between its `records`
      and `inventory`-sourced children, for Apiaries) rather than across
      the whole strip — since the top level now only has 2–3 items,
      inserting it there too would be visual noise for no reason.
- [x] Cache metadata (`url.path`, `user.permissions`) unchanged — the
      output is still derived from exactly the same route/permission
      inputs, just laid out differently.
- [x] Existing `HivelogAppNavBuilderTest`/equivalent rewritten for the
      new nested shape (not just patched): primary items, each one's
      child list, `is-active` on the right leaf, `has-active-child` on
      the right parent, and the fallback-to-top-level case for an
      unresolvable `parent`.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- **Shape:** `build()` now returns, per top-level key, a
  `#type => 'container'` wrapper (`hivelog-app-nav__item`, plus
  `has-active-child` when applicable) with two sub-keys: `'link'` (the
  primary item's own link — same shape as before) and, only if it has
  ≥1 accessible child, `'submenu'` (`hivelog-app-nav__submenu`
  container of the hub's own children, same link shape again, with the
  group-separator moved inside). Extracted three small helpers reused
  by both tiers: `partitionByParent()`, `buildItemLink()` and
  `buildSubmenu()`; `findActiveParent()` derives `has-active-child`
  from the one `resolveActiveItem()` call already made (unchanged
  itself — it still resolves against the flat accessible set, since
  which item is "active" doesn't depend on tiering).
- **Fallback-to-top-level is a plain `isset()` check** in
  `partitionByParent()`: an item's `parent` only turns it into a child
  if that key is present in the *same accessible set* — covers both a
  typo (key never existed) and a valid-but-inaccessible-to-this-user
  parent with the same one-line check, no special-casing either.
- **Every test that read `$build['hives']` etc. directly needed
  rewriting**, not patching — the top-level shape changed
  fundamentally. Updated: `testAllBuiltInItemsAppearForAdministrator`
  (now checks `$build['apiaries']['submenu']`), `testItemsAreSortedByGroupThenWeight`
  (separator moved from top level into Apiaries' own submenu),
  `testActiveStateFor{CollectionRoute,CanonicalRoute,EditRoute}` (child
  link is under `['apiaries']['submenu']['hives']`; added
  `has-active-child` assertions), `testActiveStateForRouteWithNoSection`
  (recurses into both tiers now), plus one assertion each in
  `collective`/`nexus`/`nanoprobe`'s own `AppNavItemsTest.php` (their
  item is under `$build['setup']['submenu']` now, not top-level).
- Key files: `src/HivelogAppNavBuilder.php`,
  `tests/src/Kernel/HivelogAppNavBuilderTest.php`, one assertion each in
  `modules/{collective,nanoprobe,nexus}/tests/src/Kernel/AppNavItemsTest.php`.
- No entity schema change → **no update hook required**.
- `groupPosition()`/the existing weight comparator (`sortByGroupThenWeight()`,
  extracted in task 0147) gets reused twice — once to order the primary
  tier, once per hub to order its own children — rather than inventing
  a second sorting concept; see ADR-0104's Rendering section.
- phpcs clean; phpstan clean (module-wide, no baseline changes). Full
  kernel/unit/functional suite against `cms2`, core and every submodule:
  **1,072 tests, 0 failures** (using the corrected multi-path
  invocation — see [[0147-app-nav-registry-parent-key]]'s own notes on
  that gap).
- Live-verified on `cms2`: the flat "Dashboard Apiaries Hives
  Inspections…" strip is now genuinely two-tier in the DOM (confirmed
  via `document.querySelector` — `.hivelog-app-nav__item` wrappers,
  `.hivelog-app-nav__submenu` per hub); on `/hivelog/hives`, "Hives"
  carries `is-active`/`aria-current="page"` and "Apiaries" carries
  `has-active-child`, exactly as designed. No CSS exists yet (task
  0149), so everything renders always-visible/stacked for now — purely
  a markup change at this point.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
