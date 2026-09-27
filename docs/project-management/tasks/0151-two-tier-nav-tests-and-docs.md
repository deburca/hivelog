---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[in-app-navigation-restructuring]]"
area: docs
created: 2026-09-27
branch: feature/0151-two-tier-nav-tests-and-docs
release:
depends-on: ["[[0148-two-tier-app-nav-strip-rendering]]", "[[0149-two-tier-app-nav-css-and-mobile-behaviour]]", "[[0150-two-tier-main-menu-links]]"]
blocked-by:
---
# Task: Two-tier navigation — final test and docs pass

## Context
The integration/polish pass once every other task in
[[in-app-navigation-restructuring]] has landed: a full-suite run
against every affected surface together (not just each task's own
slice), plus bringing the written docs that describe the nav system up
to date. Mirrors task 0123's own role at the end of the earlier
breadcrumb/nav registry work (task 0119/0120).

## Acceptance criteria
- [ ] Full kernel/unit/functional suite green against `cms2`, core and
      every submodule (`--group hivelog`) — the combined behaviour of
      0146–0150 together, not just each one's own tests in isolation.
- [ ] `AGENTS.md`'s "In-app navigation" section rewritten: the
      two-tier model, the `parent` key, the new Setup page, and the
      updated `GROUP_ORDER`/primary-vs-secondary distinction — replacing
      the flat-list description task 0119/0120 left behind.
- [ ] `hivelog.api.php`'s `hook_hivelog_app_nav_items()` docblock
      double-checked against what actually shipped (it was updated
      prospectively in [[0147-app-nav-registry-parent-key]]; confirm no
      drift crept in across 0148–0150).
- [ ] `README.md` checked for any nav-strip/main-menu description that
      now needs updating (only if it currently describes the flat
      structure — leave untouched if it doesn't mention nav layout at
      all).
- [ ] Breadcrumb test coverage for the new `hivelog.setup` route
      (added in [[0146-setup-landing-page-and-route]]) re-confirmed
      still passing after every later task's changes.
- [ ] Live-verify on `cms2`: as at least two different permission
      levels (an `administer hivelog` user and a beekeeper with only
      "own" permissions), confirm the nav strip, the Setup page, and
      the main menu all show exactly the destinations that user can
      access, at both tiers.
- [ ] phpcs clean; phpstan clean (module-wide, confirm baseline count
      unchanged or only shrunk).

## Implementation notes
- Key files: `AGENTS.md`, `hivelog.api.php`, `README.md` (conditionally),
  no source changes expected beyond fixing anything the full-suite run
  surfaces.
- No entity schema change → **no update hook required**.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0123-refresh-navigation-reference-docs]]
- Commits::
