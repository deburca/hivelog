---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `jsonapi` + `simple_oauth` enabled on cms2 with an auth-code +
      PKCE public client; a token obtained for a non-admin beekeeper
- [ ] Read: a beekeeper with only `view own` sees their own apiaries/hives
      and gets 403/absent for someone else's (collection *and* individual)
- [ ] Write: create an inspection, a queen (check one-active-queen demotion
      and colour derivation still fire), and a queen observation
- [ ] Invalid write: a cross-apiary `HiveComponent` — record the actual
      HTTP status and body (expected: 500 from `preSave()`)
- [ ] Delete: an apiary with BLOCK children is refused; a CASCADE delete
      removes children (`hivelog.delete_dependency_executor` via the
      entity hook)
- [ ] Image upload onto an inspection's `images` field via JSON:API's file
      upload resource
- [ ] Findings and a JSON:API vs. custom REST recommendation written up
      here and answered in the ADR's/project's open questions

## Implementation notes
- Don't commit `simple_oauth` to `composer.json` in this task.
- Note any field that serialises badly (geofield WKT, allowed-values enums,
  computed fields).

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
