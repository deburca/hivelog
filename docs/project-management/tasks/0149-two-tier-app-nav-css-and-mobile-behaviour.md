---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: theme
created: 2026-09-27
branch: feature/0149-two-tier-app-nav-css-and-mobile-behaviour
release:
depends-on: ["[[0148-two-tier-app-nav-strip-rendering]]"]
blocked-by:
---
# Task: Two-tier nav strip CSS and mobile behaviour

## Context
[[0148-two-tier-app-nav-strip-rendering]] ships the new nested markup;
this task makes it look and behave like the two-tier strip
[[0104-two-tier-in-app-navigation]] describes. No JavaScript — `hivelog`
ships none today and the ADR deliberately keeps it that way — so
disclosure is CSS-only (`:hover` / `:focus-within`), and child links
must stay focusable while visually hidden (never `display: none` or
`visibility: hidden`, both of which remove an element from the tab
order — use `opacity` + `pointer-events` instead).

## Acceptance criteria
- [ ] Desktop/tablet (> 768px): a primary item's child list is
      positioned as a dropdown under its pill, hidden by default
      (`opacity: 0; pointer-events: none`, not `display`/`visibility`),
      revealed via `:hover`/`:focus-within` on the primary item's
      wrapper. Keyboard-only navigation (Tab, no mouse) can reach every
      child link in source order without any child ever being skipped.
  - [ ] A hub with `has-active-child` (from [[0148-two-tier-app-nav-strip-rendering]])
      renders its children open by default, not just on hover/focus.
- [ ] Mobile (≤ 768px, the module's existing small-tablet breakpoint):
      the hover-gated behaviour is dropped entirely — every accessible
      child renders always-visible, indented under its primary parent
      (an always-open accordion). The existing task-0120 horizontal-
      scroll-at-phone-width rule is re-evaluated: confirm whether it's
      still needed now that the top level is 2–3 items instead of 11,
      or whether the always-open child list needs its own overflow
      handling instead.
- [ ] `.hivelog-app-nav__link.is-active` styling is unchanged; a new
      `.has-active-child` style on the primary wrapper is visually
      distinct from but not identical to `.is-active` (the user is
      *in* that section, not *on* that exact page).
- [ ] No new library/JS file added to `hivelog.libraries.yml`; the
      existing `hivelog/app_nav` library gains no new `js:` key.
- [ ] Visual check on `cms2` (both a desktop-width and a phone-width
      viewport) that every primary and secondary item is reachable and
      the active/has-active-child states render correctly on a
      canonical page under each hub (e.g. a hive canonical page marks
      "Hives" active and "Apiaries" has-active-child).
- [ ] phpcs is not applicable to CSS; confirm no Stylelint/other linter
      this repo runs is broken (check `composer.json`/CI for one before
      assuming there is none).

## Implementation notes
- Key files: `css/hivelog.app-nav.css` (near-total rewrite),
  `src/HivelogAppNavBuilder.php` only if a class name needs adding
  that [[0148-two-tier-app-nav-strip-rendering]] didn't already emit.
- No entity schema change → **no update hook required**.
- Reuses `--hivelog-*` tokens already defined in
  `css/hivelog.responsive.css` (surface/ink/border, `--hivelog-btn-primary-*`
  for active-state) — no new token set for one nav strip, per this
  module's existing theming convention (AGENTS.md "Theming HiveLog").

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
