---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0209-ios-app-demo-server-and-app-store-release
release:
depends-on: ["[[0206-ios-app-queen-calendar-and-alerts]]", "[[0207-ios-app-offline-outbox]]", "[[0208-ios-app-qr-hive-label-scanning]]"]
blocked-by:
---
# Task: iOS app: demo server, TestFlight and App Store submission

## Context
App Store review needs a reachable server and a working account, and a
self-hosted product needs a way to try the app before installing HiveLog.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] Public demo site with `hivelog_api` enabled, seeded with
      `assimilate` (which is development/demo only — so never a real site)
      and a reviewer account; reset on a schedule
- [ ] TestFlight beta with at least one real beekeeper using it in the field
- [ ] Privacy policy and App Store privacy labels (photos, location)
- [ ] Listing owner and open-source status decided (project open question)
- [ ] Submitted and approved

## Implementation notes
- Push notifications are not in this release unless the project's push
  open question has been resolved.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
