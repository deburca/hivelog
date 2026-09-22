---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-22
completed: 2026-09-22
branch: feature/0105-submodule-navigation-menu-links
release:
depends-on:
blocked-by:
---
# Task: In-app secondary navigation + submodule menu links

> **Scope grew mid-task, 2026-09-22.** The original finding was "no
> `*.links.menu.yml` for `nanoprobe`/`collective`/`nexus`" — true, and
> fixed below, but manually verifying it surfaced a deeper problem: on
> `cms2` (the real dev site this was tested against), **none** of
> `hivelog.links.menu.yml`'s existing 8 links are visible anywhere
> either. The active theme, `quick_silver` (its own separate repo,
> `cms2/web/themes/custom/quick_silver`), has zero block placements and
> its `page.html.twig` never renders a child menu tree for "HiveLog" —
> only the top-level link. This predates this task and isn't fixable
> from inside `hivelog`'s own repo. Explicit direction: **`hivelog`
> should own a theme-independent in-app secondary nav instead of
> depending on any particular theme rendering a menu block correctly.**
> The `*.links.menu.yml` files stay too, as a correct, standard,
> best-effort integration for themes/toolbars that do honor them — they
> just aren't the thing this task now hangs its acceptance criteria on.

## Context
Caught during manual UI validation ahead of
[[0094-ai-insights-prelaunch-validation]]: with `hivelog`, `nanoprobe`,
`collective`, and `nexus` all installed and enabled, there is no way to
find any of the three submodules' admin pages from the UI — and, per the
scope note above, no way to find several of `hivelog` core's own either,
on at least one real theme. `entity.api_client.collection`
(`/hivelog/api-clients`, `collective`) and
`entity.ai_provider_config.collection` (`/hivelog/ai-provider-configs`,
`nexus`) both genuinely work today, just aren't linked from anywhere a
beekeeper would find them. See
[[0106-sensor-device-management-ui]] for the deeper, separate gap
(`nanoprobe` has no collection page to link to at all yet).

## Acceptance criteria
- [x] `modules/collective/collective.links.menu.yml` /
      `modules/nexus/nexus.links.menu.yml`: links to
      `entity.api_client.collection` / `entity.ai_provider_config.collection`,
      parented under `hivelog.admin`, matching `hivelog.links.menu.yml`'s
      exact shape — correct and complete, kept as a best-effort
      integration even though it isn't what closes this task out.
      `nanoprobe`'s own link is deliberately left for
      [[0106-sensor-device-management-ui]] — no collection route exists
      yet to point it at.
- [x] New `hook_hivelog_app_nav_items()` (`hivelog.api.php`), the same
      ADR-0099 shape as the panel/dashboard-section hooks: an
      implementation returns nav item descriptors
      (`['title' => ..., 'url' => Url, 'weight' => int]`), keyed
      uniquely, so `nanoprobe`/`collective`/`nexus` can contribute
      destinations without `hivelog` depending on them.
- [x] `HivelogAppNavBuilder` service: assembles `hivelog`'s own 8
      built-in destinations (the same set `hivelog.links.menu.yml`
      already lists, same weights) plus every
      `hook_hivelog_app_nav_items()` contribution, drops any item the
      current user's route access denies, sorts by weight.
- [x] Rendered via `hook_preprocess_page()`, injected directly into
      `$variables['page']['content']` — **not** `hook_page_top()` and
      **not** a placed block. Both were considered and rejected:
      `page.content` is the one region confirmed (by direct DOM
      inspection against the real failure case) to render regardless of
      a theme's own template choices; `quick_silver`'s `page.html.twig`
      has no `{{ page.top }}` at all, so that hook's output would have
      silently vanished exactly like the block-placement approach did.
- [x] Correct cache contexts (`url.path`, `user.permissions`) on the
      nav's own render array, since both which items appear (access)
      and whether it renders at all (path) vary per request.
- [x] Renders only on `/hivelog`-prefixed paths — reuses
      `hivelog_preprocess_html()`'s existing path-matching exactly
      (`$route->getPath()` against `/hivelog`/`/hivelog/...`), not a
      second, slightly-different implementation of the same check.
- [x] Minimal CSS (`hivelog/app_nav` library) using the existing
      `--hivelog-*` custom-property tokens (`hivelog.responsive.css`) —
      no new token set invented for one small nav strip.
- [x] `collective_hivelog_app_nav_items()` / `nexus_hivelog_app_nav_items()`
      implemented in their own `.module` files, contributing "API
      Clients" / "AI Provider Configs" at the same weights (8, 9) their
      `*.links.menu.yml` entries already use.
- [x] Kernel tests: nav present on a `/hivelog` path, absent elsewhere;
      an inaccessible item (route access denied) doesn't render; hook
      dispatch confirmed via `moduleHandler()->invokeAll()`; a
      submodule-contributed item appears in the real rendered page,
      mirroring `SensorAlertCollectorTest::testSensorAlertAppearsInMergedDashboardQueue()`'s
      "real merged output" style, not just the builder in isolation.
- [x] Manually verified in a browser against `cms2`: the nav is now
      actually visible, unlike the menu-link approach it replaces as the
      primary mechanism.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **A real placement bug, caught only by looking at the actual rendered
  page, not by any kernel test**: the nav's own render array had no
  `#weight` set, so it landed *after* the dashboard's own `system_main`
  content in `page.content` — technically present (confirmed via a
  kernel test asserting the array key exists) but scrolled to the very
  bottom of the page, past "Recent activity", which defeats the entire
  point. Fixed with `#weight => -1000` — deliberately far outside this
  codebase's usual small page-section weight ranges (`DashboardController`
  uses roughly -30..20), since this needs to sort ahead of an existing
  region entry that has no weight of its own to out-rank normally.
  Confirmed live afterward: `ApiariesHivesInspections...` now appears as
  literally the first text in `<main>`.
- **Verified against the real `quick_silver` failure case, not a
  hypothetical one**: confirmed via direct DOM inspection (`fetch`/
  `querySelector` in the live browser pane) that the nav renders
  correctly both on `DashboardController`'s custom page and on a plain
  core entity collection page (`/hivelog/apiaries`, `HivelogListBuilder`)
  — the injection point (`hook_preprocess_page()`) is upstream of both,
  which was the actual point of choosing it over per-controller wiring.
  Also confirmed absent on a genuinely non-`hivelog` page (`/features`).
- **`Url::access()` is called without an explicit account**, defaulting
  to `\Drupal::currentUser()` — correct here since this always runs
  within a real request context (`hook_preprocess_page()`), unlike a
  service method a test might call with an arbitrary account.
- The `*.links.menu.yml` files from this task's original, narrower scope
  stay in place — see the scope-note callout at the top of this file for
  why neither approach was dropped.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0099-submodule-canonical-page-panel-hook]] (the pattern
  `hook_hivelog_app_nav_items()` extends, alongside
  [[0093-dashboard-ai-insights-section]]'s `hook_hivelog_dashboard_sections()`)
- Commits::
