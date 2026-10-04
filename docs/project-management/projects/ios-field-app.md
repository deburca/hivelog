---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: iOS Field App

## Goal
Give beekeepers a native iPhone app for the work they do *at the hive*:
logging inspections with photos, recording queen observations, ticking
off seasonal actions and checking alerts. It should keep working where
signal is poor. Behind it sits a per-user, OAuth-secured API in a new
optional submodule, which any future client can reuse. The design is in
[[0107-mobile-client-api-and-native-ios-app]].

## Scope
- In scope:
  - **Backend (this repo):** a new `hivelog_api` submodule with JSON:API
    resources for the field entities, `simple_oauth` (auth code + PKCE),
    and read-only endpoints for computed views (alerts, stat tiles,
    empty or net weight).
  - **Backend (this repo):** moving form-only validation into entity
    `Constraint` plugins so web and API share one set of rules.
  - **App (separate repo):** SwiftUI app with a server-URL login,
    apiary → hive browsing, inspection logging with camera photos,
    queen observations, calendar action done/ignored, an alerts and
    latest-insight view, QR hive-label scanning, and an offline outbox
    for writes (per the ADR's provisional option A).
  - App Store submission, with a public demo server seeded by
    `assimilate`.
- Out of scope:
  - Inventory, purchases, products, financial reports, calendar
    planning, sensor device and API client management, and AI provider
    configuration (all stay web-only).
  - Full offline-first sync (ADR option B), unless the offline decision
    goes that way.
  - Android, and iPad-specific layouts.
  - Changes to `collective`'s site-level context API.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
Static index (in suggested execution order):
- [[0198-mobile-api-jsonapi-oauth-spike]] — spike (do first)
- [[0199-decide-mobile-offline-scope]] — decision gate
- [[0200-move-form-validation-to-entity-constraints]] — core
- [[0201-hivelog-api-submodule-scaffold]]
- [[0202-hivelog-api-computed-view-endpoints]]
- [[0203-hivelog-api-access-parity-tests]]
- [[0204-ios-app-setup-and-sign-in]] — app repo from here on
- [[0205-ios-app-browse-and-inspection-logging]]
- [[0206-ios-app-queen-calendar-and-alerts]]
- [[0207-ios-app-offline-outbox]]
- [[0208-ios-app-qr-hive-label-scanning]]
- [[0209-ios-app-demo-server-and-app-store-release]]

## Open questions
- Offline: outbox (A) or offline-first (B)? See the ADR's §4.
- Stock JSON:API with a resource allow-list, or a hand-written REST
  layer shaped around the app's screens? Settle in [[0198-mobile-api-jsonapi-oauth-spike]].
- How is the API versioned so a `baseFieldDefinitions()` rename doesn't
  break shipped app versions?
- Push notifications: does the site send APNs directly (needs an Apple
  key per site), or go through a relay service? Self-hosted installs
  make this awkward either way.
- Is the app open source alongside the module, and who owns the App
  Store listing?
- Order relative to [[shared-apiaries-and-team-roles]]: ship the API
  first and adapt, or wait for the access-model change?

## Related decisions
- [[0107-mobile-client-api-and-native-ios-app]]
