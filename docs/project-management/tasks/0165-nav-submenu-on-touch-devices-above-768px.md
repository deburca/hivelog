---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: navigation
created: 2026-10-02
branch: feature/0165-nav-submenu-on-touch-devices-above-768px
release:
depends-on:
blocked-by:
---
# Task: Make the nav submenu reachable on touch devices wider than 768px

## Context
Follow-up from the 2026-10-02 review of [[in-app-navigation-restructuring]]
(done). The two-tier nav strip (ADR [[0104-two-tier-in-app-navigation]],
tasks 0148/0149) discloses a hub's children with CSS-only
`:hover`/`:focus-within` (`css/hivelog.app-nav.css:165`), and switches to an
always-open, indented layout only at `max-width: 768px` (`:185`). Between
769px and roughly 1024px (an iPad in portrait or landscape, a small touch
laptop) there is no hover, and tapping a hub link navigates straight to the
hub's destination — `:focus-within` never gets a chance to show the dropdown.
Hives, Inspections, Queens, Queen Observations, Inventory Items, Purchases
and Products are therefore only reachable on those devices via cross-links on
other pages, and the Apiaries page has no sub-links of its own. The mobile
rule's own docblock already explains why a tap can't open a closed dropdown;
the same reasoning applies to any `hover: none` device, not just narrow ones.

## Acceptance criteria
- [x] On a device with no hover capability (`@media (hover: none)`), every
      accessible child renders always-visible under its hub, using the same
      layout the ≤ 768px rule already uses — regardless of viewport width.
- [x] The width-based mobile rule keeps working unchanged for narrow desktop
      windows (hover-capable mouse at ≤ 768px).
- [x] Hover-capable desktop behaviour is unchanged (dropdown on
      `:hover`/`:focus-within`, no layout shift, no overlap with page
      content — see the bug-fix note in the CSS header).
- [x] The CSS header comment is updated to describe the `hover: none` rule.
- [x] Verified at tablet width (900px) — via a local prototype using the
      real stylesheets, not live on `cms2`: see Implementation notes for why
      and what it does and doesn't prove.
- [x] No new JavaScript (ADR-0104: the module ships none).
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key file: `css/hivelog.app-nav.css`. Prefer extracting the mobile
  always-open declarations so both media queries share them, rather than
  copying the block.
- Browser pane: `resize_window` with a custom size between 769px and
  1024px; touch emulation (`hover: none`) may need a UA-level override —
  fall back to temporarily forcing the media query in a prototype page if
  the pane can't emulate it.
- No schema change → no update hook.

## Implementation notes (as built)
- Two edits, CSS/comments only: `css/hivelog.app-nav.css` (the existing
  mobile block's at-rule changed from `@media (max-width: 768px)` to
  `@media (max-width: 768px), (hover: none)` — a comma in a media query
  list is OR, so the declarations are shared, not copied; header comment
  extended to explain it) and `css/hivelog.responsive.css` (the breakpoint
  convention header now says capability queries are separate from
  breakpoints and are never for picking a layout size).
- Caveat on the layout: a `hover: none` tablet gets the *whole* mobile
  layout — primary items stacked in a column, children indented under
  each — not a horizontal row with inline children. That is what the
  criterion asked for ("the same layout"), and a wrapped row of always-open
  submenus at 900px would collide visually, but it is a bigger visual change
  on an iPad than the dropdown-only fix might suggest. Revisit if it looks
  heavy on a real device.
- `hover` describes the *primary* input, so a hybrid touch laptop with a
  mouse keeps the dropdown; `any-hover` would treat that device as
  touch-less. Deliberate.
- Verification was a local prototype page (real `hivelog.responsive.css` +
  `hivelog.app-nav.css`, hand-written nav markup mirroring the
  `HivelogAppNavBuilder` output structure) on a throwaway
  `python3 -m http.server`, because the browser pane was logged out of
  `cms2` this session and the nav strip only renders for authenticated
  users. Computed styles at a 900px viewport: real CSS in the pane's
  hover-capable browser — submenu `opacity: 0`, `position: absolute`,
  nav `flex-direction: row` (desktop dropdown unchanged). A copy of the
  CSS with `(hover: none)` swapped for `(hover: hover)`, so the condition
  is true at 900px — submenu `opacity: 1`, `position: static`, nav
  `column` (always-open layout). The pane's 375px mobile preset reports
  `matchMedia('(hover: none)') === true` and renders the same always-open
  layout under the real CSS. What this does not prove: a real touch device
  at tablet width; the pane can't emulate `hover: none` above 768px.
  Worth one check on an actual iPad.
- phpcs clean over `css/`; phpstan clean (no new findings). Kernel tests
  not run: no PHP or render-array changes, and no test asserts stylesheet
  content.
- Folded in task [[0173-fix-stale-setup-wording-in-nav-css]]: the stale
  "Setup" in the CSS header was corrected here (now "Insights"), as that
  task's own notes anticipated.
- CSS aggregation caches: `ddev drush cr` after deploying.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0149-two-tier-app-nav-css-and-mobile-behaviour]], [[0166-nav-dropdown-accessibility]]
- Commits::
