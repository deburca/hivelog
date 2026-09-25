---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0135-submodule-create-permissions
release:
depends-on:
blocked-by:
---
# Task: Make submodule entity permissions consistent with core

## Context
From the 2026-09-23 gap analysis. Every core HiveLog entity type has
the full set `add X` / `view own|any X` / `edit own|any X` /
`delete own|any X`. The three submodule UI entity types don't:

| Entity | `add …` permission | `checkCreateAccess()` | "own" edit / delete |
|---|---|---|---|
| SensorDevice (nanoprobe) | none | `administer hivelog` only | yes |
| ApiClient (collective) | none | `administer hivelog` only | yes |
| AiProviderConfig (nexus) | none | `administer hivelog` only | yes |

So only admins can create these, yet "edit own / delete own"
permissions exist. They mean something only if an admin creates a
record and hands ownership to someone. For sensor devices specifically,
a beekeeper can't add a sensor to their own hive, although the hive
Insights page's Sensors panel offers an "Add Sensor" button to anyone
who passes create access.

**Needs a decision** (hence `backlog`): which of the three should
beekeepers be able to create?

## Acceptance criteria
- [x] Decision recorded here per entity type. Suggested starting point:
      - **SensorDevice → yes**: `add sensor device`, create access also
        requiring update access on the target hive / apiary (scoped add
        routes, as in [[0133-route-level-entity-access]]).
      - **ApiClient, AiProviderConfig → admin-only is right**. They are
        site-level integrations. Remove the misleading `own` permissions
        (keep `any` + admin), or document why they stay.
- [x] Permissions yml, access handlers and route requirements updated
      to match the decision. An update hook revokes removed permissions
      from roles (`user_role_revoke_permissions()`) so no role config
      references a permission that no longer exists.
- [x] Kernel tests updated for the new create / own semantics.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Decision, per entity type** (confirmed via two rounds of user sign-off —
see "Conflict with task 0106" below):
- **SensorDevice → yes.** New `add sensor device` permission
  (`nanoprobe.permissions.yml`). `SensorDeviceAccessControlHandler::checkCreateAccess()`
  now allows `add sensor device` OR `administer hivelog` — the exact same
  shape as `HiveAccessControlHandler::checkCreateAccess()`. The apiary/hive
  scoping this permission alone can't express lives at the route level,
  mirroring how `add hive` itself is scoped: `nanoprobe.sensor_device.add_for_hive`/
  `_apiary` additionally require `_entity_access: hive.update`/`apiary.update`
  on the target entity in the URL, so the bare permission only ever lets a
  beekeeper add hardware to a hive/apiary they can already manage.
  `entity.sensor_device.add_form` (the context-free `/hivelog/sensor-device/add`
  route, no hive/apiary in the URL to scope against) deliberately stays
  `administer hivelog`-only — opening it would let the permission attach a
  device to *any* apiary at all, and mirrors Hive itself having no
  context-free add route.
- **ApiClient, AiProviderConfig → admin-only confirmed.** No `add`
  permission was ever added for either — `checkCreateAccess()` stays
  `administer hivelog` only, unchanged. `edit own`/`delete own` removed
  from both as dead config (see "own permission scoping" below); `view own`
  kept.

**Conflict with task 0106, surfaced and overridden.** Mid-implementation,
`SensorDeviceAccessControlHandler`'s own pre-existing docblock and task
0106's own Implementation notes were found to record a *deliberate* prior
decision to keep SensorDevice create access `administer hivelog`-only,
explicitly "matching `ApiClient`/`AiProviderConfig`'s ... reasoning
exactly." This directly contradicted the direction of this task's own
first decision. Rather than silently proceeding or silently reverting to
0106's call, this was surfaced back to the user quoting 0106's own words;
the user explicitly chose to proceed with opening up SensorDevice create
access anyway. Task 0106 is a superseded decision as of this task, not an
oversight — hardware a beekeeper manages day-to-day on their own hive/apiary
is a materially different case from an API credential or AI provider
integration, which is why the split lands differently than 0106 assumed.

**"Own" permission scoping — narrower than a literal reading of the AC.**
The AC text ("Remove the misleading own permissions") doesn't distinguish
view from edit/delete, but the task's own context paragraph specifically
frames *edit/delete* as meaningless once create is admin-only (an owner is
always already an admin, covered by "any"). `view own api client`/
`view own ai provider config` were kept — a plausible, lower-risk case
survives: an admin can still hand one specific person read-only visibility
onto one credential without granting `view any`'s blanket access to every
other one. Only `edit own`/`delete own api client` and
`edit own`/`delete own ai provider config` were removed, from both the
`.permissions.yml` files and the `_permission` OR-clauses in
`collective.routing.yml`/`nexus.routing.yml` (those route requirement
strings referenced the now-nonexistent permissions directly, not just the
access handlers).

**Update hooks.** `collective_update_10001()` and `nexus_update_10001()`
(each module's first-ever update hook) call
`user_role_revoke_permissions()` for the two removed permissions on every
role — a safe no-op for roles that never had them. Verified live against
`cms2` via `drush updb`.

**Kernel test fallout.** Removing `edit own`/`delete own` broke the
ownership-based-access tests in both `collective` and `nexus` (they
asserted the owner could update/delete via the now-gone "own" permission)
and the `RouteEntityAccessTest` IDOR-style loops in both submodules (they
asserted the owner fixture was allowed on *every* own/any-gated route,
including edit/delete routes that no longer have an "own" path). Each
`RouteEntityAccessTest::testOutsiderDeniedOwnerAndAdminAllowed()` was
rewritten to derive the expected owner outcome generically from each
route's own `_entity_access` operation suffix (`.view` → owner allowed,
`.update`/`.delete` → owner denied, same as an outsider) rather than
hard-coding a route list, plus a new `anyUser` fixture to prove the "any"
path still grants access site-wide. `nanoprobe`'s `RouteEntityAccessTest`
needed a larger rework: its `ADMIN_ONLY_ENTITY_ROUTES` exemption list
excluded `nanoprobe.sensor_device.add_for_hive`/`_apiary` from the
functional outsider/owner loop entirely, on the (now-false) premise that
they were `administer hivelog`-only — removed, and both routes folded into
the generic loop like every other own/any-gated route, with `add sensor
device` granted to both the owner and outsider role fixtures so the loop
actually exercises the `hive.update`/`apiary.update` scoping (an outsider
with the permission but the wrong hive/apiary is still denied). The stale
`testAdminOnlyAddRoutesAdmitAdmin()` (which asserted the owner was denied)
was replaced with `testHiveOwnerWithoutPermissionDenied()` — a genuinely
new case: owning the target hive/apiary is not enough without the `add
sensor device` permission itself.

**Verification.** phpcs (module `phpcs.xml.dist`) and phpstan (module
`phpstan.neon`) clean on every changed file. Full kernel suite for
`collective`, `nexus` and `nanoprobe` against `cms2`: 220 tests / 2878
assertions, all green (only pre-existing, unrelated `key`-module attribute-
discovery deprecation noise). Live-verified on `cms2` via `drush php-eval`
with throwaway fixtures (role/user/apiary/hive/api-client, all deleted
after): a beekeeper with `add sensor device` + `edit own hive`/`apiary`
can reach both scoped add routes but not the context-free one; an
`ApiClient` owner with only `view own api client` can view but not
update/delete their own client.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0019-authorisation-model]]
- Tasks:: [[0106-sensor-device-management-ui]],
  [[0133-route-level-entity-access]]
- Commits::
