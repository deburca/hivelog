---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0121-reachability-of-orphaned-collection-pages
release:
depends-on:
blocked-by:
---
# Task: Decide on and fix collection pages with no way in

## Context
From the navigation and breadcrumb review of 2026-09-23. Four routed
list pages under `/hivelog` are missing from the nav strip and the
menu. Two of them can't be reached from anywhere in the UI:

| Page | Route | Linked from |
|---|---|---|
| `/hivelog/hive-action-logs` | `entity.hive_action_log.collection` | **nowhere** |
| `/hivelog/apiary-action-logs` | `entity.apiary_action_log.collection` | **nowhere** |
| `/hivelog/calendar-actions` | `entity.calendar_action.collection` | dashboard stat tile; its own filter form |
| `/hivelog/apiaries/financial-report` | `hivelog.apiaries.financial_report` | dashboard stat tile (0 or 2+ apiaries); the per-apiary report |

Each has a breadcrumb entry and access checks, so they are maintained
pages. Either they are wanted and need a way in, or they are not and
should go. **This needs a product decision before any code is
written**, hence `backlog`.

## Acceptance criteria
- [x] Decision recorded here (Implementation notes) for each of the four
      pages. Options per page:
      (a) add to the nav strip via [[0119-single-source-navigation-registry]]
      in an appropriate group;
      (b) link from a more natural parent page, e.g. action logs from
      the apiary / hive page's calendar section, or all-apiaries Calendar
      Actions and the Financial Report from the dashboard only, as today;
      (c) remove the route and list builder because nothing needs it.
- [x] Chosen option implemented. For (c): route, list-builder handler
      (keeping the entity type), `$collections` entry and any tests
      removed together. For (a) / (b): link visible to users with the
      route's permission and hidden otherwise (`Url::access()`).
- [x] No hivelog collection route is left with no inbound link unless
      that is recorded here as intentional.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Decisions (confirmed with the user, 2026-09-26):**

| Page | Decision |
|---|---|
| Calendar Actions (`/hivelog/calendar-actions`) | **Keep the skip.** Stays reachable only via the dashboard stat tile and its own filter form, as today — no nav strip entry. |
| Financial Report (`/hivelog/apiaries/financial-report`) | **Keep dashboard-only**, as the task's own suggested default. No new link added. |
| Hive Action Logs (`/hivelog/hive-action-logs`) | **Link from the hive page's Seasonal Calendar section** — a new "View all Logs" button. |
| Apiary Action Logs (`/hivelog/apiary-action-logs`) | **Link from the apiary page's Seasonal Calendar section** — a new "View all Logs" button. |

Only the two action-log pages needed code — Calendar Actions and the
Financial Report keep their exact current reachability, recorded here
per the AC's own "unless recorded here as intentional" clause. No
route, list builder, or `$collections` entry was removed for any of
the four; option (c) wasn't chosen anywhere.

**Implementation.** `HiveController::view()`'s and `ApiaryController::view()`'s
`calendar_heading` sections each gained a `view_logs` button
(`entity.hive_action_log.collection` / `entity.apiary_action_log.collection`)
inside their existing `actions` sub-container — not a new top-level
child of the heading, since `.hivelog-list-heading` is styled for
exactly two children (`justify-content: space-between`); a third
top-level child would have broken that layout. `ApiaryController`
already wrapped its two buttons this way, so its heading just gained a
third; `HiveController`'s previously had a single unwrapped button
(`extra_classes` directly on it) and was refactored into the same
`actions`-wrapper shape.

Per the AC's own "link visible to users with the route's permission
and hidden otherwise" requirement, both buttons are gated on
`Url::fromRoute(...)->access()` before being added to the render array
— unlike "View Full Calendar" or the pre-existing "View all Queens"
link, neither of which is access-gated today (a pre-existing gap this
task doesn't extend to fixing). Both pages already carry the
`user.permissions` cache context broadly (confirmed in their existing
cache-metadata blocks), so the new conditional needs no cache-metadata
change of its own.

**Test gotcha worth recording**: a first attempt at the "hidden without
access" kernel tests failed unexpectedly — the fresh, no-role test user
still had access. Root cause: Drupal core treats uid 1 as an implicit
superuser regardless of its roles/permissions, and `HiveTest` (unlike
`ApiaryTest`, whose `setUp()` already creates `$this->user` first for
what turns out to be exactly this reason) creates no user in `setUp()`
— so the first user any `HiveTest` method creates becomes uid 1.
Fixed by creating and discarding a throwaway placeholder user first in
both new `HiveTest` methods, matching `ApiaryTest`'s own established
pattern; also applied to the "with access" test so it genuinely
exercises the granted permission rather than an accidental superuser
bypass.

**Verification.** phpcs clean; phpstan clean (module-wide, no baseline
changes). New tests: `HiveTest::testHiveViewCalendarHeadingLinksToLogsWithAccess`
/ `...HidesLogsLinkWithoutAccess`, `ApiaryTest::testApiaryViewCalendarHeadingLinksToLogsWithAccess`
/ `...HidesLogsLinkWithoutAccess`. Full kernel/unit/functional suite
against `cms2`, core and every submodule: green (see this task's own
commit for the exact count). Live-verified on `cms2`
(`kragebaekgaard.ddev.site`): "View all Logs" renders correctly on
both a real apiary page and a real hive page, in the expected
position alongside the existing calendar buttons.

- Key files: `src/Controller/HiveController.php`,
  `src/Controller/ApiaryController.php`,
  `tests/src/Kernel/HiveTest.php`, `tests/src/Kernel/ApiaryTest.php`.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0057-dashboard-information-architecture]]
- Tasks:: [[0119-single-source-navigation-registry]],
  [[0120-app-nav-active-state-and-grouping]]
- Commits::
