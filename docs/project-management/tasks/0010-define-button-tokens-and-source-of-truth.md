---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[action-button-consistency]]"
area: theme
created: 2026-06-17
branch: feature/0010-define-button-tokens-and-source-of-truth
release: v1.4.0
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

## Decision to make
Pick the authoritative layer and demote the other:
- **Option A — CSS file is canonical.** Keep semantic classes
  (`.button--primary` etc.) in `button.twig`, strip the colour/size **utility**
  classes, and define everything (incl. tokens) in `css/hivelog.buttons.css`.
  Framework-independent; matches the file's stated purpose ("regardless of the
  active admin theme").
- **Option B — component utilities are canonical.** Remove colour/size from the
  CSS file and rely on the theme build to compile the utility classes. Lighter
  CSS, but depends on the host theme scanning `components/`.
Recommendation: **Option A** + CSS custom properties as tokens.

## Acceptance criteria
- [ ] ADR under `../decisions/` recording the chosen source of truth.
- [ ] Colour + size **tokens** defined once (proposal: `--hivelog-btn-*` custom
      properties for bg/fg/border per variant, plus padding/font-size).
- [ ] `button.twig` and `css/hivelog.buttons.css` no longer define the same
      property twice; one references the tokens, the other is trimmed.
- [ ] Primary / default / danger render identically to today on a default admin
      theme (visual regression check) — this task standardizes, it does not
      restyle.

## Implementation notes
- `button.component.yml` documents the `variant` prop (`default|primary|danger`)
  and `extra_classes`; keep that contract stable for callers.
- Note the danger selector group in `css/hivelog.buttons.css` omits
  `.hivelog-list-heading` and `.hivelog-filter-form__actions` — fold that gap
  into the token refactor.

## Dependencies
- Blocks: [[0011-unify-button-group-sizing]], [[0012-audit-action-buttons-across-pages]].

## Related
- Project:: [[action-button-consistency]]
- Decisions:: new button-source-of-truth ADR (to be created)
- Commits:: 
