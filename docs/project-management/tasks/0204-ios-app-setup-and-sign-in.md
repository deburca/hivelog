---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0204-ios-app-setup-and-sign-in
release:
depends-on: ["[[0201-hivelog-api-submodule-scaffold]]"]
blocked-by:
---
# Task: iOS app: project setup, server connect and sign-in

## Context
First app task. The app lives in its own repository
([[0107-mobile-client-api-and-native-ios-app]] §3); this note tracks it
from the HiveLog vault so the project's Dataview table stays complete.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] New repo created (local, not yet on GitHub); SwiftUI app, minimum iOS version decided and noted
- [x] "Connect to your HiveLog server" screen: URL entry, discovery check
      that the site has the API submodule enabled, clear error otherwise
- [x] OAuth auth-code + PKCE sign-in (`ASWebAuthenticationSession`), tokens
      in the Keychain, refresh handled, sign-out revokes
- [x] Multiple servers or a single one — decided and noted
- [ ] CI for the app repo (build + unit tests): written, but not yet run anywhere (see below)

## Implementation notes

### Name and App Store text (decided afterwards)
The app is **Vinculum**; HiveLog is the server it talks to. Subtitle "Smarter
beekeeping"; promotional text "Your colonies, connected. Log inspections, track hive
health, and let the data guide your beekeeping". They are in the app repo's
`AppStore/en-GB/` (name, subtitle, promotional text, one file each) with a test that
holds them to Apple's limits (30, 30 and 170 characters; the longest is 101). The
Xcode project, target, scheme, display name and the visible strings are now Vinculum;
the Swift libraries keep the `HiveLog` name because they are the client for the HiveLog
API. On the server, the OAuth client's label and the scope wording now say Vinculum,
so the consent screen reads "Vinculum" (`hivelog_api_update_10001()` renames the old
default on existing sites). Two things to check outside the code: that **"Vinculum" is
free as an App Store name** (names are unique; App Store Connect will say), and that
the **locale folder `en-GB`** is the one you want (the text has no spelling that differs
from en-US).

### Where it lives
A new repository, **`~/Development/vinculum`** (renamed from `hivelog-ios` once the app was named; local only: `git init`, files
staged, nothing committed and no GitHub remote). Creating the GitHub repository is
yours to do, because whether the app is open source alongside the module and who
owns the App Store listing are still open questions in the project.

### What was built
- **`HiveLogKit`** (no UI): `ServerURL` (what people type becomes one address; https
  only, plain http just for local development hosts), `DiscoveryClient`, `PKCE`,
  `OAuthClient` (authorisation URL, code exchange, refresh, sign-out),
  `TokenStore` (Keychain, plus an in-memory one), `AuthSession` (an actor: hands out
  a valid token, one shared refresh, retry once on 401, sign-out), `SignIn`
  (the whole code + PKCE flow behind a `WebAuthenticator` protocol).
- **`HiveLogUI`**: `AppModel` (`@Observable`; three phases: needs a server, ready to
  sign in, signed in), the SwiftUI screens, plain-language error messages, and
  `ASWebAuthenticator` over `ASWebAuthenticationSession`.
- **`App/`**: the `@main` shell; **`project.yml`** (XcodeGen) generates the Xcode
  project, with the `hivelog` URL scheme and local-network ATS exception in the
  Info.plist.
- **Backend (this repo)**: `POST /hivelog/api/v1/sign-out`, because the acceptance
  criterion "sign-out revokes" cannot be met without it: simple_oauth 6 has no
  revocation endpoint (see AGENTS.md).

### Decisions (also in the app README)
- **Minimum iOS 17.** `@Observable`, and SwiftData for the offline cache and outbox
  that come later.
- **One server at a time**, no account list. The session type and Keychain item are
  single-server; connecting elsewhere means signing out first.
- **Hand-written models, not a generated client**: the JSON:API has no OpenAPI
  description, the server pins its field names with a contract test, and the app
  needs about ten small types.
- Tokens in the Keychain (`AfterFirstUnlockThisDeviceOnly`), single-flight refresh
  because the server rotates refresh tokens, being offline never signs anyone out.
- Discovery is distrusted: its OAuth addresses must be on the host the person typed,
  and their scheme is forced to the one they entered (a server behind a proxy
  reports `http` links for an `https` site). The real server did exactly that in
  the captured fixture.

### Verification, and what is not verified
- **67 tests pass** with `swift test` (Swift 6.4, strict concurrency, Swift Testing):
  server addresses, the RFC 7636 PKCE vector, discovery in every failure mode, the
  OAuth exchange and its errors, the sign-in flow (wrong state, Deny, cancel, a
  refused exchange), the session (single-flight refresh with 12 concurrent callers,
  offline keeps the session, a revoked refresh token ends it, 401 retry, sign-out
  online and offline), the Keychain round trip (really run, not skipped), and the
  app model's phases. **The decoders are checked against responses captured from the
  real server** (its own functional test), not only hand-written ones.
- **Backend: the real flow ends to end.** The `hivelog_api` functional test now signs
  out with a real token pair: the access token is 401 afterwards, the refresh token
  is `invalid_grant`, and another user's session is untouched. 59 module tests,
  phpcs and phpstan clean.
- **Not verified: the iOS app itself.** This Mac has only the Command Line Tools:
  no Xcode, no iOS SDK, no simulator. So the `@main` shell type-checks (against the
  macOS SDK) but has **not been built for iOS, run in a simulator or on a phone**.
  Unverified as a result: the generated Xcode project from `project.yml`, the
  Info.plist URL-scheme registration, the sheet presentation anchor on iOS, and the
  two CI jobs (`swift test` on macOS, and an iOS-simulator build) which have never
  run. The sign-in sheet against a real server from a real device is the first thing
  to try once Xcode is installed.

### Follow-ups
- Install Xcode, run `xcodegen generate`, build and try the sign-in against a dev
  site (the `.ddev.site` host is allowed over http).
- Decide the GitHub home for the repo and the bundle id (`org.deburca.hivelog` is a
  placeholder) before 0209.
- Backend release: the sign-out endpoint is new API surface, so a minor bump
  (2.9.0) when you next release.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
