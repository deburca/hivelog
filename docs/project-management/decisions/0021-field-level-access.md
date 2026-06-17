---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-06-17
supersedes:
---
# ADR-0021: Field-level access for sensitive fields

## Status
proposed (pending approval)

## Context
Access in [[0019-authorisation-model]] is entity-level: if you may view an
entity, you may view all its fields. Some fields are more sensitive than the
record as a whole — `Queen.purchase_cost` / `purchase_date` (financial), the
`Apiary.beekeepers` and owner (`uid`) lists (personal data), and exact
`Apiary.geolocation` (theft risk per [[0015-apiary-location-privacy]]),
especially on public apiaries visible to non-members.

## Decision (recommended)
Add field-level access (via `hook_entity_field_access` / a field-access service)
so that: financial fields are visible only to apiary members; precise
geolocation is members-only (non-members get the fuzzed view from
[[0015-apiary-location-privacy]]); and the beekeepers/owner lists are restricted
to members. Field access composes with, and never widens, entity access.

## Consequences
- Positive: least-privilege on individual fields; safer public apiaries.
- Negative / trade-offs: more access logic and test surface; must be applied in
  forms, views, and any export.
- Follow-up tasks: depends on [[0019-authorisation-model]]; implements part of
  [[0015-apiary-location-privacy]]; field access must also be honoured by
  [[0001-queen-observation-csv-export]]; tested per [[0008-testing-strategy]].
