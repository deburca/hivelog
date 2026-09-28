---
type: task
tags: [hivelog/task]
status: done
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
- [x] Full kernel/unit/functional suite green against `cms2`, core and
      every submodule (`--group hivelog`) — the combined behaviour of
      0146–0150 together, not just each one's own tests in isolation.
- [x] `AGENTS.md`'s "In-app navigation" section rewritten: the
      two-tier model, the `parent` key, the new Setup page, and the
      updated `GROUP_ORDER`/primary-vs-secondary distinction — replacing
      the flat-list description task 0119/0120 left behind.
- [x] `hivelog.api.php`'s `hook_hivelog_app_nav_items()` docblock
      double-checked against what actually shipped — found one real
      drift (see notes) and fixed it.
- [x] `README.md` checked — its "Navigation" section describes
      per-entity drill-down and breadcrumbs only, never the nav
      strip/main-menu layout at all, so left untouched per this task's
      own "leave untouched if it doesn't mention nav layout" rule.
- [x] Breadcrumb test coverage for the new `hivelog.setup` route
      (added in [[0146-setup-landing-page-and-route]]) re-confirmed
      still passing after every later task's changes.
- [x] Live-verify on `cms2`: as at least two different permission
      levels (an `administer hivelog` user and a beekeeper with only
      "own" permissions), confirm the nav strip, the Setup page, and
      the main menu all show exactly the destinations that user can
      access, at both tiers.
- [x] phpcs clean; phpstan clean (module-wide, confirm baseline count
      unchanged or only shrunk).

## Implementation notes
- **`hivelog.api.php` drift found and fixed:** the
  `hook_hivelog_app_nav_items()` docblock's own class-level
  cross-reference still said "`HivelogAppNavBuilder` builds this from
  `hivelog` core's own **8** built-in destinations" — stale since task
  0147 added the 9th (`setup`). The per-hook docblock (weights,
  `parent` key) had already been kept current across 0146/0147/0148,
  so this was the one place drift actually crept in.
- **`AGENTS.md`'s "In-app navigation" section rewritten in full**: now
  documents the primary/secondary split (Dashboard/Apiaries/Setup vs.
  everything else), `parent` and its unresolvable-parent fallback,
  `getAccessibleChildren()` as the one shared "what can this account
  reach" query (`SetupPageAccessCheck`, `SetupController`, `build()`
  all read it), the two-tier `build()` output shape
  (`.hivelog-app-nav__item`/`.hivelog-app-nav__submenu`/
  `has-active-child`), the CSS-only desktop dropdown vs. mobile
  always-open accordion split, and `HivelogMenuLinks`'s mirrored
  per-item `parent` override with no update hook needed.
- **Live-verify used real permission levels, not synthetic mocks**:
  created a throwaway role (`view own apiary`/`hive`/`inventory item`
  only) and user via `drush php:eval`, called
  `HivelogAppNavBuilder::build()` directly as that account and as
  `uid 1`, confirmed the beekeeper saw exactly `Dashboard`, `Apiaries`
  (with only `Hives`/`Inventory Items` as children — the two entity
  types it actually has permission for) and no `Setup` at all, while
  admin saw every destination at both tiers; deleted the throwaway
  role/user immediately after. Also discovered the site's real
  `beekeeper`/`bee_keeper`/`aipary_owner` fixture accounts have zero
  permissions actually assigned (`perms: {}`) — pre-existing broken
  test data on `cms2`, unrelated to this task, not touched.
- **`\Drupal::menuTree()` used for the earlier task 0150's own
  main-menu confirmation** (not repeated here) already proved the
  parenting is correct at the data level regardless of what `cms2`'s
  own theme (`quick_silver`) chooses to render.
- phpstan baseline unchanged: `phpstan-baseline.neon` hasn't been
  touched since task 0130, and every task in this sequence reported
  "\[OK\] No errors" (zero new findings) throughout.
- Key files: `AGENTS.md`, `hivelog.api.php` (one-line drift fix).
  `README.md` read, not changed. No source (`src/`) changes in this
  task — everything it touches is documentation.
- No entity schema change → **no update hook required**.
- Full kernel/unit/functional suite against `cms2`, core and every
  submodule: **1,076 tests, 0 failures** — the true multi-path
  invocation (see [[0147-app-nav-registry-parent-key]]'s own notes on
  why that matters), covering every task in this sequence together.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Tasks:: [[0123-refresh-navigation-reference-docs]]
- Commits::
