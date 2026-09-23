---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Decision recorded here per entity type. Suggested starting point:
      - **SensorDevice → yes**: `add sensor device`, create access also
        requiring update access on the target hive / apiary (scoped add
        routes, as in [[0133-route-level-entity-access]]).
      - **ApiClient, AiProviderConfig → admin-only is right**. They are
        site-level integrations. Remove the misleading `own` permissions
        (keep `any` + admin), or document why they stay.
- [ ] Permissions yml, access handlers and route requirements updated
      to match the decision. An update hook revokes removed permissions
      from roles (`user_role_revoke_permissions()`) so no role config
      references a permission that no longer exists.
- [ ] Kernel tests updated for the new create / own semantics.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0019-authorisation-model]]
- Tasks:: [[0106-sensor-device-management-ui]],
  [[0133-route-level-entity-access]]
- Commits::
