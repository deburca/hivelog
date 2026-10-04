---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: integration
created: 2026-10-04
branch: feature/0201-hivelog-api-submodule-scaffold
release:
depends-on: ["[[0198-mobile-api-jsonapi-oauth-spike]]", "[[0199-decide-mobile-offline-scope]]", "[[0200-move-form-validation-to-entity-constraints]]"]
blocked-by:
---
# Task: Scaffold the hivelog_api submodule

## Context
[[0107-mobile-client-api-and-native-ios-app]] §1: a fifth optional
submodule exposing a per-user API. Core must not depend on it (ADR-0098).
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] `modules/hivelog_api/` (final name confirmed) depending on
      `hivelog`, `jsonapi`, `serialization`, `simple_oauth`
- [ ] `simple_oauth` added to `composer.json` `require` (or `suggest` —
      decide, since core doesn't need it); CI scaffold installs it
- [ ] Resource allow-list: only the field-workflow types and fields
      (apiary, hive, inspection, queen, queen observation, calendar action,
      hive/apiary action log, file) are exposed; everything else disabled
- [ ] Install creates (or documents creating) the public PKCE OAuth client
      and a redirect URI for the app's custom URL scheme
- [ ] Writes accept client-generated UUIDs, per the offline decision
- [ ] API versioning approach from the project's open question decided and
      implemented (path prefix or resource-name aliases)
- [ ] CI discovery picks up the new module's phpcs/phpstan/Kernel dirs
      without workflow changes (verify, don't assume)
- [ ] AGENTS.md: new submodule in "Submodules", the count in "Project
      Overview", CI paragraph if anything changed
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Patch-path constraint in AGENTS.md doesn't apply unless a patch is added.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
