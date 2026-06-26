---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[action-button-consistency]]"
area: theme
created: 2026-06-17
branch: feature/0011-unify-button-group-sizing
release: 1.4.0
depends-on: ["[[0010-define-button-tokens-and-source-of-truth]]"]
---
# Task: Unify button-group sizing

## Context
`components/button-group/button-group.twig` renders each child button with
`extra_classes: "text-sm !px-1 !py-0"`, using `!` (Tailwind `!important`) to
override the base button padding. The result: grouped buttons (the
View/Edit/Delete cluster in the inspection "Operations" column) are noticeably
smaller than standalone buttons like "Add Inspection". This both looks
inconsistent and creates the tap-target problem flagged in
[[0009-mobile-qa-and-tap-targets]]. Part of [[action-button-consistency]].

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
- [x] The button-group join styling (`rounded-l-none`/`rounded-r-none` segment
      look) is preserved.
- [x] Inspection "Operations" column still fits its cell on desktop.

## Implementation notes
- Button group markup: `components/button-group/button-group.twig`; it
  `include`s `hivelog:button` per child. `button-group.css` is currently a
  comment-only placeholder (layout is utility-driven).
- Primary caller: `HiveController::view()` builds the View/Edit/Delete group for
  each inspection row (`#component => 'hivelog:button-group'`).

## Dependencies
- Depends on: [[0010-define-button-tokens-and-source-of-truth]].
- Coordinates with: [[0009-mobile-qa-and-tap-targets]],
  [[0005-responsive-entity-list-tables]] (Operations cell on mobile).

## Related
- Project:: [[action-button-consistency]]
- Commits:: 
