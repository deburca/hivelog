---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: css
created: 2026-09-28
branch: feature/0161-hexagon-nav-button-shape
release:
depends-on:
blocked-by:
---
# Task: Elongated-hexagon shape for the in-app nav strip's buttons

## Context
User asked to replace the nav strip's fully-rounded ("pill"/stadium,
`border-radius: 999px`) left/right ends with the left and right sides
of a hexagon instead — an elongated hexagon (flat top/bottom, angled
sides meeting at a point on each end), leaning into the honeycomb-cell
motif a beekeeping app should have. Scoped to `.hivelog-app-nav__link`
specifically (`css/hivelog.app-nav.css`) — the nav strip's own buttons
— not the generic `hivelog:button` SDC component (`css/hivelog.buttons.css`,
ADR-0012's "sole source of truth" for Edit/Delete/Add/View/Filter/Reset
buttons everywhere else in the module), which already uses a small
fixed-radius corner (`--hivelog-btn-radius: 4px`), not a semi-circular
one, and wasn't the "navigation buttons" the request named.

## Acceptance criteria
- [x] `.hivelog-app-nav__link`'s `border-radius: 999px` replaced with a
      `clip-path` hexagon: flat top/bottom edges, each left/right side
      cut at a fixed `--hivelog-nav-hex-cut` distance from the
      top/bottom corners, meeting at a point at vertical center.
- [x] Border renders correctly along every edge of the clipped shape
      (confirmed — no separate double-layer border trick needed;
      `border` on a `clip-path`-shaped box paints correctly along the
      clip outline in this rendering engine).
- [x] Applies uniformly wherever `.hivelog-app-nav__link` renders —
      top-level primary pills (desktop + mobile) and the desktop/
      tablet dropdown's full-width child rows both become the same
      hexagon shape (a stretched hexagon row for the wide dropdown
      case) — one rule, not a special case per context, matching how
      the shape was already unified before this change.
- [x] Stale "pill" wording in the file's own comments updated where it
      described current behaviour (not left alone where it was
      describing a past decision/historical context).
- [x] Verified visually (CSS injected into a live `cms2` page, since
      the browser tool blocks real stylesheet requests to `cms2` —
      confirmed pointed hexagon ends, border intact on the diagonal
      edges, active-state colour still applies correctly).

## Implementation notes
- Key file: `css/hivelog.app-nav.css` (`.hivelog-app-nav__link` rule +
  one stale comment).
- Prototyped the shape and cut distance first in a standalone HTML
  file (served via a throwaway local `python3 -m http.server`, since
  the browser tool's file:// handling only gives a static snapshot,
  not a live screenshot-able page) before touching the real CSS —
  compared cut distances 6/8/10/12/16px against the real button
  padding (`0.25rem 0.75rem`) and a single-letter label ("Y") to check
  for text clipping at the corners. Settled on `10px`: clearly
  hexagonal without needing to widen the existing padding, and no
  clipping risk since the cut only narrows the box near the top/bottom
  edges — text sits at vertical center, the shape's full width.
- No entity/schema/route change, no new CSS custom-property token set
  (kept to the file's own stated "no new token set for one small nav
  strip" philosophy) — `--hivelog-nav-hex-cut` is a local property on
  the one rule, not added to `hivelog.responsive.css`'s shared `:root`
  token set.
- Live verification method: real stylesheet requests to `cms2` are
  blocked site-wide in this session's browser tool
  (`net::ERR_BLOCKED_BY_CLIENT`), a known, previously-documented
  limitation — worked around by injecting the exact `hivelog.responsive.css`
  + `hivelog.app-nav.css` content as an inline `<style>` tag via JS
  execution on the real `/hivelog/hives` page, then narrowing the
  viewport to inspect the rendered shape closely. Confirmed the
  "Dashboard"/"Bigårde" (Apiaries) pills both show a clean pointed
  hexagon end with the border intact all the way around, including
  the diagonal edges, and the active-state primary-colour border/text
  still renders correctly on the clipped shape.
- No kernel/unit test coverage — pure CSS, no PHP touched, nothing in
  this repo's test suite exercises rendered CSS shape.

## Related
- Commits::
