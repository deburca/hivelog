---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] Apiary and Hive canonical pages render their own Edit / Delete
      button group, same component, placement and access checks
      (`access('update')` / `access('delete')`) as the other detail
      pages, e.g. `QueenController::buildActions()`.
- [ ] Sensor device, API client and AI provider config canonical pages
      do the same.
- [ ] Every canonical page's Edit / Delete now comes from the page.
      Verified on `cms2` for all 16 canonical page types.
- [ ] `hivelog.links.task.yml` deleted **after** the above, in the same
      branch. The top bar then shows no hivelog tabs, and no page loses
      its controls.
- [ ] `hivelog.links.action.yml` deleted.
- [ ] No code, test or doc still references either file or their plugin
      IDs (`hivelog.*.view_tab`, `.edit_tab`, `.delete_tab`,
      `hivelog.apiary.add`, `hivelog.queen.add_action`). `grep` the
      repo, including `docs/`.
- [ ] AGENTS.md "Routing, controllers and forms" updated: page-owned
      buttons are the only mechanism; no local tasks or actions ship.
- [ ] Kernel tests: each newly added button group appears for a user
      with update / delete access and is absent without it (mirror
      `ApiClientControllerTest::testRegenerateActionHiddenWithoutUpdateAccess`).
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- If [[0125-shared-detail-page-builder]] (the shared `buildActions()`
  now copied across 9 controllers) lands first, use it here rather than
  adding a 10th–14th copy.
- Alternative considered: keep the tabs and add them for all 13 entity
  types instead. Rejected. It depends on a module (Navigation) and a
  permission the module doesn't control, and it would still duplicate
  the page buttons on the 8 pages that have them.
- No update hook needed.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0105-submodule-navigation-menu-links]],
  [[0068-replace-dropbutton-operations]],
  [[0113-destructive-action-styling-sensor-device-api-client]],
  [[0125-shared-detail-page-builder]] ([[page-structure-consistency]])
- Commits::
