---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[in-app-navigation-restructuring]]"
area: routing
created: 2026-09-27
branch: feature/0146-setup-landing-page-and-route
release:
depends-on:
blocked-by:
---
# Task: "Setup" landing page and route

## Context
[[0104-two-tier-in-app-navigation]] makes "Setup" a new primary nav
item, the parent hub for the `setup`-group items (`collective`'s API
Clients, `nexus`'s AI Provider Configs, `nanoprobe`'s Sensor Devices).
Unlike "Apiaries", "Setup" has no existing page to point at — it needs
a real destination before [[0147-app-nav-registry-parent-key]] can add
it to the registry as a working `Url::fromRoute()` target. Mirrors
`DashboardController`'s own shape: a thin controller that lists
whichever accessible children exist, with no hard-coded dependency on
any specific submodule.

## Acceptance criteria
- [x] New route `hivelog.setup` at `/hivelog/setup`, `SetupController::view()`
      + `::title()` (title "Setup"), no entity parameter.
- [x] Access is a custom check, not a fixed `_permission` string: "is at
      least one nav item with `parent === 'setup'` accessible to the
      current user", read from `HivelogAppNavBuilder::getAllItems()` (or
      a small dedicated method on it) — never a permission name from a
      specific submodule, so `hivelog` core keeps depending on none of
      them (mirrors how `build()` already filters items by
      `$item['url']->access()`).
- [x] Page body lists each accessible `parent: 'setup'` item as a plain
      link (title + URL), empty-state message if the check above somehow
      passes but the list renders empty (defensive, shouldn't normally
      happen since the access check and the list read the same data).
- [x] Breadcrumb: `Home › HiveLog › Setup`. **Corrected during
      implementation:** not `TERMINAL_CRUMB_PARAM` as originally
      planned — that map threads a *subject entity's* ancestor chain in
      front of a literal label, and this page resolves no subject at
      all (`HivelogEntityHierarchy::resolveSubject()` returns NULL for
      it, same as `hivelog.dashboard` itself). Used the `$leaf_pages`
      self-link mechanism instead — the same one
      `hivelog.apiaries.financial_report` already uses for exactly the
      same reason.
- [x] Kernel tests: access granted when ≥1 `setup`-parented item is
      accessible, denied when none are (e.g. no optional submodule
      installed), page lists exactly the accessible children, breadcrumb
      trail asserted.
- [x] phpcs clean; phpstan clean (module-wide, no new baseline entries).

## Implementation notes
- **Correctness fix caught during implementation, not in the plan
  above:** `HivelogAppNavBuilder::getAccessibleChildren()` must accept
  an explicit `?AccountInterface $account` and pass it through to
  `$item['url']->access($account)`, not default to the current user
  internally. `SetupPageAccessCheck::access()` is handed a specific
  `$account` by Drupal's access-checking framework
  (`AccessManager::checkNamedRoute()` can check access as any account
  without switching who's actually logged in — exactly what
  `RouteEntityAccessTest` does throughout this module), so silently
  substituting the current user instead would answer the wrong
  question for that exact call shape. Covered by
  `HivelogAppNavBuilderTest::testGetAccessibleChildrenRespectsExplicitAccountOverCurrentUser()`.
  `build()`'s own existing access-filtering is unaffected — it never
  passed an account either, and still doesn't need to (it always
  renders for the current request's user).
- **New test-only module**, `tests/modules/hivelog_app_nav_test/`
  (mirrors `hivelog_delete_dependency_test`'s established pattern):
  implements `hook_hivelog_app_nav_items()` with one `parent: 'setup'`
  item pointing at the real `entity.apiary.collection` route, rather
  than defining a route/entity/permission of its own — lets the kernel
  tests control the fake item's own accessibility with ordinary
  permission grants (`view own apiary`) instead of test-only plumbing.
  `parent` is not a registry-wide convention yet (that's task 0147);
  this is the only place it exists before then.
- **`RouteEntityAccessTest::EXEMPT_ROUTES`** needed a new entry —
  `hivelog.setup` has no entity parameter and uses `_custom_access`, so
  that whole-module route audit would otherwise fail on it.
- **`create()` returns `self`, not `static`** on both new classes
  (`SetupController`, `SetupPageAccessCheck`) — phpstan's
  `new.static` finding, same reasoning `HivelogMenuLinks::create()`'s
  own docblock already gives: neither class is expected to be
  subclassed.
- Key files: `hivelog.routing.yml` (new route), `src/Controller/SetupController.php`
  (new), `src/Access/SetupPageAccessCheck.php` (new),
  `src/HivelogAppNavBuilder.php` (`getAccessibleChildren()` added —
  reused as-is by [[0147-app-nav-registry-parent-key]] and
  [[0148-two-tier-app-nav-strip-rendering]]),
  `src/Breadcrumb/HivelogBreadcrumbBuilder.php` (`$leaf_pages` entry),
  `tests/src/Kernel/SetupTest.php` (new, 8 tests),
  `tests/src/Kernel/HivelogAppNavBuilderTest.php` (3 new tests for
  `getAccessibleChildren()`), `tests/src/Kernel/RouteEntityAccessTest.php`
  (`EXEMPT_ROUTES` entry), `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`
  (`leafPageProvider` entry), `tests/modules/hivelog_app_nav_test/` (new).
- No entity schema change → **no update hook required**.
- Live-verified on `cms2`: `/hivelog/setup` correctly returns "Access
  denied" for the current admin user, since no submodule declares a
  `parent: 'setup'` item yet — expected, matches ADR-0104's own "no
  submodule installed" reasoning exactly. The accessible/populated case
  is covered by the kernel tests via `hivelog_app_nav_test` (a
  test-only module, not installable on a real site); it becomes
  observable for real once [[0147-app-nav-registry-parent-key]] wires
  `parent: 'setup'` onto the real submodule items.
- Full kernel/unit/functional suite against `cms2`, core and every
  submodule: 798 tests, 0 failures (up from 787 — 11 new: 8 in
  `SetupTest`, 3 in `HivelogAppNavBuilderTest`).

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Commits::
