---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: api
created: 2026-10-30
branch: feature/0247-discovery-page-prefix-and-roles
release: 4.1.0
depends-on: ["[[0246-path-prefix-setting-and-api-at-api-v1]]"]
blocked-by:
---
# Task: Tell the app the page prefix and the user's role in each apiary

## Context
The Vinculum app reads QR labels (`/<prefix>/hive/<id>/quick`) and should show only what its user may do. Since 3.0 (apiary roles) and 4.0 (`hivelog_path_prefix`) it can know neither without guessing. Both are the server's to compute. Additive, so `api_version` stays 1. Vinculum tasks 0040 and 0041 and ADR-0025 are the client side.

## Acceptance criteria
- [x] The discovery document's `meta.hivelog_api` carries `page_prefix` (`HivelogPaths::prefix()`)
- [x] `GET /api/v1/computed/apiary-roles` (signed in): `data` is one row per apiary the user can see, `{apiary: {id, name}, role}` with `role` `owner`, `keeper` or `novice`, or null for access by a site permission without membership; `meta.count`
- [x] Kernel tests: `HivelogApiRoutingTest` (prefix), `HivelogApiComputedViewsTest::testApiaryRolesAreTheUsersOwn`
- [x] CI green
- [x] Release note 4.1.0
- [ ] Capture both responses for Vinculum's fixtures (from a 4.1 server)

## Related
- Amends:: [[0246-path-prefix-setting-and-api-at-api-v1]]
