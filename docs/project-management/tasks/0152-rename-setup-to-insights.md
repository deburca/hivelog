---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: routing
created: 2026-09-27
branch: feature/0152-rename-setup-to-insights
release:
depends-on:
blocked-by:
---
# Task: Rename "Setup" to "Insights"

## Context
[[0105-collection-breadcrumb-ancestry-and-insights-rename]]'s decision:
a clean, universal rename — every surface, not a relabel-in-one-place-
only. Safe to do without a compatibility shim since nothing from
[[in-app-navigation-restructuring]] has shipped in a tagged release
yet: no external bookmark, no `core.menu.static_menu_link_overrides`
row anywhere depends on the old `hivelog.setup` path or plugin key.

## Acceptance criteria
- [x] Route `hivelog.setup` → `hivelog.insights`, path `/hivelog/setup`
      → `/hivelog/insights`.
- [x] `SetupController` → `InsightsController` (same file rename),
      `SetupPageAccessCheck` → `InsightsPageAccessCheck` — same
      behaviour, same `getAccessibleChildren('insights', ...)` call
      shape, just renamed classes/methods/docblocks referencing "Setup".
- [x] `HivelogAppNavBuilder::builtInItems()`: the `setup` key and its
      `'title' => $this->t('Setup')` entry become `insights` /
      `$this->t('Insights')`.
- [x] `collective_hivelog_app_nav_items()`, `nanoprobe_hivelog_app_nav_items()`,
      `nexus_hivelog_app_nav_items()`: `'parent' => 'setup'` →
      `'parent' => 'insights'`.
- [x] `RouteEntityAccessTest::EXEMPT_ROUTES`: the `hivelog.setup` entry
      renamed to `hivelog.insights` (same reasoning).
- [x] `tests/modules/hivelog_app_nav_test/`: its fixture item's
      `'parent' => 'setup'` → `'parent' => 'insights'` (the *orphan*
      fixture's `'parent' => 'nonexistent_hub'` is unrelated and stays).
- [x] Every existing test referencing `hivelog.setup`, `SetupController`,
      `SetupPageAccessCheck`, the `setup` item key, or the string
      "Setup" as this item's expected title/breadcrumb text, renamed to
      match — `tests/src/Kernel/SetupTest.php` → `InsightsTest.php`
      (class renamed too), plus the affected assertions in
      `HivelogAppNavBuilderTest.php`, `HivelogMenuLinksTest.php`,
      `RouteEntityAccessTest.php`, and each submodule's own
      `AppNavItemsTest.php`.
- [x] Breadcrumb: the `$leaf_pages` entry (task 0146) for this route's
      terminal crumb becomes `'hivelog.insights' => $this->t('Insights')`;
      the corresponding `HivelogBreadcrumbBuilderTest::leafPageProvider()`
      row updated to match.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- **Also renamed the `setup` *group*** (`'group' => 'setup'` →
  `'group' => 'insights'`, and `GROUP_ORDER`'s own entry) on the
  Insights item itself and all three submodule contributions — not
  strictly named in the acceptance criteria above, but leaving the
  visual-sort-group named `setup` while the item/route/class were all
  `insights` would have been an inconsistent half-rename.
- **Drive-by fixes to stale documentation found while renaming**:
  `HivelogAppNavBuilder::GROUP_ORDER`'s own docblock still claimed
  `build()` "doesn't yet partition by `parent` (task 0148 does)" —
  stale since task 0148 shipped; corrected while touching this exact
  comment for the rename. `AGENTS.md`'s "In-app navigation" section
  (written in task 0151, the same day as this task) still named
  `SetupController`/`hivelog.setup`/the `setup` group in three places —
  updated rather than left for task 0154, since leaving core
  documentation actively wrong for a whole extra task cycle seemed
  worse than the small extra diff here.
- **Live-verified on `cms2`** via `drush php:eval` (not a browser
  screenshot — this session's browser tool blocks every real asset
  request to `cms2`, see task 0149's own notes): `hivelog.insights`
  resolves to `/hivelog/insights`, `HivelogAppNavBuilder::build()`
  shows `insights -> collective_api_clients, nexus_ai_provider_configs,
  nanoprobe_sensor_devices` for an admin, and the breadcrumb service
  renders exactly `Home › HiveLog › Insights` for that route.
- **A stale-test race caught and correctly diagnosed, not chased as a
  regression**: a full-suite run started *before* this rename began
  (queued from task 0151) finished mid-rename against the same shared
  `cms2` checkout this task's own `rsync` was actively overwriting —
  it hit a fatal `AssertionError: assert(method_exists(...))` from
  PHPUnit's own metadata cache, because a test method it had already
  indexed (`testAiProviderConfigsItemParentsUnderSetup`) was renamed
  out from under it mid-run. Not a real defect — confirmed by a fresh,
  uncontested full-suite run afterward. Lesson: don't `rsync` into
  `cms2` while an earlier background suite against it is still running.
- Key files: `hivelog.routing.yml`, `src/Controller/SetupController.php`
  (rename → `InsightsController.php`), `src/Access/SetupPageAccessCheck.php`
  (rename → `InsightsPageAccessCheck.php`), `src/HivelogAppNavBuilder.php`,
  `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `modules/{collective,nanoprobe,nexus}/*.module`,
  `tests/src/Kernel/SetupTest.php` (rename →
  `InsightsTest.php`), `tests/src/Kernel/HivelogAppNavBuilderTest.php`,
  `tests/src/Kernel/HivelogMenuLinksTest.php`,
  `tests/src/Kernel/RouteEntityAccessTest.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`,
  `modules/*/tests/src/Kernel/AppNavItemsTest.php`,
  `tests/modules/hivelog_app_nav_test/hivelog_app_nav_test.module`,
  `AGENTS.md`.
- No entity schema change → **no update hook required** — this is a
  route/class/label rename, not a field change.
- phpcs clean; phpstan clean (module-wide, no baseline changes). Full
  kernel/unit/functional suite against `cms2`, core and every
  submodule: **1,076 tests, 0 failures** (fresh run, after the
  stale-race run above was correctly discarded).

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
- Tasks:: [[0146-setup-landing-page-and-route]]
- Commits::
