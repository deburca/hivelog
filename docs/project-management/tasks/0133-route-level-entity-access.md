---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] Every route with an upcast entity parameter carries
      `_entity_access: '<param>.<op>'` alongside (not instead of) its
      `_permission`:
      - canonical and read-only sub-pages (Insights, Calendar, Financial
        Report, Readings): `.view`
      - `edit_form` and update-style sub-pages (regenerate token,
        sensor config download, which invalidates the old config):
        `.update`
      - `delete_form`: `.delete`
- [ ] Scoped add routes check the **parent**. `hivelog.hive.add`
      requires update access on `{apiary}`, and the same goes for
      inspection / queen / observation / calendar-action / log /
      inventory / product / requirement / yield / sensor-device add
      routes. Add plain `_entity_create_access` where the create check
      also needs to hold. Record the chosen parent operation (`view` vs
      `update`) in Implementation notes and keep it the same everywhere.
- [ ] `hivelog.apiary_action_log.add` / `hivelog.hive_action_log.add`
      also check `view` on `{calendar_action}`, and that the calendar
      action belongs to the same apiary as the route's `{apiary}` /
      `{hive}`. That stops a user logging against another apiary's
      action by URL.
- [ ] Submodule routes (nanoprobe, collective, nexus) get the same
      treatment. The in-controller `access('view')` checks may stay as
      a second line of defence.
- [ ] New kernel test, `RouteEntityAccessTest` (data provider over
      **every** route in all four routing files, discovered from the
      router rather than hand-listed, so a new route can't be missed):
      as a user with the relevant "own" permissions but no
      relationship to the record, `checkNamedRoute()` is denied. As
      the owner, an apiary beekeeper member and `administer hivelog`,
      it is allowed. Place per-submodule cases in each submodule's
      tests.
- [ ] Routes with no entity parameter (collections, dashboard, reports
      across apiaries, the two token-authenticated API endpoints) are
      listed explicitly in the test as exempt, with a reason.
- [ ] [[0020-access-parity-custom-routes]] gets a short addendum saying
      the route test now enforces its rule.
- [ ] phpcs clean; kernel + unit suite green against `cms2`; spot-check
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

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0020-access-parity-custom-routes]],
  [[0019-authorisation-model]]
- Tasks:: [[0124-list-page-row-access-filter]] (ship together),
  [[0137-align-lint-static-analysis-and-test-gates]] (make this test a
  hard gate)
- Commits::
