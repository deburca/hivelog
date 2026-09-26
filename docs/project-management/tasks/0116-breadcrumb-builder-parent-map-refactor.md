---
type: task
tags: [hivelog/task]
status: done
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
- [x] One declarative parent map replaces the per-type ancestry blocks:
      entity type ID → parent reference field (`hive` → `apiary`,
      `hive_inspection` → `hive`, `queen` → `hive`,
      `queen_observation` → `queen`, `calendar_action` → `apiary`,
      `hive_action_log` → `hive`, `apiary_action_log` → `apiary`,
      `inventory_item` / `inventory_purchase` / `product` → `apiary`,
      `calendar_action_item_requirement` /
      `calendar_action_product_yield` → `calendar_action`).
- [x] One recursive helper adds the ancestors and then the entity. It
      adds a cacheable dependency for every entity it touches, links to
      `entity.<type>.canonical` when the entity has a `canonical` link
      template, and falls back to `<nolink>` otherwise (today's
      requirement / yield behaviour). A missing parent (deleted apiary,
      unassigned queen) still just shortens the trail.
- [x] Top-level entities that thread through their own collection
      (`sensor_device`, `ai_provider_config`, `api_client`) are a
      second declarative list, not three hand-written blocks.
- [x] The "subject" of a route (which route parameter the trail is built
      from) is chosen by one explicit rule. That rule preserves today's
      special case: the hive / apiary action-log **add** routes carry
      `calendar_action` but thread via `hive` / `apiary`.
- [x] The named sub-page terminal maps (`$apiary_page_crumbs`,
      `$hive_page_crumbs`, `$sensor_device_page_crumbs`, and the
      Regenerate Token case) collapse into one route → label map.
- [x] The injected but unused `$entityTypeManager` is either removed
      (and dropped from `hivelog.services.yml`) or used, e.g. to read
      each type's `label_collection`. Either way, no dead dependency.
- [x] Stale comments fixed: the "collections that are not routed
      through this builder" note in the `$collections` docblock (none
      are, since task [[0067-breadcrumb-completeness]]), and the "on
      edit/delete pages it is a navigable ancestor link" note on the
      apiary block (false under the real themes; see
      [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]).
- [x] `HivelogBreadcrumbBuilderTest` passes **unchanged in its expected
      trails**. Mocks may need `getEntityTypeId()` /
      `hasLinkTemplate()` stubs added; only the mock setup changes.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **The parent map + a "subject" resolver, not two separate rewrites.**
  `resolveSubject()` walks a fixed, ordered list of route-parameter
  names (`SUBJECT_PARAMS`) and returns the first that upcasts to a real
  entity — at most one ever does for any actual hivelog route. The
  documented exception (the hive/apiary action-log `add` routes, which
  also carry `{calendar_action}`) is guarded by an explicit route-name
  list (`CALENDAR_ACTION_NOT_SUBJECT_ROUTES`), not a "some other param
  is also present" heuristic — anchored to the routes the exception is
  actually about, matching the acceptance criterion's own wording.
- **`$entityTypeManager` removed**, not repurposed. Deriving the
  `$collections` labels from `getPluralLabel()` instead of the
  hardcoded strings was considered and rejected: it would risk a real
  wording difference from what the labels say today, which this task
  is explicitly not allowed to do (a pure refactor guarded by the
  existing test suite's exact expected text). Dropped from
  `hivelog.services.yml` too — the builder now takes no constructor
  arguments at all.
- **Terminal-crumb labels stay literal `t()` calls**, not a
  variable-keyed array — Drupal's coding standard requires translated
  strings to be statically extractable. `TERMINAL_CRUMB_PARAM` (route →
  which route parameter the terminal link uses) is the single
  declarative map the acceptance criterion asks for; a small
  `terminalCrumbLabel()` `match()` provides the label per route. Net
  effect is the same collapse from four separate maps
  (`$apiary_page_crumbs`, `$hive_page_crumbs`,
  `$sensor_device_page_crumbs`, the Regenerate Token special case) into
  one.
- **`ContentEntityInterface` mocks default every unconfigured method to
  a PHP-default return value** (PHPUnit's auto-stubbing), not a
  thrown error — `getEntityTypeId()` and `hasLinkTemplate()` were
  never stubbed before, since the old code never called either. Added
  both to all 13 mock-entity factory helper methods (one line each);
  the ~50 individual test methods' `willReturnMap()` calls needed no
  changes at all, since PHPUnit's `willReturnMap()` returns `NULL` for
  an unmatched key rather than throwing (verified directly before
  relying on it) — exactly what `getParameter()` returning "this
  route doesn't carry that param" should look like.
- **Result: 119 unit tests, 474 assertions, all green, zero behavioural
  drift** — the exact trails the pre-refactor code produced, for every
  route the suite covers. Full `hivelog` suite also green (647 tests,
  same 3 pre-existing unrelated `DashboardTest` Functional errors).
- File size: `build()` plus its three new helper methods is ~230 lines,
  down from ~300 lines of `build()` alone (~370 total pre-refactor).
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `hivelog.services.yml`.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Tasks:: [[0117-breadcrumb-terminal-crumb-on-form-pages]] (builds on
  this), [[0122-top-level-entity-breadcrumb-threading]]
- Commits::
