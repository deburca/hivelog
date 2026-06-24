---
type: project
tags: [hivelog/project]
status: planning
target: 1.4.0
created: 2026-06-17
---
# Project: Action Button Consistency

## Goal
Establish a single, canonical system for action-button **colour** and **size**
so that Add / Edit / Delete / Filter / Reset / View buttons look and measure the
same on every page, regardless of the active admin theme's CSS framework.

## Scope
- In scope: reconciling the two competing styling sources; defining colour and
  size tokens; unifying grouped vs standalone button sizing; auditing every
  button render site for consistent variant usage and labels.
- Out of scope: adding new button types or icons, and broader responsive work
  (see [[mobile-ux-improvements]]; the two projects meet at tap-target sizing in
  [[0009-mobile-qa-and-tap-targets]]).

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT priority asc, file.name asc
```
Static index (in suggested execution order):
- [[0010-define-button-tokens-and-source-of-truth]] — foundation (do first)
- [[0011-unify-button-group-sizing]]
- [[0012-audit-action-buttons-across-pages]]

## Key findings (from code scan, 2026-06-17)
- **Two sources of truth** for button appearance:
  1. `css/hivelog.buttons.css` — hex colours (`#2563eb` primary, `#dc2626`
     danger, `#f3f4f6` default), `padding: 0.45em 1em`, `font-size: 0.9rem`,
     scoped to context wrappers (`.hivelog-list-heading`,
     `.hivelog-filter-form__actions`, `.hivelog-*-actions`, `.form-actions`).
  2. `components/button/button.twig` — framework utility classes
     (`px-3 py-1.5 rounded text-sm`, `bg-blue-600` / `bg-red-600`) emitted
     alongside Drupal's `.button`, `.button--primary`, `.button--danger`.
  These can drift independently (e.g. CSS `0.45em 1em` vs utility `px-3 py-1.5`).
- **Grouped buttons differ from standalone**: `button-group.twig` injects
  `text-sm !px-1 !py-0`, so View/Edit/Delete in tables are smaller than the
  Add/Edit buttons in headings.
- **Variant usage is inconsistent**: in `src/Controller/HiveController.php`,
  "Add Inspection" and "Add Queen" use `variant: primary`, but "Edit Queen" and
  "Add Observation" use the default variant — so not all "Add" actions look
  alike. The danger variant selector list in `hivelog.buttons.css` also omits
  `.hivelog-list-heading` and `.hivelog-filter-form__actions`.
- Action links also originate from `hivelog.links.action.yml` (Add Apiary, Add
  Queen), which render via Drupal's local-action theming, not the SDC — another
  surface to reconcile.

## Open questions
- No major design questions remain: [[0012-action-button-design-system]] already
  settled the source of truth, compact sizing, and variant semantics.
- Remaining work is implementation, audit, and regression checking.

## Related decisions
- [[0012-action-button-design-system]]
- [[0014-accessibility-baseline]]
