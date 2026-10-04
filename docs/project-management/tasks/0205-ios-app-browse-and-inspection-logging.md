---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0205-ios-app-browse-and-inspection-logging
release:
depends-on: ["[[0204-ios-app-setup-and-sign-in]]"]
blocked-by:
---
# Task: iOS app: apiary/hive browsing and inspection logging

## Context
The core field workflow: pick an apiary, pick a hive, log an inspection
with photos.
Part of [[ios-field-app]].

## Acceptance criteria
- [ ] Apiary list and map (apiary `geolocation`), hive list per apiary
- [ ] Hive screen: active queen, last inspection, recent inspections
- [ ] New inspection form covering the inspection payload, with enum
      values loaded from the API rather than hard-coded in Swift
- [ ] Camera and photo-library capture, uploaded to the inspection's
      `images` field
- [ ] Server-side validation errors shown against the right field
- [ ] Large tap targets; usable one-handed with gloves (manual check)

## Implementation notes
- Enum source: if JSON:API doesn't expose allowed values, 0202 may need a
  small schema endpoint.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
