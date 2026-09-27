---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: routing
created: 2026-09-27
branch: feature/0153-collection-breadcrumb-ancestor-threading
release:
depends-on: ["[[0152-rename-setup-to-insights]]"]
blocked-by:
---
# Task: Collection-page breadcrumb ancestor threading

## Context
[[0105-collection-breadcrumb-ancestry-and-insights-rename]]'s core
mechanism: ten collection pages currently get a flat
`Home › HiveLog › <Plural>` terminal crumb (`$leaf_pages` in
`HivelogBreadcrumbBuilder::build()`); each should instead thread
through its conceptual parent collection(s) first, per the ADR's own
table. A new, separate declarative map — not an extension of
`HivelogEntityHierarchy::PARENT_FIELD`, which stays untouched and
keeps governing per-instance canonical/edit/delete breadcrumbs exactly
as it does today. Depends on [[0152-rename-setup-to-insights]] only
for the `hivelog.insights` route name to exist as a valid parent-crumb
target.

## Acceptance criteria
- [ ] New map in `HivelogBreadcrumbBuilder` (or a small addition to
      `HivelogEntityHierarchy` if that reads better once written —
      judgment call for whoever implements, the ADR doesn't mandate
      which class), keyed by collection route name → its own parent
      crumb's route name, exactly the ten rows ADR-0105's own table
      lists (`entity.hive.collection` → `entity.apiary.collection`,
      … through `entity.sensor_device.collection` → `hivelog.insights`).
- [ ] `build()` walks this map from the current route to its root
      (terminates naturally — neither `entity.apiary.collection` nor
      `hivelog.insights` is a key), threading one crumb per ancestor in
      root-to-leaf order, each linking to that ancestor's own route,
      before the current route's own terminal crumb — same
      "declarative map, walk to root" shape `addAncestryLinks()` already
      uses for per-instance pages.
- [ ] Terminal-label overrides for the five steps whose breadcrumb text
      differs from `label_collection` (task 0105's own distinction —
      these are display-only, `label_collection` itself is unchanged):
      `entity.queen_observation.collection` → "Observations",
      `entity.inventory_item.collection` → "Inventory",
      `entity.inventory_purchase.collection` → "Purchases",
      `entity.ai_provider_config.collection` → "AI Providers",
      `entity.sensor_device.collection` → "Sensors". Every other
      threaded route (Hives, Inspections, Queens, API Clients,
      Products) uses its existing `label_collection` unchanged — no
      override needed.
- [ ] `calendar_action`/`hive_action_log`/`apiary_action_log` collection
      breadcrumbs, and the combined financial report, are **not**
      touched — confirm they still produce the exact same flat
      3-link trail as before this task.
- [ ] `HivelogBreadcrumbBuilderTest.php`: `leafPageProvider()` loses the
      10 threaded routes (they get their own new, deeper-asserting test
      method/provider instead — full link count and each ancestor
      crumb's text **and** route asserted, not just the terminal one);
      the remaining flat entries (calendar actions, hive/apiary action
      logs, combined financial report, insights) keep using
      `testBuildLeafPageTerminalCrumb()` unchanged.
- [ ] Live-verify on `cms2`: navigate to `/hivelog/inspections` and
      `/hivelog/ai-provider-configs`, confirm the full breadcrumb chain
      renders with every ancestor crumb clickable.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.
- No entity schema change → **no update hook required**.
- `HivelogEntityHierarchy::PARENT_FIELD` and `resolveSubject()` are
  out of scope for this task entirely — don't touch them; this is a
  collection-route-only mechanism, and per-instance canonical/edit/
  delete breadcrumbs must render identically before and after this
  task (spot-check at least one, e.g. a real hive inspection's own
  canonical page, to confirm).

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
- Tasks:: [[0152-rename-setup-to-insights]]
- Commits::
