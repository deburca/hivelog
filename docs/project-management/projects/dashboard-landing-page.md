---
type: project
tags: [hivelog/project]
status: done
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
into a single prioritised view and becomes the new home page; the apiary
list moves to `/hivelog/apiaries`, reached from the Apiaries stat tile.
Motivated by the "Observations & gaps" section of
[[navigation-and-page-layout]] (finding 1).

## Design reference
Visual mockup (illustrative data, updated to the shipped structure —
Fraunces / IBM Plex type and the beeswax / pine palette are the mockup's
own proposal, not what the theme-neutral module renders):
`dashboard-landing-page-mockup.html` next to this note; also published at
<https://claude.ai/code/artifact/62dfb1ba-8604-48b6-b9dd-e17b9eb57cd1>

Layout as shipped, top to bottom (single-column stack ≤768px per
[[0011-responsive-design-strategy]]):

1. **Masthead + header strip** — a hexagon mark (56×56) + "HiveLog"
   wordmark injected into the theme's page-title `<h1>` by
   `DashboardController::title()`, with an "Apiary and hive logbook"
   subtitle line just below. Opposite it, right-aligned: the current
   ISO-week badge ("Week NN · YYYY") and the CBR summary line (moved here
   from `ApiaryListBuilder::render()`). No primary-action buttons in the
   header — the Apiaries / Active hives / … stat tiles are the entry
   points, and the first-run welcome card carries the only "Add apiary"
   CTA.
2. **Needs attention** — the priority queue, on its own lifted surface.
   One row per item, ordered by severity then lateness:
   overdue seasonal actions → due-this-week seasonal actions → low-stock
   items. Each row carries a left **severity stripe** (critical =
   overdue, warning = due / low stock), a **status chip** (`Overdue 2
   wk`, `Due wk 37`, `Low stock` — uppercased in CSS), apiary/hive
   attribution as links with the planned week window (or "N unit on hand
   · reorder at M" for stock), and one action button (`Report done` /
   `Add purchase`). The header's count is right-aligned and coloured
   (`--danger` when anything is overdue, else `--warning`): "N to review
   · M overdue" or "N items to review". Empty state: "All caught up for
   week NN."
3. **Stat tiles** — Apiaries · Active hives · Inspections this month ·
   Open seasonal tasks (with an "M overdue" sub-line) · Low-stock items ·
   Net YTD (finance-gated). Whole-tile links, hairline-divided, no
   per-card chrome. Smart targets: the **Apiaries** tile links straight
   to the single apiary when there is one, else to `/hivelog/apiaries`;
   the **Net YTD** tile links to that apiary's financial report when
   there is one, else to the combined all-apiaries report
   (`/hivelog/apiaries/financial-report`, task 0059).
