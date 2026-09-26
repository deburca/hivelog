---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0133-route-level-entity-access
release:
depends-on:
blocked-by:
---
# Task: Enforce per-entity access on every entity route

## Context
**Critical security bug** (insecure direct object reference), found in
the 2026-09-23 gap analysis ([[page-structure-consistency]]).
[[0020-access-parity-custom-routes]] says custom routes use
`_entity_access` requirements. In practice **one** hivelog route does.
Every canonical, edit, delete, scoped-add and sub-page route checks
only `_permission` (e.g. `edit own hive+edit any hive+administer
hivelog`), and a permission says nothing about *which* hive. Core's
`_entity_form` controller doesn't check entity access, and none of the
hivelog core view controllers or entity forms check it either.

**Reproduced** with a throwaway kernel test against `cms2` (deleted
afterwards). Alice owns an apiary and a hive. Bob has the same
`view/edit/delete own apiary|hive` + `add hive` permissions but no
relation to Alice's records. `access_manager->checkNamedRoute()` for
Bob returned **ALLOWED** on:
`entity.apiary.canonical`, `.edit_form`, `.delete_form`,
`entity.hive.canonical`, `.edit_form`, `.delete_form`,
`entity.hive.insights` and `hivelog.hive.add` (adding a hive to Alice's
apiary). Meanwhile `$hive->access('view' / 'update' / 'delete', $bob)`
all return FALSE. The access handlers are right; the routes never ask
them.

Scope: all 15 core entity types' routes (`hivelog.routing.yml`, ~70
routes) plus the submodules. `SensorDeviceController::view()` and
`ApiClientController::view()` do check `access('view')` in the
controller, but their edit / delete (`_entity_form`) routes, the sensor
readings / config pages and the API-client regenerate-token route rely
on `_permission` alone.

The existing tests missed this because all cross-user access tests
(`ApiaryScopedAccessTest`, `ProductAccessTest`, …) call
`$entity->access()` directly and never go through a route.
`PermissionMatrixTest` (functional, advisory in CI) doesn't try another
user's records.

## Acceptance criteria
- [x] Every route with an upcast entity parameter carries
      `_entity_access: '<param>.<op>'` alongside (not instead of) its
      `_permission`:
      - canonical and read-only sub-pages (Insights, Calendar, Financial
        Report, Readings): `.view`
      - `edit_form` and update-style sub-pages (regenerate token,
        sensor config download, which invalidates the old config):
        `.update`
      - `delete_form`: `.delete`
- [x] Scoped add routes check the **parent**. `hivelog.hive.add`
      requires update access on `{apiary}`, and the same goes for
      inspection / queen / observation / calendar-action / log /
      inventory / product / requirement / yield / sensor-device add
      routes. Add plain `_entity_create_access` where the create check
      also needs to hold. Record the chosen parent operation (`view` vs
      `update`) in Implementation notes and keep it the same everywhere.
- [x] `hivelog.apiary_action_log.add` / `hivelog.hive_action_log.add`
      also check `view` on `{calendar_action}`, and that the calendar
      action belongs to the same apiary as the route's `{apiary}` /
      `{hive}`. That stops a user logging against another apiary's
      action by URL.
- [x] Submodule routes (nanoprobe, collective, nexus) get the same
      treatment. The in-controller `access('view')` checks may stay as
      a second line of defence.
