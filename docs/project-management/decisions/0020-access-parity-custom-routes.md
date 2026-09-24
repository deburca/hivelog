---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0020: Access parity for custom routes, controllers & queries

## Status
accepted

## Context
Custom controllers and routes can bypass the access handlers if they forget to
check. Good practice already exists — `HiveController` runs its inspection query
with `accessCheck(TRUE)` — but this must be a guarantee, not a habit, especially
for new surfaces like the scoped-add routes and the proposed CSV export
([[0001-queen-observation-csv-export]]), which could otherwise leak rows the
viewer cannot see.

## Decision (recommended)
Every custom controller/route enforces the same access as the entity handlers in
[[0019-authorisation-model]]: use `_entity_access` route requirements where
possible, run entity queries with `accessCheck(TRUE)`, and filter list output to
accessible entities. The CSV export must restrict to rows the user may view and
require the matching `view` permission + apiary membership.

## Consequences
- Positive: no privilege escalation through custom paths; consistent behaviour
  with the UI.
- Negative / trade-offs: explicit checks/tests required on every new endpoint.
- Follow-up tasks: [[0001-queen-observation-csv-export]] (access-filtered
  export); cross-checked by the route audit in [[0013-breadcrumb-route-audit]];
  verified per [[0008-testing-strategy]].

## Addendum (2026-09-23)
The 2026-09-23 gap analysis found that this rule was not actually followed:
every hivelog/nanoprobe/collective/nexus route with an entity parameter
checked only `_permission`, never `_entity_access` — an IDOR, closed by
[[0133-route-level-entity-access]]. `RouteEntityAccessTest` (one copy per
module, in each module's own `tests/src/Kernel/`) now enforces this rule
mechanically: it discovers every route from the built router rather than
a hand-maintained list, asserts every route with an entity parameter
declares `_entity_access` / `_entity_create_access` / `_custom_access`,
and functionally checks that an unrelated "own"-permission user is denied
while the owner and `administer hivelog` are allowed. A new route that
skips the check now fails the test automatically instead of silently
shipping unprotected.
