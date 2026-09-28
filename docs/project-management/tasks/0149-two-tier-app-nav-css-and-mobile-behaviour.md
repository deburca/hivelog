---
type: task
tags: [hivelog/task]
status: done
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
- [x] Desktop/tablet (> 768px): a primary item's child list is
      positioned as a dropdown under its pill, hidden by default
      (`opacity: 0; pointer-events: none`, not `display`/`visibility`),
      revealed via `:hover`/`:focus-within` on the primary item's
      wrapper. Keyboard-only navigation (Tab, no mouse) can reach every
      child link in source order without any child ever being skipped.
  - [x] ~~A hub with `has-active-child` renders its children open by
      default, not just on hover/focus.~~ **Reverted post-ship — see
      Implementation notes' "Regression found live" entry.** A real
      user hit the bug this criterion's own original implementation
      caused; `has-active-child` now only styles the hub's own pill
      (unchanged), never force-opens its dropdown.
- [x] Mobile (≤ 768px, the module's existing small-tablet breakpoint):
      the hover-gated behaviour is dropped entirely — every accessible
      child renders always-visible, indented under its primary parent
      (an always-open accordion). **Decision: the task-0120 horizontal-
      scroll-at-phone-width rule is retired, not carried forward** —
      with only 2–3 primary items, there's nothing left to overflow at
      the top level; the always-open submenu instead just wraps
      (`flex-wrap: wrap`), which suits short pill labels far better
      than a scroll container would.
- [x] `.hivelog-app-nav__link.is-active` styling is unchanged; a new
      `.has-active-child` style on the primary wrapper is visually
      distinct from but not identical to `.is-active` (an outline in
      the primary colour, not a solid fill — "in this section" reads
      differently from "on this exact page").
- [x] No new library/JS file added to `hivelog.libraries.yml`; the
      existing `hivelog/app_nav` library gains no new `js:` key.
- [x] Visual check on `cms2`, desktop and phone width — see below for
      how, given a real environment obstacle.
- [x] Confirmed no CSS linter exists in this repo (no `package.json`,
      no `.stylelintrc*`, no CSS step in `.github/workflows/ci.yml`) —
      phpcs genuinely doesn't apply to `.css` files.

## Implementation notes
- **Regression found live on `cms2`, reported by the user, fixed same
  day (2026-09-28):** on `/hivelog/hives`, the secondary nav appeared
  as a permanent vertical block of links covering the top of the Hives
  table. Root cause: `.has-active-child` was an *additional* trigger
  that force-opened a hub's dropdown, on the theory that "you're in
  this section" should stay visible without hovering — reasonable in
  concept, wrong in practice, because `.hivelog-app-nav__submenu` is
  `position: absolute` and reserves no space in the page's own layout
  flow. A force-opened dropdown just floats on top of whatever content
  happens to render immediately below the nav strip — here, the Hives
  list's own filter form and table. Fixed by making disclosure
  `:hover`/`:focus-within` only; `.has-active-child` still gives the
  hub's own pill its outline treatment (unchanged), it just no longer
  forces the dropdown itself open. Live-verified via the same
  inline-CSS-injection technique this task's own notes already
  describe (browser asset requests to `cms2` are still blocked in this
  session): the Hives page now renders its filter form and table with
  nothing overlapping, and hovering "Apiaries" still correctly opens
  its dropdown, with "Hives" shown `is-active` inside it.
- **Visual verification had a real obstacle, worked around, not
  skipped.** Every asset request to `cms2` in this session's browser
  tool returns `net::ERR_BLOCKED_BY_CLIENT` — site-wide (fonts, JS,
  every theme's CSS, hivelog's own, aggregated or not), unrelated to
  this change. This means every screenshot taken *earlier* in this
  session was already silently unstyled — it just never mattered until
  a CSS task needed the rendering itself checked. Worked around by
  injecting this exact CSS (plus the handful of `--hivelog-*` tokens
  it depends on) as an inline `<style>` tag via the browser tool's JS
  execution — that bypasses the network layer entirely, so it renders
  the real rules without needing the blocked request to succeed.
  Confirmed genuinely, visually, via screenshots: the closed pill row,
  the open dropdown on hover (all 7 Apiaries children, separator
  between the `records`/`inventory` groups), and the mobile always-open
  indented/wrapped accordion. Noting this here so a future session
  doesn't mistake a plain screenshot on `cms2` for "no styling shipped"
  — it's this browser tool's own network restriction, not the module.
- **No z-index precedent existed anywhere in this module's CSS** before
  this task — added one (`10`) on `.hivelog-app-nav__submenu` alone, a
  standard, narrowly-scoped necessity for an absolutely-positioned
  dropdown to reliably paint over subsequent in-flow page content.
- **`transition: … 0.15s ease-in-out`** matches the exact timing/easing
  `hivelog.images.css` and `hivelog.weight-histogram.css` already use —
  reused rather than inventing a new value.
- Key files: `css/hivelog.app-nav.css` (full rewrite). No PHP change
  needed — every class this CSS targets
  (`hivelog-app-nav__{item,submenu}`, `has-active-child`) was already
  emitted by [[0148-two-tier-app-nav-strip-rendering]].
- No entity schema change → **no update hook required**.
- Reuses `--hivelog-*` tokens already defined in
  `css/hivelog.responsive.css` (surface/ink/border, `--hivelog-btn-primary-*`
  for active-state) — no new token set for one nav strip, per this
  module's existing theming convention (AGENTS.md "Theming HiveLog").
- phpcs/phpstan: no PHP changed, both re-run clean regardless (no
  baseline changes). No test suite implication — this task is CSS-only.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