4. **Upcoming** — enabled, unreported seasonal actions whose `week_start`
   falls in weeks `current+1 … current+4`, one row each ("Wk NN  title ·
   apiary"), read-only. Heading "Upcoming".
5. **Recent activity** — reverse-chronological merge across inspections,
   queen observations, action logs (hive + apiary), inventory purchases
   and harvest yields; each capped at 10 before the merge, then sliced to
   10, rendered "j M  Noun — label" with the label linked to its
   canonical page. Heading "Recent activity".

The closing **Apiaries** summary section from earlier drafts was built
(task 0056 criterion 6) and then removed in task 0058 — it duplicated the
Apiaries stat tile, which already links to the list.

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
  - The widgets above. "Needs attention" and "Upcoming" generalise the
    existing `ApiaryController::buildApiaryCalendarChecklist()` /
    `HiveController::buildCalendarChecklist()` logic (enabled
    `CalendarAction` × logs cross-reference, `pendingActionTimingLabel()`
    timing) to "all apiaries + all hives, status = pending, current year".
    Low-stock reuses `InventoryItem::isLowStock()` /
    `getStockOnHand()`. Net YTD reuses
    `InventoryReportController::computeApiaryYearTotals()` summed across
    apiaries — no new financial logic. The Net YTD tile links to that
    apiary's `hivelog.apiary.inventory_cost_report` when the user has one
    apiary; when they have several it links to the combined
    `hivelog.apiaries.financial_report` (added in task 0059, the same
    per-apiary aggregation run once per apiary and summed).
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
    `user` context (per-user CBR line + the per-entity access filtering
    the roll-up does); list cache tags for every surfaced entity type
    (apiary, hive, hive_inspection, queen_observation, calendar_action,
    hive_action_log, apiary_action_log, inventory_item,
    inventory_purchase, inventory_usage, harvest_yield, product);
    per-row entity dependencies;
    `max-age = min(secondsUntilNextIsoWeek(), secondsUntilTomorrow())` —
    the header prints the ISO week and the chips are week-relative, but
    "Inspections this month" is date-relative, so the render must not
    outlive the sooner of the next week boundary and the next midnight.
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
    coverage (new route + permission + breadcrumb; `/hivelog` still
    resolves — now the dashboard — and `/hivelog/apiaries` serves the
    apiary list, no redirect).
- **Out of scope**
  - Automated reminders / email / digests for upcoming or overdue actions
    — still deferred (see
    [[seasonal-calendar-and-hive-action-tracking]] "Out of scope"). This
    project surfaces existing signals on an opened page; it does not push.
  - Redesign of the **apiary canonical page** (the "overloaded apiary
    page" finding, [[navigation-and-page-layout]] #2) — separate follow-on.
  - Cross-apiary aggregate **report** pages — that is
    [[0047-cross-apiary-aggregate-views]] in
    [[inventory-and-yield-improvements]]. One narrow slice shipped as a
    follow-on: the combined **financial** report
    (`/hivelog/apiaries/financial-report`, task 0059), built only as the
    Net YTD tile's drill-down for the multi-apiary case. The broader
    aggregate views stay in 0047.
  - Charts / sparklines on the dashboard (weight histogram etc. stay on
    the hive page).
  - Per-user dashboard customisation, widget reordering, or saved filters.
  - "Hives overdue for inspection" — deferred to its own later task
    (decision 4): needs an expected-inspection-interval field on `Hive`
    plus an update hook, out of scope for 0056.
  - Changes to how Net YTD is calculated; the tile is a straight sum of
    the existing per-apiary/year totals.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0056-dashboard-landing-page]] — **done**. Built under the one task
  (not split), one CI-green PR per acceptance criterion (#118–#124) plus
  a test sweep; every criterion checked off.
  [[0057-dashboard-information-architecture]] is accepted.
- [[0058-dashboard-visual-polish]] — **done** (PR #125, follow-up in the
  same PR). Post-launch review against the mockup on a plain admin theme:
  the 56px hexagon + subtitle masthead, right-aligned week/CBR strip,
  coloured right-aligned "Needs attention" count, dashboard buttons
  wired into the `hivelog.buttons.css` context list, and a plain-CSS
  baseline for the `hivelog:entity-table` SDC. The same PR then **removed
  the closing Apiaries section** (criterion 6 below) as redundant with
  the Apiaries tile, and made the single-apiary Apiaries tile link
  straight to that apiary.
- [[0059-combined-financial-report]] — **done** (PR #126). The combined
  all-apiaries financial report at `/hivelog/apiaries/financial-report`,
  as the Net YTD tile's target when the user has more than one apiary
  (one apiary still links to that apiary's own report). Reuses
  `InventoryReportController::computeApiaryYearTotals()` per apiary,
  summed; no new financial logic.

Breakdown (the acceptance criteria of [[0056-dashboard-landing-page]]):

1. **Routing** — land [[0057-dashboard-information-architecture]]:
   `hivelog.dashboard` route at `/hivelog` + an empty
   `DashboardController::view()`; apiary collection → `/hivelog/apiaries`;
   the menu-tree edits; the breadcrumb-root change with its unit-test
   update. No redirect. Depends on nothing; blocks the rest.
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
   list builder. Depends on 2. *(Shipped in 0056, then removed in
   [[0058-dashboard-visual-polish]] as redundant with the Apiaries stat
   tile; the CBR-strip part stands.)*
7. **Dashboard test coverage** — kernel + functional, per the In-scope
   list. Depends on 3–6.

No target release assigned yet — sequence via [[roadmap]].

## Decisions
All six open questions resolved 2026-09-07; carried into
[[0056-dashboard-landing-page]].

1. **Single- vs multi-apiary emphasis — optimise for single-apiary.**
   Most beekeepers run one apiary (the 31-entry calendar is seeded per
   apiary). The dashboard is designed for that case; multi-apiary uses
   the same widgets with per-row apiary attribution, not a separate
   layout. Realised further in [[0058-dashboard-visual-polish]] /
   [[0059-combined-financial-report]]: with exactly one apiary the
   Apiaries and Net YTD tiles skip the intermediate list and link
   straight to that apiary / its financial report.
2. **Route move — adopt `/hivelog/apiaries`, no redirect.** The apiary
   collection moves to `/hivelog/apiaries`. `/hivelog` itself stays a
   valid HiveLog page (now the dashboard, which links to Apiaries
   prominently), so a `/hivelog` bookmark never 404s and there was never
   a distinct apiary-list URL to preserve. The ADR (criterion 1 of 0056)
   ratifies the path, the menu / link-template edits, and the
   breadcrumb-root change.
3. **Net YTD tile — ships in v1.** A sixth stat tile: a straight sum of
   `InventoryReportController::computeApiaryYearTotals()` across the
   apiaries the user can view, one figure, no breakdown. Gated by
   inventory-view access — a user without it simply doesn't get the tile.
   No new financial logic. Drill-down (added [[0059-combined-financial-report]]):
   the tile links to the single apiary's `inventory_cost_report`, or, for
   several apiaries, the combined `hivelog.apiaries.financial_report`.
4. **"Overdue for inspection" — deferred, not in 0056.** Flagging hives
   not inspected in N weeks needs an expected-inspection-interval field
   on `Hive` plus an update hook; that is its own later task. v1's
   "Needs attention" covers overdue / due seasonal actions and low stock
   only.
5. **Recent activity — all five record types, capped per type.**
   Inspections, queen observations, action logs, inventory purchases and
   harvest yields; each capped before the merge, then sliced to ~10.
6. **Permission — reuse the existing OR-set.** No new
   `access hivelog dashboard` permission; the dashboard route keeps the
   `view own apiary + view any apiary + administer hivelog` requirement
   that already guards `/hivelog`. It surfaces nothing the user could not
   already reach.

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
- [[0057-dashboard-information-architecture]] (the `/hivelog` IA change;
  amends [[0013-breadcrumb-policy]]'s root-crumb target)
