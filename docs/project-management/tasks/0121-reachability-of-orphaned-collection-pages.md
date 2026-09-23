---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Decision recorded here (Implementation notes) for each of the four
      pages. Options per page:
      (a) add to the nav strip via [[0119-single-source-navigation-registry]]
      in an appropriate group;
      (b) link from a more natural parent page, e.g. action logs from
      the apiary / hive page's calendar section, or all-apiaries Calendar
      Actions and the Financial Report from the dashboard only, as today;
      (c) remove the route and list builder because nothing needs it.
- [ ] Chosen option implemented. For (c): route, list-builder handler
      (keeping the entity type), `$collections` entry and any tests
      removed together. For (a) / (b): link visible to users with the
      route's permission and hidden otherwise (`Url::access()`).
- [ ] No hivelog collection route is left with no inbound link unless
      that is recorded here as intentional.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Suggested starting point, to confirm with the user: Calendar Actions →
  nav strip `records` group (it's a cross-apiary planning view the
  dashboard already treats as first-class). Financial Report → keep
  dashboard-only. Action logs → link from the hive / apiary pages'
  calendar sections ("View all logs"), not the nav strip. They are
  audit trails, not daily destinations.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0057-dashboard-information-architecture]]
- Tasks:: [[0119-single-source-navigation-registry]],
  [[0120-app-nav-active-state-and-grouping]]
- Commits::
