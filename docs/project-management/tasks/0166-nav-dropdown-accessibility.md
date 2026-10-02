---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: navigation
created: 2026-10-02
branch: feature/0166-nav-dropdown-accessibility
release:
depends-on:
blocked-by:
---
# Task: Accessibility attributes and Escape-to-close for the nav dropdown

## Context
Follow-up from the 2026-10-02 review of [[in-app-navigation-restructuring]]
(done). The nav strip's hover-gated dropdown has no `aria-haspopup` or
`aria-expanded` on its hub links (nothing in `HivelogAppNavBuilder` or its
template emits either), and a keyboard user who tabs into a submenu cannot
dismiss it with Escape — it stays open for as long as focus is inside it.
Task [[0128-page-heading-hierarchy]] committed the module to WCAG 2.2
(SC 1.3.1 / 2.4.6) for heading structure, but the nav strip was added later
and wasn't held to the same standard. A CSS-only disclosure cannot toggle
`aria-expanded` truthfully, so the fix needs a deliberate choice about how
to describe the state without JavaScript.

## Acceptance criteria
- [ ] Decide and record the approach: (a) mark hub links with
      `aria-haspopup="true"` and describe the submenu with
      `aria-labelledby` while leaving `aria-expanded` off (it can't be kept
      truthful in CSS-only), or (b) allow a minimal progressive-enhancement
      script for `aria-expanded` and Escape handling. If (b), it needs an
      amendment to ADR-0104's "no JavaScript" position.
- [ ] Hub links and their submenus carry the agreed ARIA attributes; the
      nav landmark is labelled.
- [ ] Escape closes an open submenu and returns focus to its hub link, or the
      task records why that isn't achievable under option (a).
- [ ] `aria-current` behaviour (task 0120) is unchanged.
- [ ] Kernel test asserts the attributes in the rendered nav array.
- [ ] Checked with a screen reader or the browser accessibility tree
      (`read_page`) on `cms2`.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HivelogAppNavBuilder.php` (render array),
  `css/hivelog.app-nav.css`, any nav template.
- Coordinate with [[0165-nav-submenu-on-touch-devices-above-768px]] — both
  touch the same CSS and render array.
- No schema change → no update hook.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0128-page-heading-hierarchy]], [[0165-nav-submenu-on-touch-devices-above-768px]]
- Commits::
