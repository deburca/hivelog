---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0208-ios-app-qr-hive-label-scanning
release:
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]", "[[0177-hive-quick-access-page]]"]
blocked-by:
---
# Task: iOS app: scan hive QR labels

## Context
[[qr-hive-labels]] points labels at the web quick-access page
(`/hivelog/hive/{hive}/quick`, [[0177-hive-quick-access-page]]). The app
should open the same label straight to its own hive screen.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] In-app scanner (VisionKit `DataScannerViewController`) recognises a
      label URL for the connected server and opens that hive
- [ ] A label from a different server, or a hive the user can't access,
      gives a clear message
- [ ] Optional: universal links so the system camera opens the app — needs
      an `apple-app-site-association` file per site; record whether it's
      worth it for self-hosted installs
- [ ] Label URL format unchanged (web scanning keeps working)

## Implementation notes
- 

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
