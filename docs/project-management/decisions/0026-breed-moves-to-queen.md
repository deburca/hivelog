---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-08-19
supersedes:
---
# ADR-0026: Bee breed is a queen attribute, not a hive attribute

## Status
accepted

## Context
`Hive::bee_breed` and `Queen::breed` have coexisted since the Queen entity
was split out of Hive (`hivelog_update_10008`, ADR referenced in
`AGENTS.md`): two independently-editable `list_string` fields with an
identical allowed-value list (`buckfast`, `carniolan`, `italian`,
`caucasian`, `amm`, `other`), with nothing keeping them in sync.

Biologically, breed is a property of the queen: a Buckfast queen can be
introduced into any hive regardless of what was there before, and a hive's
effective breed is really "whichever breed the current queen is." Modeling
breed on the hive let it drift out of sync with the queen actually
occupying it — the exact kind of duplication `Hive::getActiveQueen()` was
introduced to avoid for other queen attributes (`queen_year`,
`queen_colour`).

A hive may go without an active queen for a period (the previous queen is
marked `inactive` rather than deleted, until a replacement is introduced).
During that window a hive has no resolvable breed — this is treated as a
normal, transient empty state (blank in lists/filters), not something
requiring a fallback or a migration of old `bee_breed` values onto a
synthetic queen record.

## Decision
Remove `bee_breed` from `Hive`. Breed is read exclusively from
`Hive::getActiveQueen()->breed` wherever it was previously read directly
off the hive (hive list, apiary hive table + its breed filter, hive detail
page's queen summary). No data migration, consistent with
`hivelog_update_10008`'s precedent — the module is not yet in production.

## Consequences
- Positive: single source of truth for breed; a hive's displayed breed is
  always consistent with its current queen without any sync code.
- Negative / trade-offs: resolving breed now requires a query per hive
  (`getActiveQueen()`) instead of a plain field read, and the apiary hive
  table's breed filter now resolves matching hive ids via a queen-entity
  subquery (`ApiaryController::hiveIdsForActiveQueenBreed()`) rather than a
  direct field condition. Any `bee_breed` values already entered on
  existing hives (pre-production data) are lost rather than migrated.
- Follow-up tasks: none currently planned. Colony `temperament` has the
  same duplication shape (Hive and Queen each carry their own), but that
  divergence is intentional — a hive/colony can be calm even when its queen
  is temperamentally difficult — so it is explicitly out of scope here.
