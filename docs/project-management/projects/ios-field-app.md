---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: iOS Field App

## Goal
The app's name is **Vinculum** (subtitle "Smarter beekeeping"); HiveLog is the
server it connects to.

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
- [[0210-ios-app-hivelog-look-and-feel]] — added after 0208: the web / beeswax look for the app
- [[0211-ios-app-illustrations]] — a few AI-generated, wax-tinted illustrations for empty and first-run screens
- [[0212-hivelog-api-write-compatibility-with-host-firewalls]] — found testing the live site: a shared host's firewall refused every write
- [[0214-ios-app-illustrations-round-2]] — four more pictures for the remaining empty and failure screens

## Open questions
- ~~Offline: outbox (A) or offline-first (B)?~~ Answered by [[0199-decide-mobile-offline-scope]]: A, with a client-side read cache. See the ADR's §4.
- ~~Stock JSON:API or hand-written REST?~~ Answered by
  [[0198-mobile-api-jsonapi-oauth-spike]]: stock JSON:API with a
  `jsonapi_extras` allow-list, plus a few read-only computed endpoints,
  **conditional on parent-access constraints (0200) before writes are
  enabled**: the spike showed a user can otherwise create in, or move
  records into, another beekeeper's apiary.
- How is the API versioned so a `baseFieldDefinitions()` rename doesn't
  break shipped app versions? ~~Partly answered~~ Answered by
  [[0201-hivelog-api-submodule-scaffold]]: a versioned path prefix
  (`/hivelog/api/v1`) plus a pinned contract fixture that fails CI on a rename
  or removal, until an alias or a new version is added. `jsonapi_extras` was
  not used (its settings are site-wide).
- Push notifications: does the site send APNs directly (needs an Apple
  key per site), or go through a relay service? Self-hosted installs
  make this awkward either way.
- Is the app open source alongside the module, and who owns the App
  Store listing? (The bundle id is decided: `nu.verdigris.vinculum`, listed as Vinculum;
  see [[0209-ios-app-demo-server-and-app-store-release]]. Ownership and open source are not.)
- Order relative to [[shared-apiaries-and-team-roles]]: ship the API
  first and adapt, or wait for the access-model change?

## Related decisions
- [[0107-mobile-client-api-and-native-ios-app]]
