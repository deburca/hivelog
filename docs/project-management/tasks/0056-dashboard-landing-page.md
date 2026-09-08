---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[dashboard-landing-page]]"
area: theme
created: 2026-09-07
branch: feature/0056-dashboard-landing-page
release:
depends-on:
blocked-by:
---
# Task: Implement the dashboard landing page

## Context
Build the dashboard at `/hivelog` proposed in [[dashboard-landing-page]] —
an operational roll-up of the time-sensitive signals the module already
computes (overdue/due seasonal actions, low stock, current ISO week,
recent records) so the beekeeper does not have to drill one apiary deep to
see what needs doing. Motivated by finding 1 of
[[navigation-and-page-layout]].

**Shipped incrementally** (#118 routing skeleton, #119 shell, #120 needs-
attention, #121 stat tiles, #122 upcoming/recent, #123 apiaries section,
plus this test sweep), each PR merged CI-green (lint + kernel/unit on PHP
8.3/8.4/8.5). The six open questions are resolved in the project note's
Decisions section; the IA change is [[0057-dashboard-information-architecture]]
(accepted). Criteria 2–6 were built directly under this task rather than
split into their own `00NN` files.

Design reference: `projects/dashboard-landing-page-mockup.html` and
<https://claude.ai/code/artifact/62dfb1ba-8604-48b6-b9dd-e17b9eb57cd1>.

## Acceptance criteria
- [x] **Routing.** Implement [[0057-dashboard-information-architecture]]:
      add the `hivelog.dashboard` route at `/hivelog` with an empty
      `DashboardController::view()`; move `entity.apiary.collection` to
      `/hivelog/apiaries` (the `Apiary` `collection` link template); the
      `hivelog.links.menu.yml` edits (dashboard as the `HiveLog` parent,
      **Apiaries** as first child); and the breadcrumb-root change
      (`entity.apiary.collection` → `hivelog.dashboard`) with its
      `HivelogBreadcrumbBuilderTest` update. Confirm
      `HivelogBreadcrumbBuilder::applies()` still resolves a sane trail on
      the dashboard route itself. No redirect (ADR decision 3).
      **Done** (branch `feature/0056-dashboard-landing-page`): new route +
      skeleton `DashboardController::view()` (placeholder body + intro
      link to `/hivelog/apiaries`, `user.permissions` cache context);
      `entity.apiary.collection` and the `Apiary` `collection` link
      template now `/hivelog/apiaries`; `hivelog.admin` menu link
      repointed at `hivelog.dashboard` with a new `hivelog.apiaries`
      first child (weight 0); breadcrumb root crumb → `hivelog.dashboard`
      plus an "Apiaries" terminal crumb on the collection route.
      `HivelogBreadcrumbBuilderTest`: new `testBuildDashboard`,
      `testBuildApiaryCollection` updated to the 3-crumb trail, dashboard
      route added to the `applies()` provider. `PermissionMatrixTest`
      gains `/hivelog/apiaries` in its three path lists (status codes
      only — no dashboard content assertions yet). Lint + full suite run
      in CI (no local Docker/vendor); `ApiaryListBuilder` and its CBR
      block untouched — that moves in the Shell criterion.
