---
type: task
tags: [hivelog/task]
status: done
priority: low
project:
area: navigation
created: 2026-10-02
branch: feature/0172-decide-nav-reachability-of-calendar-logs-and-report
release:
depends-on:
blocked-by:
---
# Task: Decide how Calendar, action logs and the financial report are reached

## Context
Found in the 2026-10-02 review of [[in-app-navigation-restructuring]]
(done). `HivelogAppNavBuilder::builtInItems()` registers Apiaries, Hives,
Inspections, Queens, Queen Observations, Inventory Items, Inventory
Purchases, Products and Insights. These pages have no nav entry and hang off
other pages instead: the Hive and Apiary action log lists, the apiary-scoped
Calendar pages and the combined financial report
(`hivelog.apiaries.financial_report`). Task 0120 excluded the financial
report deliberately and task [[0160-financial-report-crosslink-on-products-list]]
added a cross-link, but there is no recorded decision covering the other
pages. A beekeeper who knows a log list exists has no consistent way to find
it. This may be exactly right (they are apiary- or hive-scoped, not
site-wide), but it is currently an accident of omission, not a decision.

## Acceptance criteria
- [x] Inventory how a user reaches each of: Hive Action Logs, Apiary Action
      Logs, per-apiary Calendar, Full Calendar, Calendar Actions, the
      financial report. Record each path and whether it is discoverable.
- [x] For each, decide: add a nav entry (and under which parent), add a
      cross-link on a specific page, or confirm the current route is
      sufficient.
- [x] Record the outcome. If it changes the nav hierarchy, write an ADR (next
      free number) amending [[0104-two-tier-in-app-navigation]]; otherwise a
      short note in the implementation notes is enough.
- [x] Implement whatever the decision calls for, or spin out a task per
      change.
- [x] If any new nav items are added, `hook_hivelog_app_nav_items()`
      documentation and AGENTS.md's nav section are updated.
- [x] phpcs clean; phpstan clean (if code changes).

## Implementation notes
- Page-scoped routes can't be nav entries without a chosen context apiary —
  the decision probably favours cross-links over nav items for these.
- Coordinate with [[0168-action-log-list-filters]]: action log lists
  becoming more useful raises the discoverability question.

## Implementation notes (as built)

### How each page was reached before (audited in code)

| Page | Inbound links before | Discoverable? |
|---|---|---|
| Hive Action Logs list | "View Logs" button on a **Hive** page, only when the viewer can open it | Only if you are on a hive page |
| Apiary Action Logs list | "View Logs" button on an **Apiary** page, same gating | Only if you are on an apiary page |
| Per-apiary Calendar ("Full Calendar") | "View Full Calendar" on the Apiary page and on a Hive page | Yes, from where beekeepers work |
| **Site-wide Calendar Actions list** | **Only** the dashboard "Open seasonal tasks" tile (pre-filtered by week) | **No — the real gap** |
| Combined financial report | Dashboard "Net YTD" tile (when more than one apiary), Products page (task 0160), the per-apiary cost report | Mostly; not from Purchases |
| Per-apiary cost report | Apiary page, dashboard (single apiary), the reports' own links | Yes |

The task guessed that most of these would be fine as they are; that held for
everything except the Calendar Actions list, and for two smaller things found
along the way (below).

### Decisions

- **Calendar Actions list → nav item**, under Apiaries (`group: records`,
  `weight: 5`, `section: calendar_action`). It is a site-wide collection with
  no apiary needed, so unlike the task's assumption it *can* be a nav entry.
  Its breadcrumb now threads `Apiaries › Calendar Actions` (same shape as
  Hives), so the nav and breadcrumb agree. A leaf under an existing hub, not
  a hierarchy change, so a short amendment to ADR-0104 rather than a new ADR.
- **Action logs → not nav items, cross-links instead.** Ten entries in the
  Apiaries dropdown is too many for an audit trail. The three pages now form
  a cluster: Calendar Actions → View Hive Logs / View Apiary Logs; each log
  list → View Calendar Actions and the sibling list. Previously the log lists
  had no heading at all.
