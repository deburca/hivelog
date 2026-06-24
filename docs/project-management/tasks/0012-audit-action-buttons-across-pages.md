---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[action-button-consistency]]"
area: entity
created: 2026-06-17
branch: feature/0012-audit-action-buttons-across-pages
release: 1.4.0
depends-on: ["[[0010-define-button-tokens-and-source-of-truth]]"]
---
# Task: Audit action buttons across pages

## Context
Even with one styling system ([[0010-define-button-tokens-and-source-of-truth]]),
buttons can still be inconsistent in **which variant and label** each action
uses. A known example: in `src/Controller/HiveController.php`, "Add Inspection"
and "Add Queen" are `variant: primary`, but "Edit Queen" and "Add Observation"
use the default variant — so "Add" actions don't look alike. This task defines
and applies variant/label rules everywhere buttons are emitted. Part of
[[action-button-consistency]].

## Proposed variant rules (to ratify)
- **Add / Save / primary CTA** → `primary`
- **Edit / View / secondary** → `default`
- **Delete / destructive** → `danger`

## Acceptance criteria
- [ ] Inventory of every action-button render site (controllers, list builders,
      form actions, action links) with current vs intended variant/label.
- [ ] Variant rules above ratified (or amended) and applied across the module.
- [ ] "Add Observation" / "Edit Queen" reconciled with the ratified rules.
- [ ] Action links from `hivelog.links.action.yml` (Add Apiary, Add Queen)
      visually match SDC buttons, or the divergence is documented as acceptable.
- [ ] Labels follow one convention (e.g. "Add X" / "Edit X" / "Delete X").

## Implementation notes
- Render sites to audit:
  - Controllers: `ApiaryController`, `HiveController`, `HiveInspectionController`,
    `QueenController`, `QueenObservationController` (`#component =>
    'hivelog:button'` / `'hivelog:button-group'`).
  - List builders: `*ListBuilder.php` operations links.
  - Form actions: `*Form.php` (`.form-actions .button` styled in
    `css/hivelog.buttons.css`).
  - `hivelog.links.action.yml` local actions.
- Pure consistency pass — avoid adding/removing actions; only normalize
  variant + label.

## Dependencies
- Depends on: [[0010-define-button-tokens-and-source-of-truth]].

## Related
- Project:: [[action-button-consistency]]
- Commits:: 
