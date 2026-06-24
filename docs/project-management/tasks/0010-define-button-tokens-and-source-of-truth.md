---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[action-button-consistency]]"
area: theme
created: 2026-06-17
branch: feature/0010-define-button-tokens-and-source-of-truth
release: 1.4.0
---
# Task: Define button tokens & single source of truth

## Context
Button appearance is defined in **two** places that can drift apart:
1. `css/hivelog.buttons.css` — hex colours (`#2563eb` primary, `#dc2626`
   danger, `#f3f4f6`/`#222` default), `padding: 0.45em 1em`,
   `font-size: 0.9rem`, applied via context-wrapper selectors.
2. `components/button/button.twig` — utility classes baked into the markup:
   `px-3 py-1.5 rounded text-sm`, `bg-blue-600`/`bg-red-600`, emitted next to
   Drupal's semantic `.button`, `.button--primary`, `.button--danger`.
Today the colours happen to coincide (`#2563eb` ≈ `bg-blue-600`) but nothing
keeps them in sync. Foundation task for [[action-button-consistency]].

## Decision already made
[[0012-action-button-design-system]] already settled the design direction:
**Option A** — `css/hivelog.buttons.css` is canonical, expressed as CSS custom
properties/tokens; `button.twig` keeps semantic classes and drops duplicate
colour/size utilities.

## Acceptance criteria
- [x] Implementation aligns with accepted ADR [[0012-action-button-design-system]].
- [x] Colour + size **tokens** defined once (`--hivelog-btn-*` custom properties
      for bg/fg/border per variant, plus padding/font-size, plus compact-padding
      for grouped buttons) — in `css/hivelog.responsive.css` alongside the
      existing breakpoint/spacing tokens.
- [x] `button.twig` and `css/hivelog.buttons.css` no longer define the same
      property twice — `button.twig` emits only semantic/interop class names;
      all colour and size values live in the CSS file via `var()` tokens.
- [x] Primary / default / danger render identically to before on a default admin
      theme (desktop visual regression) — this task standardizes, it does not
      restyle.

## Implementation notes
- Tokens live in `css/hivelog.responsive.css` (`:root` block) alongside the
  existing breakpoint/spacing tokens — consistent with how the module centralises
  all shared `:root` vocabulary.
- `css/hivelog.buttons.css` now uses `:is()` to collapse the seven repeated
  context-wrapper selector lists into one rule block per state.
- The danger selector gap (`.hivelog-list-heading` and
  `.hivelog-filter-form__actions` were previously missing from danger rules)
  is fixed — all seven wrappers are now covered for every variant.
- `button.twig` retains `btn`, `btn-primary`, `btn-danger`, `btn-default`,
  `join-item`, and the layout/behaviour utilities (`inline-flex items-center
  no-underline cursor-pointer transition-colors`). All colour and size utilities
  (`bg-*`, `text-*`, `border-*`, `hover:*`, `px-*`, `py-*`, `rounded`,
  `text-sm`, `font-medium`) are removed.
- `button-group.twig` no longer passes `extra_classes: "text-sm !px-1 !py-0"`.
  Compact sizing is now handled by the `.hivelog-button-group .button` rule in
  `css/hivelog.buttons.css` using `--hivelog-btn-compact-padding-*` tokens.
- `button.component.yml` contract (`variant`, `extra_classes` props) is
  unchanged; all callers are unaffected.
- CBR summary link colour in `hivelog.buttons.css` updated from hardcoded
  `#2563eb` to `var(--hivelog-btn-primary-bg)` so it tracks the token.

## Dependencies
- Blocks: [[0011-unify-button-group-sizing]], [[0012-audit-action-buttons-across-pages]].

## Related
- Project:: [[action-button-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Commits:: (implementation — css/hivelog.responsive.css, css/hivelog.buttons.css, components/button/button.twig, components/button-group/button-group.twig)
