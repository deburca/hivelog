---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0207-ios-app-offline-outbox
release:
depends-on: ["[[0199-decide-mobile-offline-scope]]", "[[0205-ios-app-browse-and-inspection-logging]]"]
blocked-by:
---
# Task: iOS app: offline outbox for writes

## Context
Implements the outcome of [[0199-decide-mobile-offline-scope]]. Assuming
option A: writes made without signal are queued and replayed later.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] Inspections, observations and action reports created offline go into
      a persistent outbox with client-generated UUIDs (photos included)
- [ ] Replay in order on reconnect; a retried create is idempotent (same
      UUID, no duplicate)
- [ ] Failed items (validation or access errors) surfaced for the user to
      fix or discard, never silently dropped
- [ ] Pending count visible; works across app restarts
- [ ] Recently viewed apiaries/hives readable offline (cached)

## Implementation notes
- Rescope this task if 0199 picks option B.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
