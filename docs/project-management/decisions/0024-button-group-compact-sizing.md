---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-26
supersedes:
---
# ADR-0024: Button-group compact sizing strategy

## Status
accepted

## Context
Task 0010 established `css/hivelog.buttons.css` as the single source of truth
for button appearance (ADR-0012). As part of that work the ad-hoc
`!important` padding overrides in `button-group.twig` (`text-sm !px-1 !py-0`)
were removed and replaced with a `.hivelog-button-group .button` rule that
consumes two dedicated compact tokens:

```css
--hivelog-btn-compact-padding-x: 0.5em;
--hivelog-btn-compact-padding-y: 0.25em;
```

This makes the compact sizing intentional and token-driven, satisfying the
"no `!important` hacks" requirement from ADR-0012. However, two questions
remained open for Task 0011:

1. **Should grouped buttons match standalone size, or is a documented compact
   size the right choice?**
2. **Do the current compact token values satisfy the tap-target threshold from
   ADR-0014 (≥24×24px absolute floor; aim ≥44×44px)?**

On the second question: with `font-size: 0.9rem`, `line-height: 1.4`, and
`padding-y: 0.25em` the rendered button height is approximately:

```
(0.9rem × 1.4) + (2 × 0.25em)
≈ 1.26rem + 0.5em
≈ 20–22px at typical browser defaults
```

This falls below the WCAG 2.5.8 absolute floor of 24×24px on all viewports.
On a phone (360–414px wide) the View/Edit/Delete cluster in the inspection
Operations column is the primary trigger for the tap-target concern flagged in
[[0009-mobile-qa-and-tap-targets]].

At the same time, forcing grouped buttons to full standalone size
(`0.45em 1em`) on desktop would widen the Operations cell in the inspection
table noticeably, potentially breaking the dense-table layout that is
intentional on desktop.

## Decision
Retain a documented compact size token for desktop, but promote grouped buttons
to full standard padding on small screens via a responsive override.

Specifically:

1. **Compact token values** — increase `--hivelog-btn-compact-padding-y` from
   `0.25em` to `0.4em`. This raises the desktop rendered height to
   approximately 27–28px, satisfying the 24px absolute floor on desktop and in
   landscape mobile.

2. **Responsive promotion** — add a `@media (max-width: 768px)` override in
   `css/hivelog.buttons.css` that replaces the compact padding with the full
   standard padding (`var(--hivelog-btn-padding)`) for `.hivelog-button-group
   .button`. On portrait phones (≤480px), where the Operations column stacks
   into a card layout (Task 0005), buttons already have more horizontal room
   and the standard size is both achievable and correct.

3. **Desktop Operations column** — the compact token (`0.4em × 0.5em`) remains
   meaningfully smaller than the standard token (`0.45em × 1em`) on horizontal
   padding, keeping the Operations cell compact on desktop.

4. **Decision recorded here** — the compact/standard split is an explicit,
   documented choice, not an accident. `hivelog.responsive.css` carries a brief
   inline comment cross-referencing this ADR at the token definitions.

This approach satisfies all five acceptance criteria in
[[0011-unify-button-group-sizing]] and aligns with ADR-0012 (token system),
ADR-0014 (tap-target baseline), and ADR-0011 (plain-CSS `@media` breakpoints).

## Consequences
- Positive: tap targets meet the WCAG 2.5.8 24px absolute floor on all
  viewports; standard size applies on phone/tablet where density matters less
  and touch accuracy matters more; desktop layout is unchanged.
- Negative / trade-offs: compact and standard sizing now behave differently
  across breakpoints — a developer adding a new button group must be aware of
  the responsive promotion rule.
- Follow-up tasks: [[0009-mobile-qa-and-tap-targets]] (QA verification of
  final rendered sizes).

## Implementation record
During delivery of [[0011-unify-button-group-sizing]] three additional defects
were identified and resolved alongside the sizing work:

1. **Join-corner styling not rendering** (PR #86, released 1.3.1) — the Tailwind
   arbitrary-variant classes on the button-group wrapper
   (`[&>:not(:first-child)]:rounded-l-none` etc.) were never compiled. Replaced
   with plain CSS `:first-child`/`:last-child` selectors and `margin-left: -1px`
   border collapse in `hivelog.buttons.css`. The wrapper was simplified to
   `<div class="hivelog-button-group">` only.

2. **All buttons rendering red regardless of variant** (PR #88, released 1.3.2)
   — two compounding causes: (a) theme framework classes (`btn`, `btn-danger`
   etc.) in `button.twig` gave the active admin theme authority to colour
   buttons; (b) `.hivelog-button-group` was not a registered context wrapper in
   `hivelog.buttons.css` so the module's own token rules never fired. Fixed by
   removing all theme framework classes from `button.twig` (semantic modifiers
   `button--primary`, `button--danger`, `button--default` only) and adding
   `.hivelog-button-group` as the eighth context wrapper in all `:is()` blocks.

3. **Margin spacing between adjacent grouped buttons** (PR #90, released 1.3.3)
   — `margin-right: 0.5em` from the shared base rule fought the `margin-left:
   -1px` border-collapse, creating gaps between buttons. Scoped `margin-right`
   to the seven standalone contexts only; `.hivelog-button-group` is explicitly
   excluded.
