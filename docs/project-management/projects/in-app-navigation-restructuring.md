---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-27
---
# Project: In-App Navigation Restructuring

## Goal
Turn the in-app nav strip and its mirrored main-menu tree from one flat
list of 11+ destinations into a two-tier structure: a small set of
primary destinations (Dashboard, Apiaries, Setup) at the first level,
with every other destination nested under whichever primary item is its
logical parent. Beekeepers get a nav strip that stays legible as the
module keeps adding entity types and submodules; the primary tier stays
a fixed, small size regardless of how many secondary destinations exist
underneath it.

## Scope
- In scope: `HivelogAppNavBuilder` (registry + rendering), the
  `hook_hivelog_app_nav_items()` contract and every implementation
  (`collective`, `nanoprobe`, `nexus`), the `HivelogMenuLinks` deriver
  and `hivelog.links.menu.yml`, `css/hivelog.app-nav.css`, a new Setup
  landing page/route (the "Setup" primary item has no existing page of
  its own to point at), and the breadcrumb/test coverage each of those
  changes touches.
- Out of scope: any new top-level destination beyond Dashboard /
  Apiaries / Setup (e.g. a standalone "Insights" page — `HiveInsight`
  has no UI of its own today per ADR-0103's own inventory, and inventing
  one is a separate feature, not a nav restructuring); changing which
  entities exist or their permissions; the breadcrumb trail itself
  (unaffected — it walks the entity hierarchy, not the nav registry).

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

## Open questions
- None currently; see [[0104-two-tier-in-app-navigation]]'s own Open
  questions for the design-level ones already resolved.

## Related decisions
- [[0104-two-tier-in-app-navigation]]
