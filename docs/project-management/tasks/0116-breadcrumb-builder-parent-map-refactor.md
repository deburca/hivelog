---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0116-breadcrumb-builder-parent-map-refactor
release:
depends-on:
blocked-by:
---
# Task: Refactor `HivelogBreadcrumbBuilder` around a single parent map

## Context
From the navigation and breadcrumb review of 2026-09-23 (see
[[breadcrumb-consistency]] Key findings). `build()` in
`src/Breadcrumb/HivelogBreadcrumbBuilder.php` (~370 lines) has grown
one hand-written ancestry block per entity type: apiary, hive,
hive_inspection, queen, queen_observation, calendar_action,
hive_action_log, apiary_action_log, the inventory_item /
inventory_purchase / product loop, sensor_device, ai_provider_config,
api_client, and the requirement / yield loop. About 10 of these walk
`->get('<parent>')->entity` up to the apiary in near-identical code.
Every new entity type adds another copy, which is exactly the drift
[[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]] had to
catch after the fact.

This is a **pure refactor with no behaviour change**. The existing unit
suite is the guard. Doing it first makes
[[0117-breadcrumb-terminal-crumb-on-form-pages]] a single generic rule
instead of another per-route map.

## Acceptance criteria
- [ ] One declarative parent map replaces the per-type ancestry blocks:
      entity type ID → parent reference field (`hive` → `apiary`,
      `hive_inspection` → `hive`, `queen` → `hive`,
      `queen_observation` → `queen`, `calendar_action` → `apiary`,
      `hive_action_log` → `hive`, `apiary_action_log` → `apiary`,
      `inventory_item` / `inventory_purchase` / `product` → `apiary`,
      `calendar_action_item_requirement` /
      `calendar_action_product_yield` → `calendar_action`).
- [ ] One recursive helper adds the ancestors and then the entity. It
      adds a cacheable dependency for every entity it touches, links to
      `entity.<type>.canonical` when the entity has a `canonical` link
      template, and falls back to `<nolink>` otherwise (today's
      requirement / yield behaviour). A missing parent (deleted apiary,
      unassigned queen) still just shortens the trail.
- [ ] Top-level entities that thread through their own collection
      (`sensor_device`, `ai_provider_config`, `api_client`) are a
      second declarative list, not three hand-written blocks.
- [ ] The "subject" of a route (which route parameter the trail is built
      from) is chosen by one explicit rule. That rule preserves today's
      special case: the hive / apiary action-log **add** routes carry
      `calendar_action` but thread via `hive` / `apiary`.
- [ ] The named sub-page terminal maps (`$apiary_page_crumbs`,
      `$hive_page_crumbs`, `$sensor_device_page_crumbs`, and the
      Regenerate Token case) collapse into one route → label map.
- [ ] The injected but unused `$entityTypeManager` is either removed
      (and dropped from `hivelog.services.yml`) or used, e.g. to read
      each type's `label_collection`. Either way, no dead dependency.
- [ ] Stale comments fixed: the "collections that are not routed
      through this builder" note in the `$collections` docblock (none
      are, since task [[0067-breadcrumb-completeness]]), and the "on
      edit/delete pages it is a navigable ancestor link" note on the
      apiary block (false under the real themes; see
      [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]).
- [ ] `HivelogBreadcrumbBuilderTest` passes **unchanged in its expected
      trails**. Mocks may need `getEntityTypeId()` /
      `hasLinkTemplate()` stubs added; only the mock setup changes.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `hivelog.services.yml` (only if the dependency is dropped).
- The submodule entity types (`sensor_device`, `api_client`,
  `ai_provider_config`) live in modules `hivelog` does not depend on.
  Keep them as plain string IDs in the map, as today; do not add
  class references that would need those modules installed.
- Target size: roughly 150 lines for `build()` plus helpers, down from
  ~370. Not a hard requirement; readability wins over line count.
- No update hook needed (no schema change).

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Tasks:: [[0117-breadcrumb-terminal-crumb-on-form-pages]] (builds on
  this), [[0122-top-level-entity-breadcrumb-threading]]
- Commits::
