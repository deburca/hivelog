---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-07
---
# Project: Dashboard landing page

## Goal
Replace the bare apiary table at `/hivelog` with an operational dashboard
that answers "what needs doing across all my apiaries, right now?" without
the beekeeper having to drill one level into each apiary. Today every
time-sensitive signal the module already computes — overdue/due seasonal
actions, low-stock items, the current ISO week, recent records — is
scattered one apiary deep and one hive deep. The dashboard rolls those up
into a single prioritised view, keeps the apiary list as a section, and
becomes the new home page. Motivated by the "Observations & gaps" section
of [[navigation-and-page-layout]] (finding 1).

## Design reference
Visual mockup (illustrative data): `dashboard-landing-page-mockup.html`
next to this note; also published at
<https://claude.ai/code/artifact/62dfb1ba-8604-48b6-b9dd-e17b9eb57cd1>

Layout, top to bottom (single-column stack ≤768px per
[[0011-responsive-design-strategy]]):

1. **Header strip** — "HiveLog" + current ISO week badge, the CBR summary
   line (moved here from `ApiaryListBuilder::render()`), and primary
   actions (Add Apiary; plus Add Hive / Log Inspection shortcuts when the
   user has exactly one apiary).
2. **Needs attention** — the priority queue, on its own lifted surface.
   One row per item, ordered by severity then lateness:
   overdue seasonal actions → due-this-week seasonal actions → low-stock
   items. Each row carries a left **severity stripe** (critical / warning),
   a mono **status chip** (`OVERDUE 2 WK`, `DUE WK 37`, `LOW STOCK`),
   apiary/hive attribution as links, the planned week window, and one
   action button (`Report Done` / `Add Purchase` / `Log Inspection`).
   Empty state: "You're all caught up for week NN."
3. **Stat tiles** — Apiaries · Active hives · Inspections this month ·
   Open seasonal tasks (with an overdue sub-count) · Low-stock items ·
   Net YTD. Each links to the relevant collection or filtered view.
   Hairline-divided, no per-card chrome.
4. **Upcoming (next ~4 weeks)** — unreported seasonal actions whose
   `week_start` falls in the look-ahead window, grouped by week, read-only.
5. **Recent activity** — reverse-chronological merge across inspections,
   queen observations, action logs, inventory purchases and harvest
   yields; each linked to its canonical page.
6. **Apiaries** — the existing apiary list as the closing section
   (compact: name, hive count, open-tasks badge, low-stock badge, last
   activity), plus Add Apiary.

Priority model: state is encoded in **form** (stripe colour + chip) as
well as in number, so what needs attention reads at a glance. Semantic
colours (critical/warning) are distinct from the module's accent.

## Scope
- **In scope**
  - New route `hivelog.dashboard` → `/hivelog`
    (`DashboardController::view()`), custom controller per
    [[0004-custom-controllers-over-view-builders]].
  - `entity.apiary.collection` path moves to `/hivelog/apiaries`; the
    `Apiary` `collection` link template and the `hivelog.admin` /
    `hivelog.hives` … menu tree in `hivelog.links.menu.yml` updated so the
    dashboard is the `HiveLog` parent and **Apiaries** is its first child.
  - Aggregation across **every apiary the user can view**
    (`view own apiary` + `view any apiary` + `administer hivelog`), with
    per-row apiary/hive attribution.
  - The six widgets above. "Needs attention" and "Upcoming" generalise the
    existing `ApiaryController::buildApiaryCalendarChecklist()` /
    `HiveController::buildCalendarChecklist()` logic (enabled
    `CalendarAction` × logs cross-reference, `pendingActionTimingLabel()`
    timing) to "all apiaries + all hives, status = pending, current year".
    Low-stock reuses `InventoryItem::isLowStock()` /
    `getStockOnHand()`. Net YTD reuses
    `InventoryReportController::computeApiaryYearTotals()` summed across
    apiaries — no new financial logic.
  - New `stat-tile` SDC component (`components/stat-tile/`) and a
    `hivelog/dashboard` CSS library (grid layout) depending on
    `hivelog/responsive`, following the ADR-0011 breakpoints. Reuse the
    existing `hivelog:entity-table`, `hivelog:button` /
    `hivelog:button-group`, and `hivelog-list-heading` patterns per
    [[0005-sdc-component-library]] / [[0012-action-button-design-system]].
  - Move the CBR summary block out of `ApiaryListBuilder::render()` into
    the dashboard header (the plain apiary list at `/hivelog/apiaries`
    keeps only its own heading + table).
  - Explicit cache metadata per [[0009-render-cacheability-discipline]]:
    `user.permissions` + `user` contexts; list cache tags for every
    surfaced entity type (hive, hive_inspection, queen, queen_observation,
    calendar_action, hive_action_log, apiary_action_log, inventory_item,
    inventory_purchase, inventory_usage, harvest_yield, product);
    per-row entity dependencies; `max-age = secondsUntilNextIsoWeek()`
    (the header prints the week and rows compute timing against it).
  - First-run state (no apiaries → a welcome card mirroring the README
    workflow, "Add your first apiary", mentions the 31-entry calendar
    auto-seed) and the "all caught up" empty state.
  - Breadcrumb builder ([[0013-breadcrumb-policy]]): the root link changes
    from `entity.apiary.collection` to `hivelog.dashboard`; `applies()`
    already matches the `hivelog.` prefix — confirm the dashboard route
    itself renders a sane trail (Home › HiveLog, HiveLog as the active
    tail).
  - Kernel test coverage (aggregation correctness, access-filtered
    roll-up, cache metadata, empty/first-run states) and functional
    coverage (new route + permission + breadcrumb, old `/hivelog`
    apiary-list URL now 404/redirect).
