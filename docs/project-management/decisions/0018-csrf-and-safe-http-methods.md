---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0018: CSRF protection & safe HTTP methods

## Status
accepted

## Context
State-changing operations today go through Drupal's Form API (entity add/edit
and the `*DeleteForm` confirmations), which carry CSRF protection automatically.
The "scoped add" routes render forms. The proposed CSV export
([[0001-queen-observation-csv-export]]) is a read-only `GET`. No custom
GET-triggered mutations exist — and none should be introduced without protection.

## Decision (recommended)
Any state-changing custom route must use `POST` with a `_csrf_token` requirement
(or go through the Form API); `GET` routes must remain side-effect-free and
idempotent (the CSV export qualifies). Continue relying on core form CSRF for
entity forms; never perform writes from a link/`GET`.

## Consequences
- Positive: protects against CSRF and accidental state change via crawlers/links.
- Negative / trade-offs: future action-link style features need explicit token
  handling rather than a bare link.
- Follow-up tasks: constrains [[0001-queen-observation-csv-export]] (keep it a
  safe GET); reinforced by [[0020-access-parity-custom-routes]].