- [x] **Shell + design system.** `hivelog/dashboard` CSS library (grid,
      depends on `hivelog/responsive`, ADR-0011 breakpoints); new
      `components/stat-tile/` SDC; header strip (current-ISO-week badge +
      the CBR summary line moved out of `ApiaryListBuilder::render()`);
      first-run welcome state (no apiaries → workflow summary + "Add your
      first apiary", mentions the 31-entry calendar auto-seed);
      single-column stack ≤768px. Reuse `hivelog:entity-table`,
      `hivelog:button` / `button-group`, `hivelog-list-heading` — no new
      button CSS ([[0012-action-button-design-system]]).
      **Done** (branch `feature/0056-dashboard-shell`): `css/hivelog.dashboard.css`
      + `dashboard` library entry (deps `hivelog/buttons` + `hivelog/responsive`);
      `components/stat-tile/` SDC (value / label / url / sublabel /
      sublabel_variant) with its own CSS; `DashboardController` now renders
      the header strip (ISO-week badge via `inline_template` + the CBR
      three-state summary, `secondsUntilNextIsoWeek()` max-age, `user`
      cache context) and either the first-run welcome card (no visible
      apiaries) or an interim body. CBR summary + its `userStorage` /
      `currentUser` deps removed from `ApiaryListBuilder`; the per-row CBR
      column and `extractCbr()` stay. Tests: new `DashboardTest` kernel
      (welcome vs interim, week badge, CBR states, stat-tile SDC render,
      cache metadata); `CbrFieldTest::testRenderedCaptionForCurrentUser`
      removed (moved into `DashboardTest`), its docblock retargeted. The
      stat-tile grid is styled but unpopulated until the Stat tiles
      criterion.
- [x] **"Needs attention" widget.** Cross-apiary + cross-hive aggregation
      of unreported `enabled` `CalendarAction`s (current year) whose
      timing is overdue or due-this-week, plus `isLowStock()` items,
      ordered by severity then lateness. Each row: left severity stripe
      (critical/warning), mono status chip (`OVERDUE N WK`, `DUE WK NN`,
      `LOW STOCK`), apiary/hive attribution as links, planned week window,
      one action button. One-click actions reuse the existing
      safe-GET-to-scoped-add-form pattern ([[0018-csrf-and-safe-http-methods]]).
      Generalises `ApiaryController::buildApiaryCalendarChecklist()` /
      `HiveController::buildCalendarChecklist()` /
      `pendingActionTimingLabel()`. "All caught up for week NN." empty
      state.
      **Done** (branch `feature/0056-needs-attention`): `DashboardController`
      gains `buildNeedsAttention()` + `collectSeasonalAlerts()` (apiary- and
      hive-scoped, one row per hive for hive-scoped) + `collectLowStockAlerts()`
      (active items only, discontinued excluded) + `attentionTiming()` /
      `weekWindow()` / `attentionContext()` helpers. Rows: severity-stripe
      container + mono chip + apiary/hive `toLink()` context + a single
      "Report done" / "Add purchase" `hivelog:button` (safe GET to the
      scoped add-form with `?status=done`). `.hivelog-attention*` styles
      added to `css/hivelog.dashboard.css`. `view()` shows the panel
      whenever the user has a visible apiary (before the interim body).
      Cache: `calendar_action` / `apiary_action_log` / `hive_action_log` /
      `hive` / `inventory_item` / `inventory_purchase` / `inventory_usage`
      list tags + per-row deps; `user` context + ISO-week max-age already
      from criterion 2. `DashboardTest` gains 9 kernel tests (caught-up,
      overdue apiary action, due-this-week, upcoming hidden, reported
      hidden, hive-scoped per-hive, low stock, discontinued excluded,
      cache tags). phpcs clean locally (Drupal + DrupalPractice). Hives
      are not filtered by status yet — parity with the existing
      `HiveController` checklist; a possible later refinement. No cap on
      row count yet.
- [x] **Stat tiles.** Apiaries · Active hives · Inspections this month ·
      Open seasonal tasks (with overdue sub-count) · Low-stock items ·
      Net YTD. Counts via `->count()` queries, not entity loads. Net YTD
      is a straight sum of
      `InventoryReportController::computeApiaryYearTotals()` across the
      apiaries the user can view — no new financial logic; the tile is
      omitted for users without inventory-view access. Each tile links to
      the relevant collection / filtered view.
      **Done** (branch `feature/0056-stat-tiles`): `computeApiaryYearTotals()`
      made `public` (pure read-side aggregation). `DashboardController`
      gains `class_resolver` DI + `buildStatTiles()` / `statTile()` /
      `canSeeFinances()` / `sumNetYtd()` / `secondsUntilTomorrow()` /
      `effectiveWeekEnd()`. `collectSeasonalAlerts()` refactored to return
      `{alerts, open_total, open_overdue}` in one pass — `view()` calls it
      once and feeds both the needs-attention queue and the "Open seasonal
      tasks" tile; `buildNeedsAttention()` now takes the pre-merged alert
      list. Tiles: Apiaries (from the loaded set) · Active hives
      (`status = active` count) · Inspections this month
      (`inspection_date` in the current month, hives in visible apiaries) ·
      Open seasonal tasks (+ `N overdue` critical sub-line) · Low-stock
      items (warning sub-line) · Net YTD (`number_format(sum, 0)`, links
      to the single apiary's financial report or the apiary list). Links:
      each tile → its collection (`entity.*.collection`;
      `entity.calendar_action.collection` for Open tasks). `max-age` now
      `min(secondsUntilNextIsoWeek(), secondsUntilTomorrow())` (the
      month-relative count needs a daily bound). Cache: `hive` /
      `hive_inspection` / `calendar_action` / `apiary_action_log` /
      `hive_action_log` / `inventory_item` / `inventory_purchase` /
      `inventory_usage` / `harvest_yield` / `product` list tags + the
      item/product deps `computeApiaryYearTotals()` touched. `DashboardTest`
      gains 6 kernel tests (grid render, inspections-this-month scoping,
      open-tasks tally + overdue, low-stock count, Net YTD shown/hidden by
      permission). phpcs clean locally.
- [x] **"Upcoming" + "Recent activity" widgets.** Upcoming: unreported
      seasonal actions whose `week_start` is within the next ~4 weeks,
      grouped by week, read-only. Recent activity: reverse-chronological
      merge across inspections, queen observations, action logs,
      inventory purchases and harvest yields — all five record types
      (decision 5), each capped per type before the merge, then sliced to
      ~10, each linked to its canonical page.
      **Done** (branch `feature/0056-upcoming-recent`): `DashboardController`
      gains `date.formatter` DI + `buildUpcoming()` /
      `reportedApiaryActionIds()` / `buildRecentActivity()` /
      `recentActivityUrl()`. `view()` renders an `activity` split
      container (`.hivelog-dashboard__split`, 2-col → 1-col ≤768px) after
      the stat tiles. **Upcoming:** enabled `CalendarAction`s with
      `week_start` in `[week+1, min(week+4, 53)]`, one row per action
      (hive-scoped not fanned out — it's a forward plan), apiary-scoped
      ones already reported done/ignored this year dropped; each row is
      `Wk NN` + linked title + apiary name; no year wraparound; empty
      state "Nothing scheduled for the next four weeks." **Recent
      activity:** 6 record types (inspection, queen observation, hive +
      apiary action log, purchase, harvest yield) queried newest-first
      `->range(0, 10)` per type, `->access('view')`-filtered, merged by
      `created` desc, sliced to 10; each row is `j M` date + noun + link
      (harvest yields, which have no canonical route, link to their owning
      action log). New `.hivelog-upcoming*` / `.hivelog-recent*` CSS.
      Cache: `calendar_action` / `apiary_action_log` / `hive_inspection` /
      `queen_observation` / `hive_action_log` / `inventory_purchase` /
      `harvest_yield` list tags + per-row deps. `DashboardTest` gains 6
      kernel tests (window in/out, reported-skip, empty state, merge +
      ordering + linking, harvest-yield fallback link, cache tags). phpcs
      clean locally.
- [x] **"Apiaries" section.** Compact apiary summary as the closing
      section (name, hive count, open-tasks badge with overdue callout,
      low-stock badge, last activity), plus "Add Apiary". Strip the CBR
      block from `ApiaryListBuilder::render()` (now only heading + table
      at `/hivelog/apiaries`).
      **Done** (branch `feature/0056-apiaries-section`): the CBR strip
      already landed in the Shell criterion. `collectSeasonalAlerts()` /
      `collectLowStockAlerts()` now also return a per-apiary tally
      (`by_apiary`) from the same pass. New `buildApiariesSection()` +
      `lastActivityByApiary()` render a `hivelog:entity-table` — one row
      per visible apiary: linked name · hive count · open tasks
      (`N (M overdue)`) · low-stock count or `—` · most recent inspection
      date / action-log time or `—` — under a heading with an "Add
      Apiary" primary button. `hivelog/dashboard` library gains a
      `hivelog/tables` dep; `.hivelog-dashboard__section-head` CSS added.
      `buildInterimBody()` removed; `view()` renders the section at weight
      40. `DashboardTest` gains 5 kernel tests (per-apiary rows + hive
      counts, open-tasks overdue callout, low-stock cell, last-activity
      date, last-activity empty); `testWidgetsShownWhenApiariesExist`
      updated ("Add Apiary" / apiary name instead of the removed "Go to
      Apiaries"). phpcs clean locally. **Task 0056 feature work complete;
      criterion 7 is the final sweep.**
- [x] **Cache metadata** ([[0009-render-cacheability-discipline]]):
      list cache tags for every surfaced entity type (hive,
      hive_inspection, queen_observation, calendar_action,
      hive_action_log, apiary_action_log, inventory_item,
      inventory_purchase, inventory_usage, harvest_yield, product);
      per-row entity dependencies; `user` context;
      `max-age = min(secondsUntilNextIsoWeek(), secondsUntilTomorrow())` —
      the ISO-week bound the criterion called for, tightened to a daily
      bound in the Stat-tiles criterion for the month-relative
      "Inspections this month" count. `user` is per-user so it subsumes
      `user.permissions` — two users never share an entry — hence no
      separate permission context. `DashboardTest::testCacheMetadataCoversEverySurfacedList`
      asserts all 12 list tags directly on `view()['#cache']['tags']`.
- [x] **Access.** `viewableApiaries()` `->access('view')`-filters the
      loaded apiaries, and every collect/build step `->access('view')`-
      filters the child entities it loads, so the roll-up only reflects
      what the user may see. The `user` cache context means two accounts
      never share a cache entry. The route reuses the existing
      `view own apiary + view any apiary + administer hivelog` OR-set
      (decision 6). Covered by `testAccessFilteredRollUp` (owner sees only
      their apiary's overdue task, not another owner's) and
      `testNetYtdTileHiddenWithoutInventoryPermission`.
- [x] Tests added/updated (`--group hivelog`): kernel — 30 methods in
      `tests/src/Kernel/DashboardTest.php` across aggregation, the
      access-filtered roll-up, comprehensive cache metadata, first-run
      (`testFirstRunWelcomeState`) and all-empty (`testEmptyWidgetStates`,
      `testNeedsAttentionAllCaughtUp`, `testUpcomingEmptyState`) states;
      functional — new `tests/src/Functional/DashboardTest.php`: `/hivelog`
      serves the dashboard (not the list), the apiary collection is at
      `/hivelog/apiaries`, the breadcrumb "HiveLog" crumb links to the
      dashboard. `PermissionMatrixTest` already covers `/hivelog` +
      `/hivelog/apiaries` status codes for anon/viewer/admin. Per ADR-0057
      decision 3 there is no redirect — `/hivelog` stays valid, it just
      renders the dashboard.
- [x] No `@media` rules added outside the `css/hivelog.responsive.css`
      breakpoints — every `@media` in `css/hivelog.dashboard.css` is
      `(max-width: 768px)`; `components/stat-tile/stat-tile.css` has none.
      A router rebuild (`drush cr`) is needed on deploy for the moved
      route (not runnable in CI's lint job; the functional job boots a
      full site and exercises the routes).

## Implementation notes
- Key files (new): `src/Controller/DashboardController.php`,
  `components/stat-tile/*`, `css/hivelog.dashboard.css`, a new
  `docs/project-management/decisions/00NN-*.md`.
- Key files (changed): `hivelog.routing.yml`, `hivelog.links.menu.yml`,
  `hivelog.libraries.yml`, `src/Entity/Apiary.php` (collection link),
  `src/ApiaryListBuilder.php` (drop CBR block),
  `src/Breadcrumb/HivelogBreadcrumbBuilder.php`
  + `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.
  No `hivelog.permissions.yml` change — decision 6 reuses the existing
  OR-set.
- No entity schema change and no update hook — the inspection-cadence
  field (project decision 4) is explicitly out of scope for 0056.
- Follow [[0004-custom-controllers-over-view-builders]]: the dashboard is
  a custom controller assembling embedded list-builder-style tables, same
  as `ApiaryController` / `HiveController`.
- "Needs attention" and "Upcoming" revisit
  [[seasonal-calendar-and-hive-action-tracking]]'s "dashboard alerts —
  out of scope" deferral, for pull-on-open surfacing only (no push
  notifications).

## Related
- Project:: [[dashboard-landing-page]]
- Decisions:: [[0057-dashboard-information-architecture]], [[navigation-and-page-layout]], [[0004-custom-controllers-over-view-builders]], [[0005-sdc-component-library]], [[0009-render-cacheability-discipline]], [[0011-responsive-design-strategy]], [[0012-action-button-design-system]], [[0013-breadcrumb-policy]], [[0025-seasonal-calendar-and-hive-action-tracking]]
- Commits::