- **Out of scope**
  - Automated reminders / email / digests for upcoming or overdue actions
    — still deferred (see
    [[seasonal-calendar-and-hive-action-tracking]] "Out of scope"). This
    project surfaces existing signals on an opened page; it does not push.
  - Redesign of the **apiary canonical page** (the "overloaded apiary
    page" finding, [[navigation-and-page-layout]] #2) — separate follow-on.
  - Cross-apiary aggregate **report** pages — that is
    [[0047-cross-apiary-aggregate-views]] in
    [[inventory-and-yield-improvements]].
  - Charts / sparklines on the dashboard (weight histogram etc. stay on
    the hive page).
  - Per-user dashboard customisation, widget reordering, or saved filters.
  - "Hives overdue for inspection" — there is no expected-interval field
    today; deferred unless a cadence is defined (open question 4).
  - Changes to how Net YTD is calculated; the tile is a straight sum of
    the existing per-apiary/year totals.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0056-dashboard-landing-page]] — backlog (umbrella / entry task;
  blocked on the open questions below and the IA ADR). Its acceptance
  criteria hold the full breakdown below; split criteria 2–8 into their
  own `00NN` task files if the work is picked up incrementally.

Breakdown (currently the acceptance criteria of
[[0056-dashboard-landing-page]]):

1. **ADR + routing skeleton** — write a decision record for the IA change
   (dashboard takes `/hivelog`, apiary collection → `/hivelog/apiaries`,
   breadcrumb root link, whether a redirect from the old path is needed);
   add `hivelog.dashboard` route, an empty `DashboardController::view()`,
   the menu-tree edits, and the breadcrumb-root change with its unit-test
   update. Depends on nothing; blocks the rest.
2. **Dashboard shell + design system** — `hivelog/dashboard` CSS library,
   `stat-tile` SDC, the header strip (week badge + relocated CBR line),
   first-run welcome state, responsive stack. Depends on 1.
3. **"Needs attention" widget** — cross-apiary/cross-hive pending +
   timing + low-stock aggregation and the severity-stripe / status-chip
   row rendering, with one-click actions reusing the existing
   safe-GET-to-scoped-add-form pattern ([[0018-csrf-and-safe-http-methods]]).
   Depends on 2.
4. **Stat tiles** — the six figures and their link targets. Depends on 2.
5. **"Upcoming" + "Recent activity" widgets** — the look-ahead list and
   the cross-entity recent-records merge (capped per type before merge).
   Depends on 2.
6. **"Apiaries" section** — compact apiary summary (extend
   `ApiaryListBuilder` or a dedicated dashboard table) with the
   open-tasks / low-stock badges; strip the CBR block from the plain
   list builder. Depends on 2.
7. **Dashboard test coverage** — kernel + functional, per the In-scope
   list. Depends on 3–6.

No target release assigned yet — sequence via [[roadmap]].

## Open questions
- **Single- vs multi-apiary emphasis.** Most beekeepers run one apiary
  (the 31-entry calendar is seeded per apiary). Do we design the
  multi-apiary roll-up as a first-class case, or optimise hard for one
  apiary and treat multi-apiary as "the same widgets, with an apiary
  column"? Leaning the latter.
- **The route move needs the ADR (task 1) to land first.** Moving
  `entity.apiary.collection` off `/hivelog` changes a URL that may be
  bookmarked and is the current breadcrumb root. Confirm: is
  `/hivelog/apiaries` the right new path, and do we ship a redirect from
  the old `/hivelog` apiary-list URL or just accept the break (module is
  pre-1.0-of-this-feature)?
- **Net YTD on the landing page.** It pulls the financial report's
  aggregation onto the home screen. Acceptable, or keep money one level
  down in the per-apiary report (heaviness / at-a-glance sensitivity)?
- **"Overdue for inspection."** Worth adding an expected-inspection-
  interval field so the dashboard can flag hives not seen in N weeks, or
  leave that out of v1?
- **Recent activity scope.** All five record types (inspections, queen
  observations, action logs, purchases, harvest yields), or just
  inspections + action logs to keep the merge cheap and focused?
- **Permission.** New `access hivelog dashboard` permission, or reuse the
  existing `view own apiary + view any apiary + administer hivelog`
  OR-set that already guards `/hivelog`?

## Related decisions
- [[navigation-and-page-layout]] (the review that motivated this project)
- [[0004-custom-controllers-over-view-builders]] (the dashboard is a
  custom controller)
- [[0005-sdc-component-library]] (new `stat-tile` component)
- [[0009-render-cacheability-discipline]] (aggregation cache metadata,
  ISO-week `max-age`)
- [[0011-responsive-design-strategy]] (single-column stack, breakpoints)
- [[0012-action-button-design-system]] (row / header action buttons)
- [[0013-breadcrumb-policy]] (root link changes to the dashboard route)
- [[0025-seasonal-calendar-and-hive-action-tracking]] ("Needs attention"
  and "Upcoming" reuse its checklist mechanism; this project revisits that
  project's "dashboard alerts — out of scope" deferral, for pull-on-open
  surfacing only, not push notifications)
- **New ADR to be written** (task 1): the `/hivelog` IA change.
