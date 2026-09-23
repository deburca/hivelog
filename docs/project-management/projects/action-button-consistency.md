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
- [[0113-destructive-action-styling-sensor-device-api-client]] —
  follow-up: nanoprobe/collective canonical-page buttons shipped after
  the 0012 audit closed, and were rendering completely unstyled

## Key findings (from code scan, 2026-06-17; updated 2026-06-26)

### Resolved by task 0010 and task 0011
- **Two sources of truth** — resolved. `css/hivelog.buttons.css` is now the
  sole styling authority (ADR-0012). All theme framework classes (`btn`,
  `btn-primary`, `btn-danger`, `btn-default`, `join-item`, Tailwind utilities)
  have been removed from `button.twig`; it emits only semantic class names
  (`button`, `button--primary`, `button--danger`, `button--default`).
- **Grouped buttons differing from standalone** — resolved. The `!important`
  overrides are gone. Compact sizing is now documented and token-driven
  (ADR-0024). Join-corner styling (segmented control appearance) is implemented
  in plain CSS using `:first-child`/`:last-child` selectors and `margin-left:
  -1px` border collapse.
- **`.hivelog-button-group` not a context wrapper** — resolved. Added as the
  eighth context wrapper in all `:is()` rule blocks so grouped buttons receive
  correct token-based colour styling.

### Still outstanding (for task 0012)
- **Variant usage is inconsistent**: in `src/Controller/HiveController.php`,
  "Add Inspection" and "Add Queen" use `variant: primary`, but "Edit Queen" and
  "Add Observation" use the default variant — so not all "Add" actions look
  alike.
- Action links from `hivelog.links.action.yml` (Add Apiary, Add Queen) render
  via Drupal's local-action theming, not the SDC — another surface to reconcile.

## Key findings (2026-09-23)
- The `:is()` context-wrapper allow-list in `hivelog.buttons.css` is an
  easy silent-failure trap: a button with fully correct `button`/
  `button--*` classes (or built via the `hivelog:button` SDC component)
  renders with **zero** styling if its container isn't wrapped in one
  of the registered classes — no fallback, no visual hint anything is
  wrong, so it can go unnoticed until someone looks closely. Found on
  two canonical pages (`SensorDeviceController`, `ApiClientController`)
  that shipped after [[0012-audit-action-buttons-across-pages]] closed;
  fixed in [[0113-destructive-action-styling-sensor-device-api-client]]
  by registering their action containers as new context wrappers,
  matching this project's own established extension pattern rather
  than inventing a new one. Worth a standing check whenever
  nanoprobe/nexus/collective ship a new canonical-page action, not
  just a one-time audit.

## Open questions
- No major design questions remain: [[0012-action-button-design-system]] already
  settled the source of truth, compact sizing, and variant semantics.
- Remaining work is implementation, audit, and regression checking.

## Related decisions
- [[0012-action-button-design-system]]
- [[0014-accessibility-baseline]]
