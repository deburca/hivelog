---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-06-17
supersedes:
---
# ADR-0022: Authentication & membership model

## Status
proposed (pending approval)

## Context
Hivelog relies on Drupal core authentication; it defines no auth mechanism of
its own. "Membership" of an apiary is the owner plus users listed in the
`beekeepers` reference field (managed today via an entity-reference autocomplete
on the apiary form). There is no self-service invite/registration flow, and no
REST/JSON API surface (the proposed CSV export is an authenticated page route).

## Decision (recommended)
Continue to rely on core authentication and account management. Apiary
membership is granted by the owner editing the `beekeepers` field; adding a user
grants them "own"-scoped access under [[0019-authorisation-model]]. Defer
self-service invitations as a future enhancement. If a programmatic API
(REST/JSON:API) is ever added, it must require authenticated requests, reuse the
same access checks ([[0020-access-parity-custom-routes]]), and get its own ADR.

## Consequences
- Positive: minimal moving parts; leverages battle-tested core auth; no bespoke
  credential handling.
- Negative / trade-offs: membership management is manual and owner-driven; no
  onboarding flow yet.
- Follow-up tasks: operates [[0019-authorisation-model]]; any future API work
  pairs with [[0020-access-parity-custom-routes]] and a new ADR.
