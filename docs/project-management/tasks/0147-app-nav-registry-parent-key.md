---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: routing
created: 2026-09-27
branch: feature/0147-app-nav-registry-parent-key
release:
depends-on: ["[[0146-setup-landing-page-and-route]]"]
blocked-by:
---
# Task: Add a `parent` key to the nav item registry

## Context
[[0104-two-tier-in-app-navigation]]'s secondary tier is driven by a new
optional `parent` key on the existing nav item descriptor shape
(`['title', 'url', 'weight', 'group', 'section']`). This task only
changes the *registry* — `HivelogAppNavBuilder::getAllItems()` keeps
returning the same flat, keyed array shape it does today, just with
some entries now carrying `parent`. Rendering
([[0148-two-tier-app-nav-strip-rendering]]) and the main menu
([[0150-two-tier-main-menu-links]]) are separate tasks so this one stays
independently testable: after this task, every existing test should
still pass unchanged, because nothing yet *reads* `parent`.

## Acceptance criteria
- [x] `hivelog.api.php`'s `hook_hivelog_app_nav_items()` docblock
      documents the new optional `parent` key: the key of another item
      (core's own or another module's) this one nests under; omit for a
      top-level item. Update the example in the docblock.
- [x] `HivelogAppNavBuilder::builtInItems()`: add `'parent' => 'apiaries'`
      to `hives`, `inspections`, `queens`, `queen_observations`,
      `inventory_items`, `inventory_purchases`, `products`. Add a new
      built-in `setup` item (title "Setup", `Url::fromRoute('hivelog.setup')`
      from [[0146-setup-landing-page-and-route]], weight placing it after
      `apiaries` in `GROUP_ORDER`'s `setup` position, no `parent` — it's
      primary).
- [x] `collective_hivelog_app_nav_items()`, `nanoprobe_hivelog_app_nav_items()`,
      `nexus_hivelog_app_nav_items()`: each existing item gains
      `'parent' => 'setup'`.
- [x] `GROUP_ORDER`'s docblock updated — `inventory` no longer has any
      primary-tier member (its only current member becomes a
      `parent: 'apiaries'` secondary item), only `records` (Apiaries)
      and `setup` (the new Setup item) do.
- [x] Existing `HivelogAppNavBuilderTest`/equivalent kernel or unit
      tests (whichever file covers `getAllItems()`/`builtInItems()`)
      pass unchanged, plus new assertions that the expected items now
      carry the expected `parent` value and the new `setup` built-in
      exists and resolves a real accessible-when-installed URL.
- [x] Each submodule's own `AppNavItemsTest` (collective, nanoprobe,
      nexus) updated to assert its item's new `parent` value.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- **Weight collision, caught before it shipped:** the three
  submodules' existing `setup`-group weights (8/9/10) collided with
  the new core `setup` item's own weight (8, the next slot after
  `products`=7). Bumped `collective`→9, `nexus`→10, `nanoprobe`→11 —
  harmless today (`build()` doesn't sort within a hub yet) but would
  have produced an arbitrary tie-broken order the moment
  [[0148-two-tier-app-nav-strip-rendering]] starts caring about
  intra-hub order.
- **Ordering bug found live on `cms2`, not in the plan above:**
  `HivelogAppNavBuilder::getAccessibleChildren()` (added in
  [[0146-setup-landing-page-and-route]]) never sorted its result —
  invisible there since only one test-only item ever existed at once.
  With all three real `setup`-group items installed on `cms2`, the
  Setup page listed them in module-invocation order (API Clients,
  Sensor Devices, AI Provider Configs) instead of weight order.
  Extracted `build()`'s existing group-then-weight comparator into a
  shared `sortByGroupThenWeight()` and applied it in
  `getAccessibleChildren()` too, rather than duplicating the logic.
  Covered by `testGetAccessibleChildrenOrderedByWeight()` (installs
  `collective`/`nexus`/`nanoprobe` together) and re-verified live:
  the page now reads API Clients, AI Provider Configs, Sensor Devices.
- Confirmed `build()` and `HivelogMenuLinks` are unaffected as
  designed: `testItemsAreSortedByGroupThenWeight()`'s exact-order
  assertion (core-only, no submodule) passes unchanged, and
  `testAllBuiltInItemsAppearForAdministrator()` now additionally
  asserts `setup` is *absent* even for an administrator in that bare
  environment (its own accessibility depends on a child existing,
  which task 0148/0150 don't change).
- Key files: `hivelog.api.php`, `src/HivelogAppNavBuilder.php`
  (`parent` keys, new `setup` built-in, `sortByGroupThenWeight()`
  extracted and reused), `modules/collective/collective.module`,
  `modules/nanoprobe/nanoprobe.module`, `modules/nexus/nexus.module`
  (`parent: 'setup'` + weight bump each), the four modules'
  `tests/src/Kernel/AppNavItemsTest.php`,
  `tests/src/Kernel/HivelogAppNavBuilderTest.php` (4 new tests).
- No entity schema change → **no update hook required**.
- phpcs clean; phpstan clean (module-wide, no baseline changes). Full
  kernel/unit/functional suite against `cms2`, core and every
  submodule: **1,072 tests, 0 failures** — the first time in this
  session that count is genuinely trustworthy. Every earlier "full
  suite green" claim this session (including task 0146's) ran
  `phpunit ... web/modules/contrib/hivelog/tests/` as a single
  directory argument, which silently never recurses into
  `modules/{assimilate,collective,nanoprobe,nexus}/tests/` — a
  sibling tree, not nested inside `hivelog/tests/`. Caught while
  verifying this task (which edits three submodule files), fixed by
  passing every submodule's own test directory explicitly; recorded
  in memory (`run-kernel-tests-via-ddev`) so it isn't repeated.
  Nothing this session actually broke in a submodule — this is a
  verification-process gap, not a regression — but every "full suite"
  claim before this task should be read as "hivelog core's own suite
  only."
- Live-verified on `cms2`: the flat nav strip now shows "Setup"
  immediately before API Clients/AI Provider Configs/Sensor Devices
  (unchanged rendering, just the new item taking its `GROUP_ORDER`
  slot), and `/hivelog/setup` lists all three in weight order with a
  correct `Home › HiveLog › Setup` breadcrumb.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
