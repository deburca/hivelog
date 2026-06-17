---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0009: Explicit render & access cacheability

## Status
accepted (ratifies existing practice)

## Context
Controllers already declare cacheability carefully. `HiveController::view()` adds
`url.query_args` and `user.permissions` contexts, the hive dependency, and the
inspection/queen list cache tags, plus a per-row dependency for every rendered
inspection. The breadcrumb builder adds the `route` context and per-entity
dependencies. Access results in `ApiaryAccessTrait` call `cachePerPermissions()`
/ `cachePerUser()` and add the apiary as a dependency.

## Decision
Every render array and access result declares correct cacheability: list pages
add list cache tags and per-row cacheable dependencies; anything that varies by
filters/pager uses `url.query_args`; anything gated by ownership/membership uses
`user.permissions` + `user` contexts and depends on the governing apiary. New
controllers, routes, and access handlers must follow this before merge.

## Consequences
- Positive: correct cache invalidation and strong performance; no stale or
  cross-user leakage.
- Negative / trade-offs: easy to forget on a new endpoint; requires reviewer
  attention.
- Follow-up tasks: [[0001-queen-observation-csv-export]] (export must declare
  cacheability/access), reinforced by [[0020-access-parity-custom-routes]].
