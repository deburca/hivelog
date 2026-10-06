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
- [ ] Listing owner and open-source status decided (project open question; the bundle id is
      decided, see Implementation notes)
- [ ] Submitted and approved

## Implementation notes
- Push notifications are not in this release unless the project's push
  open question has been resolved.

### Identity (decided 2026-10-06)
- **Bundle id `nu.verdigris.vinculum`**, an explicit App ID, with the app's name on the
  App Store **Vinculum** (submitted under that name). The app repository uses it from branch
  `feature/0209-bundle-id`: `project.yml` (`PRODUCT_BUNDLE_IDENTIFIER`, `bundleIdPrefix`
  `nu.verdigris`), the URL type name, the Keychain service, and the log and queue names. It was
  the placeholder `org.deburca.hivelog` before.
- The sign-in redirect scheme stays **`hivelog://oauth/callback`**: it belongs to the server's
  OAuth client (`hivelog-ios`), not to the app's identity, so no server change is needed.
- Because the Keychain service changed, a build from before the change does not see its earlier
  session and asks to sign in again (only development installs exist, so nothing is lost).
- **No capabilities are registered on the App ID.** The app needs none today (Keychain, camera and
  photo access, MapKit, VisionKit and the network need no capability). Later candidates: Push
  Notifications (if alerts are pushed, with the open question below), Associated Domains (only if QR
  labels become universal links, which does not fit an app that works with any server), App Groups
  (widgets). **WeatherKit was considered and left out:** weather is planned server-side in
  [[weather-aware-inspection-planning]], so the web and the app show the same forecast, and sending
  apiary coordinates to a third party has to be opt-in per site, which an in-app WeatherKit call
  would bypass. Capabilities can be added to the App ID later.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
