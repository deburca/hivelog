---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0004: Render composed canonical pages via custom controllers

## Status
accepted (ratifies existing practice)

## Context
Apiary, Hive, and HiveInspection canonical pages are rendered by custom
controllers (`src/Controller/*Controller.php`) rather than the default entity
view builder, because each page composes child content: the apiary shows its
hives, the hive shows its queen section, a weight histogram, a filtered +
paginated inspection list, and an image grid (see `HiveController::view()`).

## Decision
Use a custom controller when a canonical page must embed child list builders or
derived widgets; use the default entity view for simple entities. Controllers
assemble render arrays with explicit `#weight` ordering and own their
cacheability (see [[0009-render-cacheability-discipline]]). Parent references on
new children are set via the "scoped add" routes / `addForm()` methods.

## Consequences
- Positive: rich, purpose-built pages; full control over composition and order.
- Negative / trade-offs: more code than default rendering; each controller must
  manage cache metadata and access itself.
- Follow-up tasks: interacts with [[0005-responsive-entity-list-tables]] and the
  button audit in [[0012-audit-action-buttons-across-pages]]; access parity in
  [[0020-access-parity-custom-routes]].