- **Per-apiary Full Calendar and cost report → confirmed sufficient.** They
  need a chosen apiary, so they cannot be nav entries, and every apiary and
  hive page already links to them.
- **Financial report → also linked from Inventory Purchases** (the cost side
  of the report), alongside Products.

### Two things found beyond the audit

- **The Products page's "View Financial Report" link (task 0160) wasn't
  access-gated.** The report needs inventory-item view access, which is a
  different permission from viewing products, so a viewer with one but not
  the other was offered a link that returned 403. All the new cross-links go
  through one helper, `accessibleLinkAction()`, which only offers a link the
  target route's own access check passes; Products now uses it too.
- **Cache correctness.** A heading whose buttons depend on who is looking has
  to vary by permissions, or one viewer's links could be served to another.
  The shared heading declares the `user.permissions` context. That is correct
  for permission-gated targets only (all four here are); the helper's
  comment says an entity-access-gated target would need `user`.

### What changed

`HivelogAppNavBuilder` (new item), `HivelogBreadcrumbBuilder`
(`COLLECTION_ANCESTOR_ROUTE`), `HivelogListPageTrait` (new
`buildListHeading()` and `accessibleLinkAction()`; `HivelogListBuilder`'s
inline heading code now calls the first, so the controller-built Calendar
Actions page and the list builders share one heading shape),
`HiveActionLogListBuilder`, `ApiaryActionLogListBuilder`,
`ProductListBuilder`, `InventoryPurchaseListBuilder`,
`CalendarActionController`. AGENTS.md and ADR-0104 updated.
`hook_hivelog_app_nav_items()` is unchanged: this is a core built-in, not a
submodule contribution.

### Tests and verification

- New `ListCrossLinksTest` (6): the log lists' links and their targets; a
  viewer who can see only the log list gets no links, and each added
  permission adds exactly its link; the Calendar Actions page's two links and
  no heading at all when neither target is reachable; the financial report
  link needs inventory-item access (Products and Purchases); the heading
  varies by permissions. Turning off the access check in the cms2 copy
  failed 3 of them.
- Updated: the built-in-items, submenu-order and menu-link tests, the
  breadcrumb data provider (Calendar Actions moves from flat to threaded),
  plus two new nav tests (Calendar Actions is an Apiaries child and marks
  itself active; the action logs are not nav items).
- On `cms2` (`vdg`), rendering real pages as admin through the HTTP kernel:
  Calendar Actions, both log lists, Products and Purchases showed exactly the
  expected links with correct targets; the nav listed Calendar Actions after
  Queen Observations and marked it `aria-current` on its own page; its
  breadcrumb was `Home › Apiaries › Calendar Actions`, identical in shape to
  Hives; and the derived main-menu link
  `hivelog.nav_item:calendar_actions` exists under Apiaries.
- **Not done:** no click-through in a browser (the pane was logged out and I
  did not sign in), and no real restricted-user page was rendered on `cms2` —
  restricted viewers are covered by the kernel tests only.
- phpcs and phpstan clean; no baseline change.
- **Full suite: 1,206 tests / 20,048 assertions, 0 failures, 0 errors**
  (1,198 before this task + the 8 new: 6 cross-link, 2 nav), only the usual
  third-party deprecations and notices. It took four attempts. A single run
  and then four- and eight-way chunked runs were stopped at the background
  time limit with nothing completed, and the environment had stopped
  answering `ddev exec` altogether; the cause was 727 leftover `test<digits>`
  tables from my earlier killed runs. Drupal's cleanup, run inside the
  container (`run-tests.sh --clean --sqlite /tmp/...` with `SIMPLETEST_DB` set;
  it fails on the host), removed them, leaving the 182 real tables untouched,
  and the suite then ran clean as eight chunks (134–198 tests each) plus
  Functional. The task was committed in `review` before the run finished and
  pushed the same way; this commit flips it to `done`.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0160-financial-report-crosslink-on-products-list]], [[0168-action-log-list-filters]]
- Commits::
