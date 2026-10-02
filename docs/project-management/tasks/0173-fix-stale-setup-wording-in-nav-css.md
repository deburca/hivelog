---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: docs
created: 2026-10-02
branch: feature/0173-fix-stale-setup-wording-in-nav-css
release:
depends-on:
blocked-by:
---
# Task: Fix stale "Setup" wording in the nav CSS header

## Context
Found in the 2026-10-02 review of [[in-app-navigation-restructuring]]
(done). `css/hivelog.app-nav.css:13` still says the primary items are
"Dashboard, Apiaries, Setup". Task [[0152-rename-setup-to-insights]] renamed
the item to Insights, and the other remaining "Setup" mentions in `src/` and
AGENTS.md are deliberate rename history (they say "renamed from Setup").
This one is not: it describes the current structure with the old name.

## Acceptance criteria
- [ ] The CSS header comment names the primary items as "Dashboard,
      Apiaries, Insights".
- [ ] Re-run a case-sensitive `grep -rn "Setup\b"` over `css/`, `src/`,
      `modules/*/src`, `*.module` and AGENTS.md; every remaining hit is
      either rename history or a `setUp()` method. Record the result.
- [ ] Comment-only change: no behaviour change, no test, no release.

## Implementation notes
- Fold this into [[0165-nav-submenu-on-touch-devices-above-768px]] if that
  task is done first, since both edit the same CSS header. If so, mark this
  one `dropped` with a pointer rather than leaving it open.

## Related
- Project:: [[in-app-navigation-restructuring]] (done; follow-up)
- Tasks:: [[0152-rename-setup-to-insights]], [[0165-nav-submenu-on-touch-devices-above-768px]]
- Commits::
