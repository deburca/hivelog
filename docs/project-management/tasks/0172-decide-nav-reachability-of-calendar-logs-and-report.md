---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Inventory how a user reaches each of: Hive Action Logs, Apiary Action
      Logs, per-apiary Calendar, Full Calendar, Calendar Actions, the
      financial report. Record each path and whether it is discoverable.
- [ ] For each, decide: add a nav entry (and under which parent), add a
      cross-link on a specific page, or confirm the current route is
      sufficient.
- [ ] Record the outcome. If it changes the nav hierarchy, write an ADR (next
      free number) amending [[0104-two-tier-in-app-navigation]]; otherwise a
      short note in the implementation notes is enough.
- [ ] Implement whatever the decision calls for, or spin out a task per
      change.
- [ ] If any new nav items are added, `hook_hivelog_app_nav_items()`
      documentation and AGENTS.md's nav section are updated.
- [ ] phpcs clean; phpstan clean (if code changes).

## Implementation notes
- Page-scoped routes can't be nav entries without a chosen context apiary —
  the decision probably favours cross-links over nav items for these.
- Coordinate with [[0168-action-log-list-filters]]: action log lists
  becoming more useful raises the discoverability question.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0160-financial-report-crosslink-on-products-list]], [[0168-action-log-list-filters]]
- Commits::
