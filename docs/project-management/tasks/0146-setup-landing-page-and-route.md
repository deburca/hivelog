---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] New route `hivelog.setup` at `/hivelog/setup`, `SetupController::view()`
      + `::title()` (title "Setup"), no entity parameter.
- [ ] Access is a custom check, not a fixed `_permission` string: "is at
      least one nav item with `parent === 'setup'` accessible to the
      current user", read from `HivelogAppNavBuilder::getAllItems()` (or
      a small dedicated method on it) — never a permission name from a
      specific submodule, so `hivelog` core keeps depending on none of
      them (mirrors how `build()` already filters items by
      `$item['url']->access()`).
- [ ] Page body lists each accessible `parent: 'setup'` item as a plain
      link (title + URL), empty-state message if the check above somehow
      passes but the list renders empty (defensive, shouldn't normally
      happen since the access check and the list read the same data).
- [ ] Breadcrumb: `Home › HiveLog › Setup` — a named sub-page terminal
      crumb (`TERMINAL_CRUMB_PARAM`), the same mechanism Hive Insights /
      full-calendar already use, since this page has no canonical
      entity subject to thread through `HivelogEntityHierarchy`.
- [ ] Kernel tests: access granted when ≥1 `setup`-parented item is
      accessible, denied when none are (e.g. no optional submodule
      installed), page lists exactly the accessible children, breadcrumb
      trail asserted.
- [ ] phpcs clean; phpstan clean (module-wide, no new baseline entries).

## Implementation notes
- Key files: `hivelog.routing.yml` (new route), new
  `src/Controller/SetupController.php`, `src/HivelogAppNavBuilder.php`
  (the "which items are `setup`-parented and accessible" query this
  task needs is the same one [[0147-app-nav-registry-parent-key]] and
  [[0148-two-tier-app-nav-strip-rendering]] will reuse — consider adding
  it as one small public method now rather than duplicating the filter
  logic across three tasks), `src/Breadcrumb/HivelogBreadcrumbBuilder.php`
  (`TERMINAL_CRUMB_PARAM` route option), `tests/src/Kernel/`.
- No entity schema change → **no update hook required**.
- The registry has no `parent` key yet at this point in the sequence —
  this task only needs the *page* to exist; wiring "Setup" into
  `getAllItems()` as a registry item is [[0147-app-nav-registry-parent-key]].

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Commits::
