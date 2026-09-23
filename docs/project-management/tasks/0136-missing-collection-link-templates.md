---
type: task
tags: [hivelog/task]
status: todo
priority: low
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0136-missing-collection-link-templates
release:
depends-on:
blocked-by:
---
# Task: Add the missing `collection` link templates

## Context
From the 2026-09-23 gap analysis. Five entity types have an
`entity.<type>.collection` route in `hivelog.routing.yml`, but no
`collection` entry in their entity attribute's `links`:

| Entity | links today |
|---|---|
| Hive | canonical, edit-form, delete-form |
| HiveInspection | canonical, edit-form, delete-form |
| CalendarAction | canonical, edit-form, delete-form |
| HiveActionLog | canonical, edit-form, delete-form |
| ApiaryActionLog | canonical, edit-form, delete-form |

So `$entity->toUrl('collection')` and
`$entity_type->getLinkTemplate('collection')` fail for these types.
Several planned refactors resolve collection pages generically and
would need special cases without this:
[[0116-breadcrumb-builder-parent-map-refactor]],
[[0119-single-source-navigation-registry]] and
[[0127-shared-delete-form-base]].

Relatedly, `CalendarAction` registers `CalendarActionListBuilder` as its
`list_builder` handler, while its collection route is served by
`CalendarActionController::collection()` instead.

## Acceptance criteria
- [ ] `collection` link template added to the five entity types, with
      paths matching their existing routes (`/hivelog/hives`,
      `/hivelog/inspections`, `/hivelog/calendar-actions`,
      `/hivelog/hive-action-logs`, `/hivelog/apiary-action-logs`).
- [ ] Confirm the explicit route definitions keep winning (same route
      name, `entity.<type>.collection`) and nothing gets
      double-registered. `drush route:debug` / router rebuild clean.
- [ ] Kernel test: every hivelog entity type that has an
      `entity.<type>.collection` route also has a `collection` link
      template (discovered, not hand-listed).
- [ ] `CalendarActionListBuilder`: either used by the collection page
      (after [[0126-unified-list-page-base]]) or documented as the
      handler only for non-route callers. Decide and record.
- [ ] `ddev drush cr` clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Link templates are entity type definitions, not field storage. No
  update hook needed.
- Key files: `src/Entity/Hive.php`, `HiveInspection.php`,
  `CalendarAction.php`, `HiveActionLog.php`, `ApiaryActionLog.php`.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]],
  [[0119-single-source-navigation-registry]],
  [[0126-unified-list-page-base]], [[0127-shared-delete-form-base]]
- Commits::
