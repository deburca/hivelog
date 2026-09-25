---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0118-page-owned-edit-delete-then-retire-local-tasks
release:
depends-on:
blocked-by:
---
# Task: Every canonical page owns its Edit / Delete, then retire local tasks and actions

> **Corrected 2026-09-23, same day, before commit.** This task was first
> drafted as "remove the unused local tasks and actions". The navigation
> review's check found no tab markup in the page body, but missed that
> Drupal's **Navigation** module top bar renders local tasks as an
> Edit / Delete dropdown (`toolbar-dropdown__link`). On `cms2`, the
> `authenticated` role has `access navigation`, so every logged-in user
> sees it. For **Apiary and Hive** that dropdown is the *only* Edit /
> Delete on the page. Deleting the yml first would have removed it.
> Found during the page-structure review.

## Context
Where a canonical page's Edit / Delete comes from today, checked live on
`cms2`:

| Canonical page | Page-owned Edit / Delete buttons | Local-task tabs (Navigation top bar) |
|---|---|---|
| Apiary, Hive | **no** | yes (the only source) |
| Inspection, Queen, Queen observation | yes | yes (duplicate) |
| Calendar action, Hive / Apiary action log, Inventory item, Inventory purchase, Product | yes | no |
| Sensor device, API client, AI provider config | **no** | **no**: editable only from the collection row |

The module's own convention is page-owned buttons
([[0012-action-button-design-system]]; AGENTS.md "Routing, controllers
and forms"), because a theme or toolbar placing local tasks is never
guaranteed. The top bar exists only where the Navigation module is
enabled and the user has `access navigation`.

`hivelog.links.action.yml` (Add Apiary, Add Queen) is a separate case.
Local actions are *not* shown in the top bar, and the only "Add"
buttons visible on `cms2` are the ones the list builders render
themselves. On an admin theme that places the local-actions block, both
would show.

## Acceptance criteria
- [x] Apiary and Hive canonical pages render their own Edit / Delete
      button group, same component, placement and access checks
      (`access('update')` / `access('delete')`) as the other detail
      pages, e.g. `QueenController::buildActions()`.
- [x] Sensor device, API client and AI provider config canonical pages
      do the same.
- [x] Every canonical page's Edit / Delete now comes from the page.
      Verified on `cms2` for all 16 canonical page types.
- [x] `hivelog.links.task.yml` deleted **after** the above, in the same
      branch. The top bar then shows no hivelog tabs, and no page loses
      its controls.
- [x] `hivelog.links.action.yml` deleted.
- [x] No code, test or doc still references either file or their plugin
      IDs (`hivelog.*.view_tab`, `.edit_tab`, `.delete_tab`,
      `hivelog.apiary.add`, `hivelog.queen.add_action`). `grep` the
      repo, including `docs/`.
- [x] AGENTS.md "Routing, controllers and forms" updated: page-owned
      buttons are the only mechanism; no local tasks or actions ship.
- [x] Kernel tests: each newly added button group appears for a user
      with update / delete access and is absent without it (mirror
      `ApiClientControllerTest::testRegenerateActionHiddenWithoutUpdateAccess`).
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**`buildActions()` extracted out of `HivelogDetailPageTrait` into a new
`HivelogEntityActionsTrait`.** The task's own note said to reuse 0125's
shared `buildActions()` rather than add a 10th–14th copy — but
`HivelogDetailPageTrait` also declares `formatFieldValue()` as an
abstract method (for `buildSection()`'s field rendering), which Apiary,
Hive, SensorDevice, ApiClient and AiProviderConfig don't want at all
(Apiary/Hive render their fields via the default entity view builder;
the three submodule pages build a custom summary table directly). Using
the full trait would have forced each of these five controllers to
implement an unused abstract method just to get one small button group.
Splitting `buildActions()` into its own trait, which
`HivelogDetailPageTrait` itself now `use`s, gives both groups what they
actually want: the nine existing controllers keep `buildActions()`
unchanged (via `HivelogDetailPageTrait`), and the five new ones use
`HivelogEntityActionsTrait` directly with no unused-method obligation.

**Found and fixed a real regression the local-task retirement exposed,
not introduced:** `PermissionMatrixTest::testAdministerHivelogBypassesGranularChecks`
asserted a Delete link on `$this->apiary`'s canonical page — but that
fixture apiary has a BLOCK-treatment hive child, so
`buildActions()`'s deliberate `access('delete')` check (which includes
the BLOCK rule, ADR-0103/task 0141 — see
`HivelogDeleteBlockRelationshipsTest::testDeleteRouteAccessStaysAllowedWhileDeleteOpIsBlocked()`'s
own docblock) correctly hides the button. The *old* local task tab
didn't have this problem because it was never BLOCK-aware in the first
place — a local task's visibility follows its target route's own
access requirement, and `entity.apiary.delete_form`'s route
deliberately uses the BLOCK-exempt `delete_route` operation (so a
stale/bookmarked link still reaches the form's "Can't delete yet"
explanation instead of a bare 403) — so the tab always showed
regardless of BLOCK status, even for the 4 entity types that already
had page-owned buttons *and* no tab, where the button was already
correctly BLOCK-aware. Retiring the tab makes the *button* the only
mechanism everywhere, which surfaces this exact case for Apiary/Hive
for the first time. Briefly considered making `buildActions()`'s Delete
check `access('delete') || access('delete_route')` instead (button
stays visible, links through to the explanatory form) — reverted: that
would change already-established, working behaviour on the 6 entity
types that define `delete_route` (Apiary, Hive, Queen, CalendarAction,
InventoryItem, Product), not just fix Apiary/Hive, and
`HivelogDeleteBlockRelationshipsTest`'s own docblock already documents
"every Delete button/tab that calls `access('delete')` directly...
disappear[s]" as the intended behaviour. Fixed the test's stale
expectation instead — `linkByHrefExists` → `linkByHrefNotExists` for
Delete on the blocked fixture, with the reasoning recorded inline — and
removed the now-pointless `drupalPlaceBlock('local_tasks_block')` /
`'block'` module dependency from that test file, since no hivelog
local task exists to render there any more.

**Update hook:** none needed, as anticipated — `label_collection`/route
deletion isn't field storage, and no data migration is required.

**Verification.** phpcs clean, phpstan clean. Full kernel + unit +
functional suite (hivelog core + collective/nexus/nanoprobe kernel
tests): 952 tests, zero failures, zero new errors — only the 3
pre-existing, unrelated `DashboardTest` Functional cache-redirect
errors already documented in tasks 0116/0119/0120 (Functional is
advisory per CI policy anyway). Live-verified on `cms2` via `drush
php-eval` with throwaway fixtures (all cleaned up after): an unblocked
apiary and hive both render Edit+Delete; a blocked apiary (real hive
child) renders Edit only; SensorDevice/ApiClient/AiProviderConfig all
render Edit+Delete; `plugin.manager.menu.local_task` and
`plugin.manager.menu.local_action` both confirm zero remaining
`hivelog.*` definitions of either kind.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0105-submodule-navigation-menu-links]],
  [[0068-replace-dropbutton-operations]],
  [[0113-destructive-action-styling-sensor-device-api-client]],
  [[0125-shared-detail-page-builder]] ([[page-structure-consistency]])
- Commits::
