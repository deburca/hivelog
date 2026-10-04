---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ios-field-app]]"
area: tests
created: 2026-10-04
branch: feature/0203-hivelog-api-access-parity-tests
release: 2.8.1
depends-on: ["[[0201-hivelog-api-submodule-scaffold]]"]
blocked-by:
---
# Task: API access-parity and validation tests

## Context
The API must not grant anything the web UI doesn't. AGENTS.md's CI notes
already keep access-critical assertions in hard-gate kernel tests
(`RouteEntityAccessTest`) rather than advisory functional ones; the API
needs the same treatment.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] Kernel tests in the submodule's own `tests/src/Kernel`: for each
      exposed type, own/any/admin read, create, update, delete outcomes match
      `RouteEntityAccessTest`'s matrix
- [x] Collections never leak another user's rows
- [x] Constraint violations from [[0200-move-form-validation-to-entity-constraints]]
      return 422 with the field path, not 500
- [x] Non-exposed entity types (inventory, products, API clients, AI
      provider configs) return 404 from the API
- [x] Unauthenticated and expired-token requests get 401
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes

### What the tests do
- **`HivelogApiAccessParityTest`** (kernel, so a hard CI gate). The oracle is the
  web's own route access, `access_manager->checkNamedRoute()` on the canonical,
  edit, delete and scoped-add routes, not a second hand-written table: if the web
  would let a user do something, the API must; if not, it must not. Five actors:
  the owner, an unrelated beekeeper holding the same permissions (the IDOR case),
  a beekeeper member of the owner's apiary, a `view any|edit any|delete any` user,
  and an administrator. For all eight exposed hivelog types and two record trees
  (the actor's own and someone else's) it checks read, update, create and delete.
  A refusal is a 403 (or a 422 from the parent-access constraint on a create) and
  never a 5xx. Delete uses a fresh leaf record each time; records with BLOCK
  children (an apiary with hives, a hive with inspections) are checked
  separately: not deletable even by the owner or an admin, as on the web
  (ADR-0103). The member test pins the owner-only limits (cannot rename the apiary,
  cannot add a hive or a calendar action).
- **The field-app role's ceiling** is its own test: it creates inspections,
  observations and both action logs, cannot create an apiary, hive, queen or
  calendar action, and deletes nothing.
- **`HivelogApiValidationTest`**: for every type a missing required field, for the
  enums a bad value, and each 0200 rule through the API (feed type, varroa count,
  week range, an action from another apiary) are a 422 whose pointer names the
  field. Never a 500.
- **`HivelogApiNonExposedTypesTest`** enables every optional submodule so the
  entity types exist, shows plain JSON:API serves each to an administrator, and
  that the versioned API answers 404 for the same administrator: the allow-list
  hides them, not a missing route.
- **No credentials / expired token = 401**: a kernel test for every type and verb,
  a unit test for the subscriber, and in the OAuth functional test a real token
  with a one-second lifetime that is a 401 after it lapses.

### Defects the tests found (all fixed here)
1. **JSON:API paginates before it filters by access.** These types have no
   query-level access, so a collection's first page was the first 50 records on
   the site, whoever owned them. The calendar action collection (apiary creation
   seeds 31 per apiary) came back **empty for its owner** with 50 records
   "omitted"; with enough data that happens to apiaries, hives and inspections
   too, and the app would page through empty pages. `HivelogApiQueryAccess` now
   narrows an access-checked query to the ids the type's own `view` check allows
   (computed the way the web lists compute theirs), only on a versioned request,
   so the page is full and the web and API cannot disagree.
2. **`meta.omitted` named other people's records.** The same response listed the
   ids and URLs of every record it dropped. With (1) fixed nothing is dropped, and
   the test asserts there is no `meta` at all.
3. **The file collection** (`/file/file`) would list every file on the site; the
   app only fetches by id or uploads, so it is no longer routed (404).
4. **No credentials was a 403 or an empty 200, not a 401**; only a bad token was a
   401. A client cannot tell "sign in" from "not allowed". The versioned API now
   answers an unauthenticated request with a 401 and a Bearer challenge.
5. **A recursion I introduced and then caught:** the first version of (4) threw a
   401, which Drupal renders as an HTML page through a *sub-request that inherits
   the original path*, so the subscriber fired on it too and recursed until memory
   ran out (found by the functional test, not the kernel tests). It now answers
   directly as JSON and ignores sub-requests.

### Facts recorded
- simple_oauth 6 sets the access-token lifetime **per client** (default 300 s) and
  the **refresh-token lifetime per client (default 14 days, 1,209,600 s)**: how long
  the app can stay offline before the user must sign in again (task 0199 asked for
  this to be read and recorded).
- A token's roles are the scope's intersected with the user's, so the field-app
  role is a ceiling even for an admin.

### Not done
- The `view any` actor is covered for the eight hivelog types only; `file`
  access is not part of the matrix (an upload is exercised in the OAuth
  functional test).
- Query narrowing computes the viewable ids with a PHP access check per record
  (as the web lists do), so a collection costs O(records of that type). Fine at
  beekeeping scale; if a site ever has hundreds of thousands, the filter needs a
  SQL form.
- Rate limiting, and a per-IP lockout on repeated failures, are not in scope.

### Verification
59 tests in `hivelog_api` (kernel, unit and the OAuth functional test), all green;
the access-parity matrix alone makes about 1,000 assertions. phpcs and phpstan
clean. **Full suite: 1,283 tests / 22,765 assertions, 0 failures, 0 errors**
(1,259 before + 24 new), run as twelve chunks covering every test file once.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
