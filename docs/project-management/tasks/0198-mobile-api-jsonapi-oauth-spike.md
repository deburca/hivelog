---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ios-field-app]]"
area: integration
created: 2026-10-04
branch: feature/0198-mobile-api-jsonapi-oauth-spike
release:
depends-on:
blocked-by:
---
# Task: Spike: JSON:API + simple_oauth against HiveLog's access and invariants

## Context
First task of [[ios-field-app]]. Before committing to stock JSON:API
([[0107-mobile-client-api-and-native-ios-app]] §1) rather than a
hand-written REST layer, confirm on cms2 that the generic API
behaves correctly against HiveLog's own access and data rules.
Throwaway: findings go into this note and the ADR, not into a merged
branch.

## Acceptance criteria
- [x] `jsonapi` + `simple_oauth` enabled on cms2 with an auth-code +
      PKCE public client; a token obtained for a non-admin beekeeper
      (proven in a throwaway sandbox site booted from cms2's codebase, not
      enabled on the real `vdg` site; see "How it was run")
- [x] Read: a beekeeper with only `view own` sees their own apiaries/hives
      and gets 403/absent for someone else's (collection *and* individual)
- [x] Write: create an inspection, a queen (check one-active-queen demotion
      and colour derivation still fire), and a queen observation
- [x] Invalid write: a cross-apiary `HiveComponent` — record the actual
      HTTP status and body (expected: 500 from `preSave()`; confirmed)
- [x] Delete: an apiary with BLOCK children is refused; a CASCADE delete
      removes children (`hivelog.delete_dependency_executor` via the
      entity hook)
- [x] Image upload onto an inspection's `images` field via JSON:API's file
      upload resource
- [x] Findings and a JSON:API vs. custom REST recommendation written up
      here and answered in the ADR's/project's open questions

## Implementation notes
- `simple_oauth` was **not** added to this repo's `composer.json`. It was
  installed in cms2 only for the spike, and that change is reverted.
- Throwaway harnesses (`SpikeMobileApiTest`, `SpikeOauthTest`,
  `SpikeConstraintTest`, a `spike_scope` test module) were not committed.
  Everything below is from their recorded output.

### How it was run
Each test is a `BrowserTestBase` booting a fresh site with hivelog installed,
two ordinary beekeepers (A and B, each holding every `view own` / `add` /
`edit own` / `delete own` hivelog permission, no admin) and JSON:API
switched to read-write. Part 1 used Basic Auth as a stand-in for the
identity; Part 2 repeated the reads and writes with real OAuth bearer tokens
(`simple_oauth` 6.1.1). Deviation from the criteria: this was a sandbox site
on cms2's code, not a configured client on the live `vdg` site.

### Headline: stock JSON:API is NOT safe to write through today
**Create and re-parent access is not checked against the target parent.**
Hive_create access in HiveLog is a global permission and cannot see field
values; the web UI is safe only because the scoped add routes check the
parent (task 0133). JSON:API has no such route.

- A created records in **B's** space (HTTP 201, rows persisted) for
  inspection, queen, queen observation, hive, hive component, inventory
  item, inventory purchase, calendar action, hive action log and apiary
  action log. Product failed only on a missing required field.
- A re-parented their own records onto B's through the relationship
  endpoint (`PATCH .../relationships/hive`, `.../relationships/apiary`):
  204 and the row actually moved.
- The same 201 happens with an OAuth bearer token, so this is not an
  artefact of the stand-in auth.
- Editing or deleting B's *existing* records is correctly refused (403).

**The fix works, and is cheap.** A field constraint on each parent
reference ("current user may view the referenced parent"), added to
inspection/queen/hive/etc., turned every one of those into
`422 "You do not have access to the parent record."` while legitimate
writes (create on own hive, create hive in own apiary, unrelated PATCH)
still returned 201/200 and nothing moved. Validated in
`SpikeConstraintTest`. This confirms ADR-0107 §2 is not only about
friendly messages: **parent-access constraints are a security requirement,
not polish.**

### Read parity (holds)
- Collections omit other users' records (`meta.omitted`), an individual
  fetch of another user's apiary or hive is 403, a filter on another
  user's apiary leaks nothing, and an anonymous collection is 200 with 0
  rows.
- Same results under OAuth bearer tokens: the collection shows only the
  token owner's apiary.
- Leak to know about: the `user` collection lists every display name to any
  authenticated user (enumeration). The allow-list below removes it.

### Invariants through the API
- One active queen per hive: creating a second demotes the first to
  `inactive` and keeps her hive link. Queen colour is derived from the year
  (2024 green, 2025 blue). Both fire.
- Field validation returns **422** (null name, bad enum value).
- `preSave()` guards return **500** with the message in the `detail`
  member, e.g. cross-apiary component: "A hive component's item must belong
  to the same apiary as its hive." and over-stock: "Cannot assign 5 of ...
  only 2 available." A 500 is wrong for a client error (the app would treat
  it as retryable). They need constraints so they become 422 (task 0200).
