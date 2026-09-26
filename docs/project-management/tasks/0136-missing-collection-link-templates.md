---
type: task
tags: [hivelog/task]
status: done
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
- [x] `collection` link template added to the five entity types, with
      paths matching their existing routes (`/hivelog/hives`,
      `/hivelog/inspections`, `/hivelog/calendar-actions`,
      `/hivelog/hive-action-logs`, `/hivelog/apiary-action-logs`).
- [x] Confirm the explicit route definitions keep winning (same route
      name, `entity.<type>.collection`) and nothing gets
      double-registered. `drush route:debug` / router rebuild clean.
- [x] Kernel test: every hivelog entity type that has an
      `entity.<type>.collection` route also has a `collection` link
      template (discovered, not hand-listed).
- [x] `CalendarActionListBuilder`: either used by the collection page
      (after [[0126-unified-list-page-base]]) or documented as the
      handler only for non-route callers. Decide and record.
- [x] `ddev drush cr` clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Route conflict was never actually possible** — these entity types
have no `handlers.route_provider` configured at all (unlike an entity
type using Drupal's automatic `DefaultHtmlRouteProvider`, which
*would* generate a route from a link template), so a `collection` link
template is purely a URL-generation convenience for
`$entity->toUrl('collection')` / `hasLinkTemplate()` callers — it has
no power to register or conflict with a route. Confirmed live on
`cms2` anyway, per the AC's own instruction: each of the five routes
still resolves to exactly one route, served by the exact same
controller/`_entity_list` handler as before (`entity.calendar_action.collection`
still `CalendarActionController::collection()`, not `_entity_list`).

**`CalendarActionListBuilder` question was already answered by task
0126**, not re-litigated here: its own class docblock already records
that it's "not dead, just not routed to directly" — the live
`/hivelog/calendar-actions` route uses `CalendarActionController::collection()`
for its filter form, but the list builder stays the registered
`list_builder` handler (reachable through the generic entity API) and
is exercised directly by `HiveCalendarChecklistTest` as an unfiltered
comparison point against the controller's filtered view. Nothing
about adding the `collection` link template changes that division —
recorded here only because this task's own AC asked for it to be, not
because there was a new decision to make.

**Found and fixed a real, if narrow, ripple effect**: `HivelogEntityHierarchy::parentOrCollectionUrl()`
(the delete form's post-delete-redirect/cancel-fallback logic) checks
`hasLinkTemplate('collection')` as its third-priority fallback, after
the parent's own canonical page. For all five of these types, that
branch was previously unreachable in the normal case (the parent
almost always resolves), but for the exact scenario ADR-0103's own
context describes — an existing dangling/orphaned reference (1,024
calendar actions, 9 inventory items on `cms2`, or any future BLOCK
row's raw-API bypass) — `resolveParent()` returns `NULL` and the
fallback used to go straight to the bare dashboard. It now correctly
lands on the entity's own collection page instead, which is a genuine
improvement, not a behaviour regression to guard against. It did
break one existing kernel test that encoded the old fallback as its
expectation: `HivelogEntityDeleteFormTest`'s own `hierarchyRules()`
data provider already had an `own_collection` field built for exactly
this case (used correctly for `apiary`/`queen`/`queen_observation`/
`inventory_item`/`inventory_purchase`/`product`, which already had the
link template) — it was simply `NULL` for these five, since the link
template didn't exist yet. Set to the real route name for all five;
no new test logic needed, the harness was already there waiting for
this task.

**Verification.** phpcs clean, phpstan clean (module-wide, no baseline
changes). New `CollectionLinkTemplateTest` (discovers hivelog's own
entity types generically via the entity type manager + route provider,
not a hand-maintained list — a future type with the same gap now fails
immediately) plus the corrected `HivelogEntityDeleteFormTest`: full
kernel suite 728 tests, zero failures, only the 3 pre-existing
unrelated `DashboardTest` Functional errors already documented in
prior tasks. Live-verified on `cms2`: all five routes resolve singly
to their existing controllers; `$hive->toUrl('collection')` resolves
correctly; and, reproducing the real dangling-reference scenario
directly (deleted an apiary via the raw entity API — bypassing the
BLOCK access check the way `cms2`'s own existing 1,024/9 orphans
were created — leaving its hive with a reference to a now-gone
apiary), `parentOrCollectionUrl()` correctly returned the Hives
collection instead of the dashboard. Throwaway fixtures cleaned up
after.

- Link templates are entity type definitions, not field storage. No
  update hook needed.
- Key files: `src/Entity/Hive.php`, `HiveInspection.php`,
  `CalendarAction.php`, `HiveActionLog.php`, `ApiaryActionLog.php`,
  `tests/src/Kernel/CollectionLinkTemplateTest.php` (new),
  `tests/src/Kernel/HivelogEntityDeleteFormTest.php` (data fix).

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]],
  [[0119-single-source-navigation-registry]],
  [[0126-unified-list-page-base]], [[0127-shared-delete-form-base]]
- Commits::
