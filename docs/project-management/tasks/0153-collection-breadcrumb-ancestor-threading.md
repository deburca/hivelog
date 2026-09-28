---
type: task
tags: [hivelog/task]
status: done
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
- [x] New map in `HivelogBreadcrumbBuilder` — `COLLECTION_ANCESTOR_ROUTE`,
      keyed by collection route name → its own parent crumb's route
      name, exactly the ten rows ADR-0105's own table lists
      (`entity.hive.collection` → `entity.apiary.collection`, … through
      `entity.sensor_device.collection` → `hivelog.insights`).
- [x] `build()` walks this map from the current route to its root
      (terminates naturally — neither `entity.apiary.collection` nor
      `hivelog.insights` is a key), threading one crumb per ancestor in
      root-to-leaf order, each linking to that ancestor's own route,
      before the current route's own terminal crumb — same
      "declarative map, walk to root" shape `addAncestryLinks()` already
      uses for per-instance pages (new `addCollectionAncestryLinks()`).
- [x] Terminal-label overrides for the five steps whose breadcrumb text
      differs from `label_collection` (task 0105's own distinction —
      these are display-only, `label_collection` itself is unchanged):
      `entity.queen_observation.collection` → "Observations",
      `entity.inventory_item.collection` → "Inventory",
      `entity.inventory_purchase.collection` → "Purchases",
      `entity.ai_provider_config.collection` → "AI Providers",
      `entity.sensor_device.collection` → "Sensors" (new
      `collectionCrumbLabel()`). Every other threaded route (Hives,
      Inspections, Queens, API Clients, Products) uses its existing
      `label_collection` unchanged — no override needed.
- [x] `calendar_action`/`hive_action_log`/`apiary_action_log` collection
      breadcrumbs, and the combined financial report, are **not**
      touched — confirmed via `testBuildLeafPageTerminalCrumb()`,
      which still exercises exactly those 5 routes (down from 15;
      the other 10 moved to the new threaded test).
- [x] `HivelogBreadcrumbBuilderTest.php`: `leafPageProvider()` lost the
      10 threaded routes; `threadedCollectionProvider()` +
      `testBuildThreadedCollectionAncestry()` assert the full chain for
      all 10 — every ancestor crumb's text **and** route, not just the
      terminal one.
- [x] Live-verify on `cms2`: navigated to `/hivelog/inspections` in the
      browser — the underlying breadcrumb service confirmed via `drush`
      (see notes on why the browser check alone wasn't conclusive).
- [x] phpcs clean; phpstan clean (baseline `count: 51 → 52`, one
      already-known/tolerated false-positive pattern occurring once
      more because a new test exercises it — see notes).

## Implementation notes
- **A real consistency gap found and fixed, beyond the letter of the
  acceptance criteria above:** the site-wide "Add" form breadcrumb
  (`entity.<type>.add_form`) threads to its own collection using a
  *separate* code path in `build()` that never called the new
  ancestor/override methods — left as originally written, "Add
  Inventory Item" would have shown a flat "Inventory Items" crumb (no
  Apiaries ancestor, no "Inventory" shorthand) right next to an
  Inventory Items *collection* page now showing
  `Apiaries › Inventory`. Fixed by routing the add-form branch through
  `addCollectionAncestryLinks()`/`collectionCrumbLabel()` too — an
  add-form page must never disagree with its own collection page about
  where that collection sits. `testBuildAddFormThreadsToCollection()`
  rewritten to assert variable-depth chains (6 of its 7 cases now
  thread somewhere; only the root-level `entity.apiary.add_form` case
  is unchanged).
- **Live verification needed a second pass to be conclusive.** The
  browser's own rendered breadcrumb on `/hivelog/inspections` showed
  only `Home › Hives › Inspections` — at first glance looking like
  "Apiaries" and "HiveLog" were missing. Inspecting the actual DOM
  showed why: `cms2`'s theme (`quick_silver`) collapses a long
  breadcrumb's middle crumbs behind a "…" (ellipsis) icon, a
  responsive/compact breadcrumb UI pattern — a theme rendering choice,
  not evidence of a wrong trail. Confirmed the real breadcrumb
  *service* output directly via `drush php:eval` instead (each route
  checked in its own fresh process — the first attempt reused one PHP
  process across several `build()` calls and got a stale cached result
  for every route after the first; not a real bug, a test-script
  mistake): every one of the 5 spot-checked routes matched the
  original request's own spec exactly, e.g.
  `entity.queen_observation.collection` → `Home > HiveLog > Apiaries >
  Hives > Queens > Observations`.
- **phpstan baseline regenerated, diff-confirmed**: the one new test's
  extra `$route_match->method(...)` mock-builder call (PHPUnit's own
  API, not a real `RouteMatchInterface` method) hit an
  already-baselined false-positive pattern one more time (51 → 52
  occurrences) — regenerated and diffed against the original to
  confirm only that one count line changed, per this repo's own
  ratchet convention.
- **Spot-checked a real hive inspection's own canonical page** via
  `drush` to confirm per-instance breadcrumbs are genuinely untouched:
  still `Home › HiveLog › Apiaries › Ravnholt Home › H-1 › Inspection
  of H-1 on 2026-09-07` — real entity names, not the new collection
  mechanism.
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `phpstan-baseline.neon`.
- No entity schema change → **no update hook required**.
- `HivelogEntityHierarchy::PARENT_FIELD` and `resolveSubject()` were
  not touched, as scoped — per-instance canonical/edit/delete
  breadcrumbs render identically before and after this task.
- Full kernel/unit/functional suite against `cms2`, core and every
  submodule: **1,076 tests, 0 failures**.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
- Tasks:: [[0152-rename-setup-to-insights]]
- Commits::
