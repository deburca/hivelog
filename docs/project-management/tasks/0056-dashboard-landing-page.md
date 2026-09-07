---
type: task
tags: [hivelog/task]
status: todo
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

**Design settled.** The six open questions are resolved (see the project
note's Decisions section) and the IA decision is captured in
[[0057-dashboard-information-architecture]] (accepted). Acceptance
criterion 1 lands that ADR's routing / menu / breadcrumb changes and
should go first. If the work is picked up incrementally, split criteria
2–8 into their own `00NN` task files (all
`project: "[[dashboard-landing-page]]"`); this task is the umbrella /
entry point until then.

Design reference: `projects/dashboard-landing-page-mockup.html` and
<https://claude.ai/code/artifact/62dfb1ba-8604-48b6-b9dd-e17b9eb57cd1>.

## Acceptance criteria
- [ ] **Routing.** Implement [[0057-dashboard-information-architecture]]:
      add the `hivelog.dashboard` route at `/hivelog` with an empty
      `DashboardController::view()`; move `entity.apiary.collection` to
      `/hivelog/apiaries` (the `Apiary` `collection` link template); the
      `hivelog.links.menu.yml` edits (dashboard as the `HiveLog` parent,
      **Apiaries** as first child); and the breadcrumb-root change
      (`entity.apiary.collection` → `hivelog.dashboard`) with its
      `HivelogBreadcrumbBuilderTest` update. Confirm
      `HivelogBreadcrumbBuilder::applies()` still resolves a sane trail on
      the dashboard route itself. No redirect (ADR decision 3).
- [ ] **Shell + design system.** `hivelog/dashboard` CSS library (grid,
      depends on `hivelog/responsive`, ADR-0011 breakpoints); new
      `components/stat-tile/` SDC; header strip (current-ISO-week badge +
      the CBR summary line moved out of `ApiaryListBuilder::render()`);
      first-run welcome state (no apiaries → workflow summary + "Add your
      first apiary", mentions the 31-entry calendar auto-seed);
      single-column stack ≤768px. Reuse `hivelog:entity-table`,
      `hivelog:button` / `button-group`, `hivelog-list-heading` — no new
      button CSS ([[0012-action-button-design-system]]).
- [ ] **"Needs attention" widget.** Cross-apiary + cross-hive aggregation
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
- [ ] **Stat tiles.** Apiaries · Active hives · Inspections this month ·
      Open seasonal tasks (with overdue sub-count) · Low-stock items ·
      Net YTD. Counts via `->count()` queries, not entity loads. Net YTD
      is a straight sum of
      `InventoryReportController::computeApiaryYearTotals()` across the
      apiaries the user can view — no new financial logic; the tile is
      omitted for users without inventory-view access. Each tile links to
      the relevant collection / filtered view.
- [ ] **"Upcoming" + "Recent activity" widgets.** Upcoming: unreported
      seasonal actions whose `week_start` is within the next ~4 weeks,
      grouped by week, read-only. Recent activity: reverse-chronological
      merge across inspections, queen observations, action logs,
      inventory purchases and harvest yields — all five record types
      (decision 5), each capped per type before the merge, then sliced to
      ~10, each linked to its canonical page.
- [ ] **"Apiaries" section.** Compact apiary summary as the closing
      section (name, hive count, open-tasks badge with overdue callout,
      low-stock badge, last activity), plus "Add Apiary". Strip the CBR
      block from `ApiaryListBuilder::render()` (now only heading + table
      at `/hivelog/apiaries`).
- [ ] **Cache metadata** ([[0009-render-cacheability-discipline]]):
      `user.permissions` + `user` contexts; list cache tags for every
      surfaced entity type (hive, hive_inspection, queen,
      queen_observation, calendar_action, hive_action_log,
      apiary_action_log, inventory_item, inventory_purchase,
      inventory_usage, harvest_yield, product); per-row entity
      dependencies; `max-age = secondsUntilNextIsoWeek()` (header prints
      the week; rows compute timing against it). Verified directly on the
      render array, not just by inspection.
- [ ] **Access.** Roll-up respects per-entity access — two users with
      different permissions must not share a cache entry, and a user sees
      only apiaries/hives they may view. The dashboard route reuses the
      existing `view own apiary + view any apiary + administer hivelog`
      OR-set (decision 6) — no new permission.
- [ ] Tests added/updated (`--group hivelog`): kernel — aggregation
      correctness, access-filtered roll-up, cache metadata, first-run and
      "all caught up" states; functional — new route + permission +
      breadcrumb, old `/hivelog` apiary-list URL now redirects/404s.
- [ ] `ddev drush cr` clean; no `@media` rules added outside the
      `css/hivelog.responsive.css` breakpoints.

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
