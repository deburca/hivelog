---
type: decision
tags: [hivelog/decision]
date: 2026-09-07
supersedes:
---
# ADR-0057: Dashboard landing page & `/hivelog` information architecture

## Status
accepted

## Context
The review [[navigation-and-page-layout]] found (finding 1) that `/hivelog`
is a bare apiary table with no at-a-glance "what needs doing" view — every
time-sensitive signal the module already computes (overdue / due seasonal
actions, low stock, the current ISO week, recent records) lives one apiary
deep and one hive deep. The [[dashboard-landing-page]] project proposes
replacing that table with an operational dashboard and resolved six open
questions on 2026-09-07. Five of those (single-apiary emphasis, the Net
YTD tile, deferring inspection-cadence flagging, the recent-activity
record set, reusing the existing permission) are product / scope calls
recorded in the project note. This ADR ratifies the one with
architectural reach: the routing / information-architecture change.

Forces in play:

- `entity.apiary.collection` currently owns `/hivelog` — that is its
  `collection` link template — and is the fixed target of the breadcrumb
  builder's "HiveLog" root crumb ([[0013-breadcrumb-policy]]).
- The whole HiveLog menu tree lives in the site's front-end `main` menu,
  parented on the `hivelog.admin` link (route `entity.apiary.collection`)
  — see `AGENTS.md`.
- `HivelogBreadcrumbBuilder::applies()` matches a `hivelog.` route-name
  prefix catch-all, so a new `hivelog.dashboard` route is picked up
  automatically and must render a sensible trail (or be excluded).
- Canonical / landing pages are custom controllers, not view builders
  ([[0004-custom-controllers-over-view-builders]]); render cacheability is
  assembled explicitly ([[0009-render-cacheability-discipline]]).
- No dedicated URL for the apiary *list* exists other than `/hivelog`
  itself, so there is nothing list-specific that could already be
  bookmarked at a distinct path.

## Decision
1. **New route `hivelog.dashboard` at `/hivelog`**, served by a new
   `DashboardController::view()` (custom controller, per
   [[0004-custom-controllers-over-view-builders]]). It aggregates across
   every apiary the user can view and renders the six widgets described in
   [[dashboard-landing-page]].
2. **The apiary collection moves to `/hivelog/apiaries`.** Change the
   `Apiary` entity's `collection` link template from `/hivelog` to
   `/hivelog/apiaries`; the `entity.apiary.collection` route,
   `ApiaryListBuilder`, its filter form and its "Add Apiary" action are
   otherwise unchanged. The CBR summary block moves off the list builder
   and onto the dashboard header.
3. **No redirect.** `/hivelog` stays a valid, useful HiveLog page — it
   renders the dashboard now, which links prominently to Apiaries. Since
   no URL was ever dedicated to the apiary list, nothing bookmarkable
   404s; a `RedirectResponse` from an old path is unnecessary.
4. **Menu tree** (`hivelog.links.menu.yml`): repoint the top-level
   `hivelog.admin` item at `hivelog.dashboard` (title stays "HiveLog"),
   and add a new first child `hivelog.apiaries` → `entity.apiary.collection`
   (title "Apiaries", weight ordered above the existing children). Hives,
   Inspections, Queens, Queen Observations, Inventory Items, Inventory
   Purchases and Products are unchanged.
5. **Breadcrumb root.** In `HivelogBreadcrumbBuilder::build()` the fixed
   second crumb ("HiveLog") links to `hivelog.dashboard` instead of
   `entity.apiary.collection`. On the dashboard route itself the trail is
   `Home › HiveLog` with "HiveLog" as the non-linked terminal crumb, per
   [[0013-breadcrumb-policy]] rule 2. `applies()` already matches
   `hivelog.dashboard` via the `hivelog.` prefix and it must **not** be
   added to the non-page exclusion list. The apiary collection page's
   trail becomes `Home › HiveLog › Apiaries`.
6. **Permission.** `hivelog.dashboard` reuses the existing
   `_permission: 'view own apiary+view any apiary+administer hivelog'`
   requirement that guards `entity.apiary.collection` today — no new
   permission (project decision 6). It surfaces nothing the user could not
   already reach.
7. **Cache metadata** is assembled per
   [[0009-render-cacheability-discipline]]: `user.permissions` + `user`
   contexts; list cache tags for every entity type surfaced (hive,
   hive_inspection, queen, queen_observation, calendar_action,
   hive_action_log, apiary_action_log, inventory_item, inventory_purchase,
   inventory_usage, harvest_yield, product); per-row entity dependencies;
   and `max-age = secondsUntilNextIsoWeek()` — the dashboard prints the
   current ISO week and computes row timing against it, exactly as
   `ApiaryController` / `HiveController` already do for their calendar
   sections.

This ADR **amends** [[0013-breadcrumb-policy]] on a single point — the
root-crumb target — and leaves the rest of that policy intact. It does not
supersede it.

## Consequences
- Positive:
  - `/hivelog` becomes an operational home rather than a flat list; the
    signals the module already computes are surfaced without drilling into
    each apiary.
  - Minimal new architectural surface: one custom controller in the
    established mould, one new SDC (`stat-tile`), one CSS library. **No**
    new entity, schema, permission, or update hook.
  - The apiary list keeps its own URL (`/hivelog/apiaries`) and every
    feature it has today; it just stops being the landing page.
  - The `hivelog.` prefix catch-all in `applies()` means the breadcrumb
    "just works" for the new route with no `applies()` change.
- Negative / trade-offs:
  - `/hivelog` changes what it shows. Anyone with muscle memory for "the
    apiary list at `/hivelog`" now lands on the dashboard and clicks
    through to Apiaries — a one-time adjustment, accepted rather than
    softened with a redirect.
  - The breadcrumb-root change touches `HivelogBreadcrumbBuilder` and
    `HivelogBreadcrumbBuilderTest` — a small, well-covered edit to a
    shared file.
  - `hivelog.admin`'s route target moving means any external deep link to
    the *menu item* (rare) now resolves to the dashboard rather than the
    apiary list.
  - The dashboard's cross-apiary aggregation is more read work per request
    than the old single-table list builder — bounded by `->count()`
    queries and per-type caps, and cache-keyed to invalidate only on real
    change, but not free.
- Follow-up tasks: [[0056-dashboard-landing-page]] — criterion 1
  implements this ADR (routes, menu, `Apiary` link template, breadcrumb
  root + test); criteria 2–8 build the widgets. Narrows
  [[0025-seasonal-calendar-and-hive-action-tracking]]'s "no
  reminders / notifications" position: still no *push*, but overdue / due
  items are now surfaced on an opened page.
