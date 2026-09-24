---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0117-breadcrumb-terminal-crumb-on-form-pages
release:
depends-on: ["[[0116-breadcrumb-builder-parent-map-refactor]]"]
blocked-by:
---
# Task: Terminal page crumb on edit / delete / add pages

## Context
**User-visible bug**, found in the navigation and breadcrumb review of
2026-09-23 and verified live against `cms2`. On every edit, delete and
add form, the breadcrumb ends with the same crumb as the entity's own
view page (or, for site-wide add forms, the collection). The theme
renders the last crumb as plain text, so the page the user would go back
to can't be clicked. For example, `/hivelog/hive/22/edit` renders
`[Home] › [Apiary] › Hive`, with "Hive" not a link. Full evidence table
and the rule being adopted:
[[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]].

Depends on [[0116-breadcrumb-builder-parent-map-refactor]] because the
refactor turns this fix into one generic rule. If the refactor slips,
this can still land on the current builder as a small per-route
addition; the refactor is not strictly required.

## Acceptance criteria
- [x] Edit / delete forms for every hivelog-owned and submodule entity
      type end `… › [<Entity>] › Edit` / `… › [<Entity>] › Delete`, with
      the entity crumb linked.
- [x] Scoped add forms end `… › [<Parent>] › <route title>`, e.g.
      `/hivelog/apiary/43/hive/add` → `[HiveLog] › [Apiary] › Add Hive`.
      Covers `hivelog.hive.add`, `.inspection.add`, `.queen.add`,
      `.queen_observation.add`, `.calendar_action.add`,
      `.hive_action_log.add`, `.apiary_action_log.add`,
      `.inventory_item.add`, `.inventory_purchase.add`, `.product.add`,
      `.calendar_action_item_requirement.add`,
      `.calendar_action_product_yield.add`,
      `nanoprobe.sensor_device.add_for_hive` / `.add_for_apiary`.
- [x] Site-wide add forms (`entity.<type>.add_form`) end
      `[HiveLog] › [<Collection>] › <route title>`, with the collection
      linked. For example, `/hivelog/queen/add` → `[Queens] › Add Queen`.
- [x] Named sub-pages (Insights, Calendar, Financial Report, Readings,
      Download Configuration, Regenerate Token) keep their current trails.
      They already follow the rule.
- [x] Layout Builder override routes get a terminal crumb from the
      route title.
- [x] Canonical pages and collection pages are **unchanged**.
- [x] `HivelogBreadcrumbBuilderTest` updated: every edit / delete / add
      data-provider case asserts the new terminal crumb and that the
      preceding crumb targets the entity's canonical route (or the
      collection route).
- [x] Verified live on `cms2` (`Host: drupal-cms2.ddev.site`) for at
      least: hive edit, hive delete, hive-scoped inspection add,
      apiary-scoped hive add, site-wide queen add, sensor device edit.
- [x] [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]] flipped
      to `accepted` in the same commit.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **One generic helper, not a per-route-shape map.** `build()` now falls
  through to a single new method, `addGenericTerminalCrumb()`, for
  anything that isn't a leaf collection page, a named sub-page (the
  existing `TERMINAL_CRUMB_PARAM` map, unchanged), or a canonical page
  (guarded by `str_ends_with($route_name, '.canonical')`, since
  `addEntityLink()` already gave canonical pages their terminal crumb —
  the entity's own label). That one method handles edit forms,
  delete forms, every scoped and site-wide add form, and any future
  bolt-on route (Layout Builder included) with the same few lines.
- **The terminal link's route parameters are the route's own raw
  parameters** (`$route_match->getRawParameters()->all()`), not
  something reconstructed from the resolved subject entity. This turned
  out to be essential, not just convenient: `hivelog.hive_action_log.add`
  and `.apiary_action_log.add` require *two* route parameters at once
  (`{hive}`/`{apiary}` **and** `{calendar_action}`), and the subject
  resolver deliberately only tracks one of them. Rebuilding the link
  from the route's own raw parameters sidesteps that entirely — it
  works identically whether the route needs zero, one, or two
  parameters, with no per-route special-casing.
- **Terminal label source, exactly as planned**: `str_ends_with()`
  checks for `.edit_form` / `.delete_form` give the fixed `t('Edit')` /
  `t('Delete')`; everything else reads the route's own title via a new
  `routeTitle()` — `$route->getDefault('_title')` first (every add
  route in this module declares one statically), falling back to
  `\Drupal::service('title_resolver')->getTitle()` only for a route
  with a `_title_callback` instead (a hypothetical Layout Builder
  override; no route this module defines outside the existing named
  sub-pages actually needs this path — covered by a dedicated test all
  the same). A route with neither gets no terminal crumb added, which
  is the graceful, pre-existing fallback rather than a new failure
  mode — proven by the existing Layout Builder test, unmodified,
  still passing.
- **`str_ends_with()` on the route name, not a route-name allow-list.**
  Every `entity.<type>.edit_form` / `.delete_form` / `.canonical` route
  in this module (and, by Drupal convention, any other module's) ends
  with exactly that suffix — checked against the full routing.yml
  before relying on it. Avoids a third declarative map alongside
  `PARENT_FIELD`/`COLLECTION_THREADED_TYPES` (0116) and
  `TERMINAL_CRUMB_PARAM` for something that's already a naming
  convention.
- **`HivelogBreadcrumbBuilder` stays constructor-free**, matching
  0116's explicit choice — `title_resolver` and the current request
  are read via `\Drupal::` static calls for the one fallback branch
  that's never actually exercised by a real route today (`\Drupal`
  static-call phpcs/phpstan warnings accepted here the same way the
  rest of the module's controllers already do; not a new pattern).
- **Test updates**: `createRouteMatch()` gained two optional
  parameters — `$raw_parameters` (what `getRawParameters()` returns,
  needed by every edit/delete/add test from here on) and `$title` (the
  route's static `_title`, needed only by add-route tests) — rather
  than changing its existing call sites' meaning. ~24 existing
  edit/delete/add test methods updated in place (one more link, one
  more assertion each); one new test
  (`testBuildBoltOnRouteWithStaticTitleGetsTerminalCrumb`) added
  alongside the existing (unmodified) Layout Builder test to cover the
  affirmative "a bolt-on route with a resolvable title gets a terminal
  crumb" case the existing test didn't exercise.
- **Verification**: 120 unit tests / 569 assertions, all green — the
  full breadcrumb suite plus the one new test. phpcs clean (0 errors)
  on both changed files; phpstan clean except the same
  pre-existing PHPUnit-mock `method.notFound` noise task 0116 already
  documented across this whole test file (confirmed unrelated by
  diffing phpstan's error list before/after this task's changes) and
  the two `\Drupal::` static-call advisory warnings noted above. Full
  `hivelog` suite: 902 tests, same 3 pre-existing unrelated
  `DashboardTest` Functional errors. Live-verified on `cms2` (all six
  routes from the acceptance criterion) after a `drush cr` — every one
  now shows the expected `[Entity/Parent] › Edit|Delete|Add …` shape,
  with the previously-terminal entity crumb now a working link back
  (confirmed directly in quick_silver's rendered breadcrumb markup,
  which truncates to `Home … <last two>` exactly as ADR-0102 described,
  making the fix immediately visible: `Hive A` → `Edit`, `Meadow Apiary`
  → `Add Hive`, `Queens` → `Add Queen`, etc.).
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `docs/project-management/decisions/0102-breadcrumb-terminal-crumb-on-non-canonical-pages.md`.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]],
  [[0013-breadcrumb-policy]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
