---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] On a device with no hover capability (`@media (hover: none)`), every
      accessible child renders always-visible under its hub, using the same
      layout the ≤ 768px rule already uses — regardless of viewport width.
- [ ] The width-based mobile rule keeps working unchanged for narrow desktop
      windows (hover-capable mouse at ≤ 768px).
- [ ] Hover-capable desktop behaviour is unchanged (dropdown on
      `:hover`/`:focus-within`, no layout shift, no overlap with page
      content — see the bug-fix note in the CSS header).
- [ ] The CSS header comment is updated to describe the `hover: none` rule.
- [ ] Verified live on `cms2` at tablet width (768–1024px) with touch
      emulation: all children reachable without hovering.
- [ ] No new JavaScript (ADR-0104: the module ships none).
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key file: `css/hivelog.app-nav.css`. Prefer extracting the mobile
  always-open declarations so both media queries share them, rather than
  copying the block.
- Browser pane: `resize_window` with a custom size between 769px and
  1024px; touch emulation (`hover: none`) may need a UA-level override —
  fall back to temporarily forcing the media query in a prototype page if
  the pane can't emulate it.
- No schema change → no update hook.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0149-two-tier-app-nav-css-and-mobile-behaviour]], [[0166-nav-dropdown-accessibility]]
- Commits::
