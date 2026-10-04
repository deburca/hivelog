---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: tests
created: 2026-10-04
branch: feature/0203-hivelog-api-access-parity-tests
release:
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
- [ ] Kernel tests in the submodule's own `tests/src/Kernel`: for each
      exposed type, own/any/admin read, create, update, delete outcomes match
      `RouteEntityAccessTest`'s matrix
- [ ] Collections never leak another user's rows
- [ ] Constraint violations from [[0200-move-form-validation-to-entity-constraints]]
      return 422 with the field path, not 500
- [ ] Non-exposed entity types (inventory, products, API clients, AI
      provider configs) return 404 from the API
- [ ] Unauthenticated and expired-token requests get 401
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
