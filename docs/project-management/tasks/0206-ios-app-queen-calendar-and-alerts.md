---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0206-ios-app-queen-calendar-and-alerts
release:
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]", "[[0202-hivelog-api-computed-view-endpoints]]"]
blocked-by:
---
# Task: iOS app: queen observations, calendar actions and alerts

## Context
The remaining in-scope field actions.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] Add a queen observation from the hive screen (only when there is an
      active queen, matching the web hive page)
- [ ] Calendar actions due this week for an apiary; report done/ignored as a
      hive or apiary action log
- [ ] Alerts screen from the needs-attention endpoint
- [ ] Hive stat tiles and the latest insight on the hive screen when
      available

## Implementation notes
- Inventory usage/harvest yield capture on "done" is out of scope; the web
  form still handles it.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
