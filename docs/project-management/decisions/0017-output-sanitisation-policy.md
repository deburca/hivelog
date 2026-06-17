---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0017: Output sanitisation / XSS policy

## Status
accepted

## Context
The `entity-table` SDC renders each cell with `{{ cell|raw }}`
(`components/entity-table/entity-table.twig`). Controllers populate those cells
with a mix of `(string)`-cast field values and pre-rendered markup (e.g.
`HiveController` renders a button-group via `renderInIsolation()` into a cell).
There is also some `#markup` usage. `|raw` disables Twig autoescaping, so any
unsafe content reaching a cell becomes an XSS vector.

## Decision (recommended)
Treat `|raw` cells as a contract: callers MUST pass already-safe values — either
markup produced by the render system (`renderInIsolation()` / safe strings) or
output escaped via `Html::escape()` / `t()` with placeholders. Never pass raw
user input to `|raw` or `#markup`. Document this contract in
`entity-table.component.yml`, and prefer render arrays over hand-built HTML where
practical.

## Consequences
- Positive: closes the main stored-XSS surface; explicit, reviewable contract.
- Negative / trade-offs: callers carry the sanitisation responsibility; needs
  reviewer vigilance.
- Follow-up tasks: covered during the render-site inventory in
  [[0012-audit-action-buttons-across-pages]]; relates to
  [[0005-sdc-component-library]].
