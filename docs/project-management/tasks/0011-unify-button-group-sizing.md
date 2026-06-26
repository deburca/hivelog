---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[action-button-consistency]]"
area: theme
created: 2026-06-17
branch: feature/0011-unify-button-group-sizing
release: 1.3.1
depends-on: ["[[0010-define-button-tokens-and-source-of-truth]]"]
---
# Task: Unify button-group sizing

## Context
`components/button-group/button-group.twig` previously passed
`extra_classes: "text-sm !px-1 !py-0"` to override base button padding with
ad-hoc Tailwind `!important` rules. Grouped buttons (the View/Edit/Delete
cluster in the inspection "Operations" column) were noticeably smaller than
standalone buttons, and the Tailwind arbitrary-variant classes used for the
join-corner styling (`[&>:not(:first-child)]:rounded-l-none` etc.) were never
compiled — the module has no Tailwind build step — so all buttons in a group
rendered with four fully rounded corners. Additionally, theme framework classes
(`btn`, `btn-danger` etc.) in `button.twig` gave the active admin theme
authority to colour grouped buttons, causing all buttons to render red
regardless of variant. Part of [[action-button-consistency]].

## Decision taken
**Documented compact size token retained for desktop; full standard padding
applied on small screens (≤768px) via a `@media` override** — see
[[0024-button-group-compact-sizing]].

- `--hivelog-btn-compact-padding-y` bumped from `0.25em` → `0.4em` (renders
  ~27–28px height on desktop, satisfying the WCAG 2.5.8 24px absolute floor).
- `@media (max-width: 768px)` block in `css/hivelog.buttons.css` promotes
  `.hivelog-button-group .button` to `var(--hivelog-btn-padding)` on small
  screens (aims for ≥44px touch target).
- No `!important` overrides anywhere in the component.

## Acceptance criteria
- [x] No `!important` ad-hoc overrides remain in `button-group.twig`; sizing
      comes from the token system instead.
- [x] Grouped buttons either match standalone size or use a documented compact
      size; the choice is recorded (ADR-0024).
- [x] Resulting tap targets satisfy the threshold agreed in
      [[0009-mobile-qa-and-tap-targets]] (24px floor on desktop; standard size
      on ≤768px for ≥44px aim; final visual QA in task 0009).
- [x] The button-group join styling (segmented control: inner corners squared
      off, shared border collapsed to 1px) is implemented in plain CSS.
- [x] Inspection "Operations" column still fits its cell on desktop.

## Implementation notes
- Button group markup: `components/button-group/button-group.twig` — wrapper
  simplified to `<div class="hivelog-button-group">` only; all layout and
  join-corner logic moved to `css/hivelog.buttons.css`.
- `button.twig` — all theme framework classes (`btn`, `btn-primary`,
  `btn-danger`, `btn-default`, `join-item`) removed. Semantic modifier classes
  are now `button--primary`, `button--danger`, `button--default` only.
  `css/hivelog.buttons.css` is the sole styling authority (ADR-0012).
- `.hivelog-button-group` added as the eighth context wrapper in all `:is()`
  rule blocks so grouped buttons receive correct token-based colour styling
  without needing an additional outer wrapper.
- Join-corner styling in `css/hivelog.buttons.css`: `.button + .button` uses
  `margin-left: -1px` to collapse the shared border; `:not(:last-child)` squares
  right-hand corners; `:not(:first-child)` squares left-hand corners.
- `margin-right: 0.5em` scoped to the seven standalone contexts only;
  `.hivelog-button-group` is excluded to prevent spacing fighting the
  border-collapse approach.
- Primary caller: `HiveController::view()` builds the View/Edit/Delete group
  for each inspection row (`#component => 'hivelog:button-group'`).

## Dependencies
- Depends on: [[0010-define-button-tokens-and-source-of-truth]].
- Coordinates with: [[0009-mobile-qa-and-tap-targets]],
  [[0005-responsive-entity-list-tables]] (Operations cell on mobile).

## Related
- Project:: [[action-button-consistency]]
- Decisions:: [[0024-button-group-compact-sizing]]
- PRs:: #84 (sizing), #86 (join corners), #88 (variant colours), #90 (margin spacing)
- Released:: 1.3.1, 1.3.2, 1.3.3