- Delete: an apiary with hives is 403 with the friendly count message
  (BLOCK honoured); a hive with only components is 204 and its components
  are removed (CASCADE works); a hive with inspections is 403; another
  user's hive is 403.

### Sync primitives (matter for the offline outbox, task 0199/0207)
- A client-supplied UUID on POST is accepted (the id matches). Retrying
  the same UUID returns **409 Conflict** with no duplicate row, so the
  outbox must treat 409 on a create as "already applied".
- `changed` cannot be PATCHed (403): there is **no optimistic locking**.
  Edits are last-write-wins. A PATCH without `changed` is 200.

### Image upload
- Direct upload onto an inspection's `images` field works (200, image
  attached) for a user with `access content`.
- The orphan upload (201) can then be referenced in an inspection create
  (201).
- Without `access content` the attach fails 422 ("no access to the
  referenced entity (file)") even though the orphan upload returned 201.
  The beekeeper role therefore needs `access content`.

### OAuth (simple_oauth 6.1.1)
- Auth code + PKCE with a public client (no secret) works end to end: the
  consent page (Allow/Deny), redirect to the custom scheme
  `hivelog://oauth/callback` carrying `code` and `state`, token exchange
  (200, Bearer, `expires_in` 300, refresh token). A wrong PKCE verifier is
  400 `invalid_grant`; replaying a code is 400 `invalid_grant`.
- Refresh works and **rotates**: the old refresh token is then 400
  `invalid_grant`.
- `/oauth/userinfo` returns the identity. A garbage token is 401.
- **Scopes map to roles** (`Oauth2Scope` config entities, granularity
  `role`). A token with a read-only-role scope could read its own apiary
  and got 403 on create: least privilege works.
- Revocation works: deleting the user's access-token records made the
  bearer call 401.
- Requirements found: scopes must exist as config entities with the
  grant types enabled (otherwise `invalid_scope`); the user needs
  `grant simple_oauth codes` to consent; keys come from the
  `simple_oauth.key.generator` service.
- **Not settled:** an authorize request with no `code_challenge` recorded
  neither a code nor an error in the harness, so I cannot claim a public
  client with `pkce` on rejects it. 0201 must test this explicitly.

### Serialisation
- Geofield reads as an object (WKT `value`, `geo_type`, `lat`, `lon`,
  bounds, `geohash`, `latlon`). **Write with `{"value": "POINT (x y)"}`**:
  `{lat, lon}` returns 201 but stores null (a silent data loss; the app must
  send WKT).
- Decimals serialise as floats (31.5), dates as ISO-8601 strings, enums as
  raw keys (the app needs its own label map), booleans as real booleans.
- `drupal_internal__id` is exposed. Computed values (empty/net weight,
  stock on hand, stat tiles) are absent by design; they are the 0202
  endpoints.

### Surface area
- 33 resource types are exposed by default, including config entities
  (access-protected, but surface the app should not have).
- `jsonapi_extras` (already on cms2) can disable a resource (404), alias
  or disable fields, and has `path_prefix` and `default_disabled`
  (allow-list) settings. `default_disabled` was identified but not exercised.

## Recommendation
**Use stock JSON:API with an allow-list, plus a few hand-written read-only
endpoints; do not build a custom REST layer.** Reasons: reads, owner-scoped
edits/deletes, field validation, the one-active-queen and colour
invariants, BLOCK/CASCADE deletes, image upload, client UUIDs and OAuth
scoping all behave correctly with no code. The one real gap (parent access
on create/re-parent) is closed by entity constraints we want anyway, and
it is the same fix whichever transport is used, so a custom REST layer
would not avoid it. Conditions:
1. **Do not enable writes until the parent-access constraints exist.**
   Until then JSON:API must stay `read_only`.
2. Allow-list only the resource types and fields the app needs
   (`jsonapi_extras` `default_disabled`), which also removes the user
   enumeration and config entities.
3. Convert the `preSave()` throws that guard client-fixable states into
   constraints so they return 422, not 500.
4. Scope tokens to purpose-built roles via `Oauth2Scope`.

## Follow-on effects (not done here)
- [[0200-move-form-validation-to-entity-constraints]] must include the
  **parent-viewable constraints** on every parent reference (list in
  "Headline"), not only the friendly-message rules.
- [[0201-hivelog-api-submodule-scaffold]]: allow-list, scopes to roles,
  `access content` and `grant simple_oauth codes` on the app role, test the
  missing-`code_challenge` case, `path_prefix`.
- [[0203-hivelog-api-access-parity-tests]] is a **security gate**; add the
  cross-user create and re-parent matrix from this spike.
- [[0199-decide-mobile-offline-scope]]: 409-on-retry makes the outbox
  idempotent; no optimistic locking means last-write-wins.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
