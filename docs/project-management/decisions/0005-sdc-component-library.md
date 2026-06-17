---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0005: Build shared UI as SDCs emitting multi-framework classes

## Status
accepted (ratifies existing practice)

## Context
Reusable UI lives in `components/` as Single Directory Components: `button`,
`button-group`, and `entity-table`. Their `.twig` files deliberately emit
Drupal core classes (`.button`, `.button--primary`), Bootstrap classes, and
Tailwind/DaisyUI utilities together so the module renders acceptably regardless
of which CSS framework the active admin theme uses
(`button.component.yml` documents this intent).

## Decision
New shared UI is built as SDCs with documented `props` contracts. Components emit
a framework-agnostic class set; module-owned CSS in `css/*.css` provides
theme-independent fallback styling so appearance does not depend on a host theme
build. Components are referenced via `#type => 'component'` render elements.

## Consequences
- Positive: portable, theme-independent UI; reusable building blocks.
- Negative / trade-offs: the same visual property can be defined in both the
  component utilities and the CSS file and drift apart — the core problem behind
  [[action-button-consistency]].
- Follow-up tasks: [[0012-action-button-design-system]] (this resolves the
  drift), [[0005-responsive-entity-list-tables]],
  [[0017-output-sanitisation-policy]] (the `entity-table` `cell|raw` contract).
