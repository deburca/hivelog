---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ios-field-app]]"
area: integration
created: 2026-10-04
branch: feature/0201-hivelog-api-submodule-scaffold
release: 2.7.0
depends-on: ["[[0198-mobile-api-jsonapi-oauth-spike]]", "[[0199-decide-mobile-offline-scope]]", "[[0200-move-form-validation-to-entity-constraints]]"]
blocked-by:
---
# Task: Scaffold the hivelog_api submodule

## Context
[[0107-mobile-client-api-and-native-ios-app]] §1: a fifth optional
submodule exposing a per-user API. Core must not depend on it (ADR-0098).
Part of [[ios-field-app]].

## Acceptance criteria
- [x] `modules/hivelog_api/` (final name confirmed) depending on
      `hivelog`, `jsonapi`, `serialization`, `simple_oauth`
- [x] `simple_oauth` added to `composer.json` as a `suggest` (core does not
      need it, same as `key` for nexus); CI scaffold installs it
- [x] Resource allow-list: only the field-workflow types and fields
      (apiary, hive, inspection, queen, queen observation, calendar action,
      hive/apiary action log, file) are exposed; everything else disabled
- [x] Install creates (or documents creating) the public PKCE OAuth client
      and a redirect URI for the app's custom URL scheme
- [x] Writes accept client-generated UUIDs, per the offline decision
- [x] API versioning approach from the project's open question decided and
      implemented (path prefix or resource-name aliases)
- [x] CI discovery picks up the new module's phpcs/phpstan/Kernel dirs
      without workflow changes (verify, don't assume)
- [x] AGENTS.md: new submodule in "Submodules", the count in "Project
      Overview", CI paragraph if anything changed
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes

### What was built
`modules/hivelog_api/` (depends on `hivelog`, `file`, core `jsonapi`,
`serialization`, `simple_oauth`; no entity types; version bumped with the others
on release):
- **`HivelogApiPathProcessor`** serves core JSON:API under the versioned prefix
  `/hivelog/api/v1`: inbound it maps `/hivelog/api/v1/<type>/<bundle>/…` onto
  `/jsonapi/…` for the allow-list only (anything else finds no route: 404);
  outbound it writes JSON:API links for allow-listed resources back with the
  prefix on a request that used it, and adds the `url.path` cache context.
- **Allow-list** (`HivelogApiResources::RESOURCES`): apiary, hive, hive
  inspection, queen, queen observation, calendar action, hive and apiary action
  logs, file.
- **`HivelogApiMethodFilter`** decorates `method_filter` so writes pass on the
  prefix even when JSON:API's site-wide read-only mode is on. Enabling the module
  therefore **changes no site-wide setting**; plain `/jsonapi` keeps whatever the
  site chose.
- **`HivelogApiTokenConfinementSubscriber`** refuses a token carrying the app's
  scope on any JSON:API route off the prefix.
- **Discovery** `GET /hivelog/api/v1` (public): `api_version`, module version,
  site name, OAuth client id/scope/endpoints, and links to each resource.
- **Install** (idempotent): role `hivelog_field_app`, a scope of the same name
  (granularity `role`), and the public PKCE client `hivelog-ios` (redirect
  `hivelog://oauth/callback`, authorization-code + refresh). Uninstall removes
  them. `hook_requirements` errors until simple_oauth has a readable key pair,
  and warns if any other scope enables the authorization-code grant.

### Decisions (and how they differ from the task text)
- **No `jsonapi_extras`.** The spike suggested it for the allow-list. Its
  `default_disabled` and `path_prefix` are site-wide settings, so a submodule
  flipping them would change JSON:API for everything else on the site. The path
  processor gives a per-API allow-list and prefix with no site-wide side effects
  and no extra dependency. Trade-off: the allow-list is code, not UI config.
- **Versioning = path prefix + a pinned contract.** `/hivelog/api/v1` is the
  prefix (a v2 would add `/v2` beside it). Stability is enforced by
  `tests/fixtures/api-v1-contract.json`, every exposed resource and field name:
  a rename or removal fails `HivelogApiContractTest` until it is given an alias or
  a new version; an addition just needs the fixture regenerated. The app also
  reads `api_version` from the discovery document at sign-in.
- **Allow-list covers types, not fields.** Which fields a user sees is decided by
  the entity's field access, and the contract pins the set. Nothing hides fields
  at the API (e.g. `apiary` exposes `beekeepers` and `ai_insights_enabled`); if
  the app should not see some, that is a deliberate follow-up.
- **Role instead of a hand-made scope per site.** A token's roles are the
  scope's roles intersected with the user's own, so it can only narrow. A shipped
  role `hivelog_field_app` that a site gives to the beekeepers who may use the
  app is both the access switch and a ceiling: even an admin's app token has only
  the app's permissions. It has no `delete` permission (0199: the app deletes
  nothing) and no inventory, product or sensor permission.
- **Known over-grant.** Adding a child needs `update` on its parent (task 0133,
  and the 0200 parent-access constraint), so the role must hold `edit own
  apiary|hive|queen`, which also lets the app PATCH those records. Narrowing it
  needs a separate "add children" permission in core. Documented in the install
  file; for 0203.

### Findings
- **Router caching defeats a path processor with side effects.** The router
  caches an inbound path's processed result, so a processor only runs on the first
  request for a path. My first version flagged the request in the processor; the
  second POST to the same path then hit read-only mode. "Arrived on the prefix" is
  now read from the request path.
- **PKCE is enforced (resolves the spike's open item).** An authorize request with
  no `code_challenge` is refused (no code, no consent screen), and a wrong
  verifier is a 400.
- **simple_oauth does not limit the authorization-code grant to a consumer's
  scopes.** A public client may request *any* scope with that grant enabled. The
  app's client is public, so any broad scope with the grant enabled would be
  reachable by it; the status report warns, and the README says to disable it.
- **A token could enumerate users** at `/jsonapi/user/user` (display names are
  visible to any signed-in user). Caught by the OAuth functional test; closed by
  the confinement subscriber.
- **Role permissions depend on `node`** only for `access content`, which the
  role gets when node exists and silently omits otherwise (a photo attach needs
  it).
- Kernel tests use Basic Auth as the stand-in for a bearer token (the token
  exchange is simple_oauth's own and is covered end to end by the functional test).

### Not done / for later tasks
- Expired-token behaviour is only exercised with an invalid token (401); 0203.
- No rate limiting, no per-user token revocation UI beyond simple_oauth's own.
- The `hivelog_api_requirements` hook uses the legacy form with
  `#[LegacyRequirementsHook]` for Drupal < 11.3 compatibility.

### Verification
New module: 26 kernel + unit tests (routing, allow-list, read/write, client UUID
+ 409 retry, 422 with the field pointer, foreign create/re-parent refused, app
role cannot delete, install/uninstall/idempotence, contract, requirements, token
confinement) and 1 functional test of the real flow (installed client, consent,
PKCE, token, reads, writes, photo upload, 404 outside the allow-list, no user
enumeration, refresh). phpcs and phpstan clean; no baseline change.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
