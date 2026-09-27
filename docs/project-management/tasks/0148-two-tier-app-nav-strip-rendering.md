---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `build()` partitions `getAllItems()` (plus the synthetic Dashboard
      entry) into primary items and, for each, its accessible
      `parent`-matching children — an item whose declared `parent`
      resolves to nothing (typo, or the parent itself inaccessible)
      falls back to rendering at the top level rather than disappearing
      silently.
- [ ] Each primary item's render array is a wrapper containing its own
      link plus (if it has ≥1 accessible child) a nested list of child
      links — child links keep the exact same `#type => 'link'` shape
      and `hivelog-app-nav__link` base class they have today, so
      existing CSS for the link itself still applies; only the
      containers around them are new.
- [ ] `resolveActiveItem()`'s existing two rules are unchanged for
      *which* item matches; `build()` additionally marks a primary item
      `has-active-child` when the active item is one of its children
      (the child itself still gets `is-active` exactly as today).
- [ ] The existing group-separator divider (`hivelog-app-nav__separator`)
      renders **within** a hub's own children (between its `records`
      and `inventory`-sourced children, for Apiaries) rather than across
      the whole strip — since the top level now only has 2–3 items,
      inserting it there too would be visual noise for no reason.
- [ ] Cache metadata (`url.path`, `user.permissions`) unchanged — the
      output is still derived from exactly the same route/permission
      inputs, just laid out differently.
- [ ] Existing `HivelogAppNavBuilderTest`/equivalent rewritten for the
      new nested shape (not just patched): primary items, each one's
      child list, `is-active` on the right leaf, `has-active-child` on
      the right parent, and the fallback-to-top-level case for an
      unresolvable `parent`.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HivelogAppNavBuilder.php`, its test file.
- No entity schema change → **no update hook required**.
- `groupPosition()`/the existing weight comparator gets reused twice —
  once to order the primary tier, once per hub to order its own
  children — rather than inventing a second sorting concept; see
  ADR-0104's Rendering section.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
