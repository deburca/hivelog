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
primary destinations (Dashboard, Apiaries, Insights) at the first
level, with every other destination nested under whichever primary
item is its logical parent. Beekeepers get a nav strip that stays
legible as the module keeps adding entity types and submodules; the
primary tier stays a fixed, small size regardless of how many
secondary destinations exist underneath it. Extended (ADR-0105) to
carry that same hierarchy into the breadcrumb trail's own collection
pages, and to rename the "Setup" primary item to "Insights".

## Scope
- In scope: `HivelogAppNavBuilder` (registry + rendering), the
  `hook_hivelog_app_nav_items()` contract and every implementation
  (`collective`, `nanoprobe`, `nexus`), the `HivelogMenuLinks` deriver
  and `hivelog.links.menu.yml`, `css/hivelog.app-nav.css`, the Setup/
  Insights landing page/route (this primary item has no existing
  entity collection of its own to point at), and — since ADR-0105 —
  `HivelogBreadcrumbBuilder`'s collection-page breadcrumbs (a new,
  separate ancestor-threading mechanism; per-instance canonical/edit/
  delete breadcrumbs are unaffected, see ADR-0105's own Decision).
- Out of scope: any new top-level destination beyond Dashboard /
  Apiaries / Insights, or a genuinely new aggregated-insights *feature*
  (`HiveInsight` still has no UI of its own today per ADR-0103's own
  inventory — "Insights" is a rename of the existing Setup page, not a
  new destination); changing which entities exist or their
  permissions; `HivelogEntityHierarchy::PARENT_FIELD` (the per-instance
  ancestor map canonical/edit/delete pages use — untouched by
  ADR-0105's collection-only mechanism).

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

## Open questions
- None currently; see [[0104-two-tier-in-app-navigation]]'s and
  [[0105-collection-breadcrumb-ancestry-and-insights-rename]]'s own
  Open questions for the design-level ones already resolved.

## Related decisions
- [[0104-two-tier-in-app-navigation]]
- [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
