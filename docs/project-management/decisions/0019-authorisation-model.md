---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0019: Apiary-scoped authorisation model

## Status
accepted (ratifies existing practice)

## Context
`Apiary` implements `EntityOwnerInterface` (owner `uid`), carries a `beekeepers`
multi-value user reference, and a `visibility` field (`private` default /
`public`). `ApiaryAccessTrait` resolves the governing apiary by walking the
reference chain (hive → apiary, inspection → hive → apiary, queen → hive →
apiary, observation → queen → hive → apiary) and enforces:
- **view**: site-wide "any" OR (apiary member AND "own") OR (public apiary AND "own").
- **update**: site-wide "any" OR (apiary member AND "own").
- **delete**: hives — apiary owner only (or "any"); inspections/observations —
  apiary owner OR the record's creator.
`administer hivelog` bypasses all checks. Permissions follow the
`view/edit/delete own|any <entity>` pattern.

## Decision
This apiary-scoped **owner + beekeeper-membership + visibility** model is the
canonical authorisation model. Every entity derives access from its parent
apiary via `ApiaryAccessTrait`; new entities, routes, and queries must follow it
rather than inventing parallel access logic.

## Consequences
- Positive: coherent multi-beekeeper model with a single chokepoint
  (`ApiaryAccessTrait`).
- Negative / trade-offs: access depends on an intact reference chain — a broken
  chain yields neutral/deny; membership is manual.
- Follow-up tasks: enforced at the edges by [[0020-access-parity-custom-routes]],
  refined by [[0021-field-level-access]], and operated per
  [[0022-authentication-and-membership]].
