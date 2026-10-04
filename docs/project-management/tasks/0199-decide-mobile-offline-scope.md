---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: docs
created: 2026-10-04
branch: feature/0199-decide-mobile-offline-scope
release:
depends-on:
blocked-by:
---
# Task: Decide the app's offline scope

## Context
[[0107-mobile-client-api-and-native-ios-app]] §4 leaves offline open:
(A) online with an outbox for writes, or (B) offline-first with a local
copy and delta sync. The choice shapes the API (client-generated UUIDs,
`changed` exposure, delete tombstones) so it must be made before
[[0201-hivelog-api-submodule-scaffold]] starts.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] A or B chosen (or a documented middle ground, e.g. A plus a cached
      read-only copy of the last-viewed apiaries)
- [ ] ADR-0107 §4 updated with the decision and ADR status flipped to
      `accepted` if the rest of it stands after
      [[0198-mobile-api-jsonapi-oauth-spike]]
- [ ] API requirements the choice implies listed for 0201/0202

## Implementation notes
- Inputs: how often field writes are edits vs. creates; real signal
  conditions at the out-apiaries; spike findings.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
