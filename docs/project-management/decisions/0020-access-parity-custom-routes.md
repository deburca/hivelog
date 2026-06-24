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
