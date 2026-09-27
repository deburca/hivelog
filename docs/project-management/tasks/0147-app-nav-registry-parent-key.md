---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `hivelog.api.php`'s `hook_hivelog_app_nav_items()` docblock
      documents the new optional `parent` key: the key of another item
      (core's own or another module's) this one nests under; omit for a
      top-level item. Update the example in the docblock.
- [ ] `HivelogAppNavBuilder::builtInItems()`: add `'parent' => 'apiaries'`
      to `hives`, `inspections`, `queens`, `queen_observations`,
      `inventory_items`, `inventory_purchases`, `products`. Add a new
      built-in `setup` item (title "Setup", `Url::fromRoute('hivelog.setup')`
      from [[0146-setup-landing-page-and-route]], weight placing it after
      `apiaries` in `GROUP_ORDER`'s `setup` position, no `parent` — it's
      primary).
- [ ] `collective_hivelog_app_nav_items()`, `nanoprobe_hivelog_app_nav_items()`,
      `nexus_hivelog_app_nav_items()`: each existing item gains
      `'parent' => 'setup'`.
- [ ] `GROUP_ORDER`'s docblock updated — `inventory` no longer has any
      primary-tier member (its only current member becomes a
      `parent: 'apiaries'` secondary item), only `records` (Apiaries)
      and `setup` (the new Setup item) do.
- [ ] Existing `HivelogAppNavBuilderTest`/equivalent kernel or unit
      tests (whichever file covers `getAllItems()`/`builtInItems()`)
      pass unchanged, plus new assertions that the expected items now
      carry the expected `parent` value and the new `setup` built-in
      exists and resolves a real accessible-when-installed URL.
- [ ] Each submodule's own `AppNavItemsTest` (collective, nanoprobe,
      nexus) updated to assert its item's new `parent` value.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `hivelog.api.php`, `src/HivelogAppNavBuilder.php`,
  `modules/collective/collective.module`,
  `modules/nanoprobe/nanoprobe.module`, `modules/nexus/nexus.module`,
  the four modules' own `tests/src/Kernel/AppNavItemsTest.php`.
- No entity schema change → **no update hook required**.
- Deliberately does not touch `build()` or `HivelogMenuLinks` — both
  still treat every item as flat and simply ignore the new key until
  [[0148-two-tier-app-nav-strip-rendering]] / [[0150-two-tier-main-menu-links]]
  land. Confirms the registry change alone is safe before the render
  rewrite.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]]
- Commits::
