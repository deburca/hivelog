---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0013: Breadcrumb policy

## Status
accepted

## Context
`HivelogBreadcrumbBuilder` builds Home → HiveLog → ancestry trails for all
entity routes plus a `hivelog.` prefix catch-all in `applies()`. Ancestry is
threaded only when route parameters are upcast to entity objects
(`is_object()` guards). Canonical pages currently omit a trailing self-link.
There is no written policy, so future routes may render inconsistently.

## Decision (recommended)
Codify the rules: (1) trails are Home → HiveLog → Apiary → Hive → … built from
upcast ancestor entities; (2) the current entity's label is always appended as
the terminal crumb on every route — the theme renders the last link as plain
text (`aria-current="page"`), so ancestors are naturally clickable and the
current page label is non-linked without any special suppression logic in the
builder; (3) `applies()` excludes non-page `hivelog.*` routes (e.g. file
exports); (4) `applies()` must be kept in sync with `hivelog.routing.yml`
whenever routes change (per `AGENTS.md`).

## Consequences
- Positive: predictable, correct trails on every page; clear rule for new routes.
- Negative / trade-offs: `applies()` is a maintenance touch-point that must track
  routing.
- Follow-up tasks: [[0013-breadcrumb-route-audit]],
  [[0014-implement-breadcrumb-consistency-fixes]],
  [[0015-breadcrumb-test-coverage]]; reconciles task
  [[0002-breadcrumb-queen-canonical]] (already implemented).