- [x] New kernel test, `RouteEntityAccessTest` (data provider over
      **every** route in all four routing files, discovered from the
      router rather than hand-listed, so a new route can't be missed):
      as a user with the relevant "own" permissions but no
      relationship to the record, `checkNamedRoute()` is denied. As
      the owner, an apiary beekeeper member and `administer hivelog`,
      it is allowed. Place per-submodule cases in each submodule's
      tests.
- [x] Routes with no entity parameter (collections, dashboard, reports
      across apiaries, the two token-authenticated API endpoints) are
      listed explicitly in the test as exempt, with a reason.
- [x] [[0020-access-parity-custom-routes]] gets a short addendum saying
      the route test now enforces its rule.
- [x] phpcs clean; kernel + unit suite green against `cms2`; spot-check
      live on `cms2` with a non-admin test user (another user's hive
      edit URL → 403).
- [ ] **Release**: ship as a security patch release together with
      [[0124-list-page-row-access-filter]], with a release note telling
      site owners to update promptly.

## Implementation notes
- `_permission` and `_entity_access` in the same `requirements` block
  are AND-ed by Drupal's access manager. Keep both: the permission
  stays a cheap coarse gate, and the entity check adds ownership /
  membership.
- Entity access results already carry cacheability from the access
  handlers; nothing extra is needed for render caching.
- Also check the upcasting of every `{param}` (all routes already
  declare `options.parameters … type: entity:<type>`; confirmed in the
  gap analysis). `_entity_access` needs the upcast object.
- Key files: `hivelog.routing.yml`, `modules/*/*.routing.yml`, new
  `tests/src/Kernel/RouteEntityAccessTest.php` (+ submodule
  counterparts).

**Implemented 2026-09-23.**
- **Parent operation: `update`, everywhere.** Every scoped-add route
  (`hivelog.hive.add`, `.inspection.add`, `.queen.add`,
  `.queen_observation.add`, `.calendar_action.add`, `.hive_action_log.add`,
  `.apiary_action_log.add`, `.inventory_item.add`, `.inventory_purchase.add`,
  `.product.add`, `.calendar_action_item_requirement.add`,
  `.calendar_action_product_yield.add`, and nanoprobe's
  `.sensor_device.add_for_hive` / `.add_for_apiary`) carries
  `_entity_access: '<parent>.update'`, matching the task's own
  `hivelog.hive.add` example. Every one of these parents' `update`
  operation resolves via `checkApiaryEditAccess()` (apiary-member
  scoped), so a beekeeper member — not just the owner — can add
  children, matching what the UI already let them do before this task.
  Paired with `_entity_create_access: '<child_type>'` for defence in
  depth on the create side.
- **Same-apiary check:** new `\Drupal\hivelog\Access\
  CalendarActionApiaryMatchAccessCheck` (`src/Access/`), wired via
  `_custom_access` on `hivelog.hive_action_log.add` and
  `.apiary_action_log.add`. Resolves both `{calendar_action}` and
  whichever of `{apiary}`/`{hive}` the route carries through
  `ApiaryAccessTrait::resolveApiary()` and compares apiary IDs.
- **Admin-only add routes** (`entity.sensor_device.add_form` and its two
  contextual add routes, `entity.api_client.add_form`,
  `entity.ai_provider_config.add_form`) still get `_entity_access` /
  `_entity_create_access` for defence in depth, but since
  `administer hivelog` bypasses every access handler's `checkAccess()`
  unconditionally, they aren't part of the IDOR surface and are excluded
  from the automated outsider/owner denial loop in
  `RouteEntityAccessTest` (documented in that test's class docblock).
- **Route discovery test gotcha:** a submodule's kernel test also has
  `hivelog` installed (it's a dependency), so `router.route_provider`
  returns hivelog core's own routes too. Each submodule's
  `RouteEntityAccessTest::hivelogRoutes()` filters by route name prefix
  (`nanoprobe.*` / `entity.sensor_device.*`, etc.), not by the `/hivelog`
  path prefix every route shares.
- Verified live on `cms2`: a throwaway non-admin user with only
  `view/edit/delete own hive` (no apiary relation) got 403 on another
  user's hive canonical/edit/delete pages; the actual owner kept normal
  200 access. Full kernel/unit suite run against `cms2` to confirm no
  regressions.
- **Beekeeper members can't add apiary-direct structure, only the
  owner.** `apiary.update` (used as the parent check on every scoped-add
  route whose parent is `{apiary}` directly — `hivelog.hive.add`,
  `.calendar_action.add`, `.apiary_action_log.add`, `.inventory_item.add`,
  `.inventory_purchase.add`, `.product.add`, and `entity.apiary.edit_form`
  itself) is **owner-only** per `ApiaryAccessControlHandler` ("Only
  apiary owner can edit the apiary itself") — unlike every child type's
  own `update`, which is member-scoped. So a beekeeper member can edit
  an apiary's existing hives/inspections/queens/etc. (member-scoped) but
  cannot add a new hive, calendar action, inventory item/purchase, or
  product to it, nor edit the apiary itself — only the owner can. This
  is a real, if narrow, behaviour change from the (buggy) status quo,
  where the "Add Hive" button on the apiary page was never
  access-gated at all and any viewer with the `add hive` permission
  could reach the route. Deliberate: it matches the ownership rule
  already used for structural deletes (`Hive`/`CalendarAction`/
  `Product`'s `delete` is owner-only too), and it's stricter, not
  looser, so it closes rather than reopens the IDOR. Covered by
  `RouteEntityAccessTest::testBeekeeperMemberDeniedOnApiaryOwnerOnlyRoutes()`.
  Flag in the release note if this trips up an existing site's
  beekeepers.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0020-access-parity-custom-routes]],
  [[0019-authorisation-model]]
- Tasks:: [[0124-list-page-row-access-filter]] (ship together),
  [[0137-align-lint-static-analysis-and-test-gates]] (make this test a
  hard gate)
- Commits::
