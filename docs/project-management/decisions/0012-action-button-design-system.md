---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-06-17
supersedes:
---
# ADR-0012: Action-button design system

## Status
proposed (pending approval)

## Context
Button appearance has two sources of truth that can drift: hex colours + sizing
in `css/hivelog.buttons.css`, and Tailwind utility classes baked into
`components/button/button.twig`. `button-group.twig` further overrides sizing
with `!px-1 !py-0`, so grouped buttons differ from standalone ones. Variant
usage is also inconsistent (some "Add" actions are `primary`, others default).

## Decision (recommended)
Make `css/hivelog.buttons.css` the single source of truth, expressed as CSS
custom-property **tokens** (`--hivelog-btn-*` for bg/fg/border per variant, plus
padding/font-size, including one documented "compact" size). `button.twig`
keeps only the semantic classes (`.button`, `.button--primary`,
`.button--danger`) and references tokens; duplicate colour/size utilities are
removed. Ratify variant semantics: **Add/Save → primary**, **Edit/View →
default**, **Delete → danger**.

## Consequences
- Positive: one consistent button system; grouped and standalone buttons align;
  no `!important` hacks.
- Negative / trade-offs: refactor across CSS + component + callers; needs a
  visual regression check.
- Follow-up tasks: [[0010-define-button-tokens-and-source-of-truth]],
  [[0011-unify-button-group-sizing]], [[0012-audit-action-buttons-across-pages]];
  contrast/tap-target constraints come from [[0014-accessibility-baseline]].
