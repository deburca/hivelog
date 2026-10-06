---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0204-ios-app-setup-and-sign-in
release: 2.9.0
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
- [x] New repo created (private, github.com/deburca/vinculum); SwiftUI app, minimum iOS version decided and noted
- [x] "Connect to your HiveLog server" screen: URL entry, discovery check
      that the site has the API submodule enabled, clear error otherwise
- [x] OAuth auth-code + PKCE sign-in (`ASWebAuthenticationSession`), tokens
      in the Keychain, refresh handled, sign-out revokes
- [x] Multiple servers or a single one — decided and noted
- [x] CI for the app repo (build + unit tests): both jobs passed on GitHub Actions' first run

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
A new repository, **`~/Development/vinculum`** (renamed from `hivelog-ios` once the app
was named), pushed to the **private** GitHub repository `deburca/vinculum`. Whether the app
is open source alongside the module, and who owns the App Store listing, are still open
questions in the project.

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
- **68 tests pass** with `swift test` on the Mac (Swift 6.4, strict concurrency, Swift
  Testing), and the same suite passes **on an iOS 27 simulator**
  (`xcodebuild test -scheme HiveLog-Package`; the Keychain suite skips itself there
  because a bare test host has no entitlements). They cover server addresses, the RFC 7636
  PKCE vector, discovery in every failure mode, the OAuth exchange and errors, the sign-in
  flow (wrong state, Deny, cancel, a refused exchange), the session (single-flight refresh
  with 12 concurrent callers, offline keeps the session, a revoked refresh token ends it,
  401 retry, sign-out online and offline), the Keychain round trip, the app model, the
  App Store text limits, and the decoders against **responses captured from the real
  server**.
- **The app itself was built and run** (Xcode 27, iPhone 18 Pro simulator, iOS 27) once
  Xcode was installed: it builds, shows the Connect screen with the Vinculum title and the
  URL keyboard, reports an unreachable server in plain words, finds a server, flags an
  unencrypted dev connection, shows the real system prompt and `ASWebAuthenticationSession`
  sheet, follows the `hivelog://` redirect back, completes the PKCE exchange, calls the API
  with the bearer token, resumes the session from the Keychain after a cold relaunch, and
  signs out (the server got the bearer and refresh tokens). **That ran against a stand-in
  server on localhost that I wrote to speak the protocol, not a real HiveLog site**: the
  PKCE check it applies, the discovery document and the endpoints are the real contract, but
  the server side of consent and token issue is the PHP module's own tests' job.
- **Bugs found only by running the app:**
  1. A build with `CODE_SIGNING_ALLOWED=NO` has no entitlements and the Keychain refuses
     every write (-34018), so sign-in failed at the very last step with a generic "Something
     went wrong". Fixed twice: the simulator build is now signed to run locally
     (`project.yml`), and a Keychain failure now says so in words and logs its cause
     (`Messages`, `os.Logger`). The README records the gotcha.
  2. The app fetched the apiary list twice after sign-in (once in the model, once when the
     screen appeared); the model no longer does.
- **Observed, not investigated:** the first screen takes several seconds to appear after a
  cold launch of the Debug build on the simulator (a white launch screen first). It may be
  the Debug build or a cold simulator; worth timing on a Release build and a real phone.
- **CI:** both jobs passed on GitHub Actions' first run (`macos-15`): the package tests on
  the Mac, and the iOS job (the same tests on an iPhone simulator, project generation with
  XcodeGen, and a simulator build).
- **Real site (`kragebaekgaard.ddev.site`, the kbg dev site):** `hivelog_api` and
  simple_oauth enabled there, with a test user holding the `hivelog_field_app` role.
  - Found only by doing it: **enabling `hivelog_api` together with its dependencies failed**
    ("Table `consumer__grant_types` doesn't exist"), because the consumer entity's extra
    fields had not been created when the client was. `hivelog_api_install()` now calls
    `hivelog_api_ensure_consumer_fields()` first; `HivelogApiFreshInstallTest` covers it and
    was checked to fail without the fix.
  - The app found the real server over https (after trusting the mkcert root in the
    simulator) and showed "Kragebækgård".
  - **The protocol, run against that site by a script** (the same requests the app makes,
    logged in with a one-time link): discovery; authorize shows the consent form, which names
    "Vinculum"; Allow redirects to `hivelog://oauth/callback` with the `state` echoed; code +
    PKCE verifier exchange gives a 300-second access token and a refresh token; the API
    answers a bearer-only request (no cookie) with the test apiary and hive, and 401 with
    `WWW-Authenticate: Bearer realm="HiveLog API"` without one; refresh works and the old
    refresh token is then refused (rotation); `POST /sign-out` with the refresh token gives 204,
    after which the access token gets 401 and the refresh token 400.
  - **The sign-in sheet inside the app was not completed.** On the simulator the
    `ASWebAuthenticationSession` sheet did not see the Safari app's logged-in session (Safari
    showed the test user logged in; the sheet showed the login form), so someone has to type
    the test user's password into the sheet. I did not do that. On a phone the sheet normally
    shares Safari's cookies, but that is not confirmed.
- **Not verified:** TestFlight.
- **Since then, verified (2026-10-06):** the app's own sign-in against a real site, on a
  physical iPhone: signed in to the live site (`kragebaekgaard.dk`) through the OAuth sheet and
  used it (see 0212). The sign-out and a cold relaunch on the phone were not separately noted.

### Follow-ups
- Finish the in-app sign-in on `kragebaekgaard.ddev.site`: tap Sign in, type the test user's
  password into the sheet, Allow. Then check the apiary count, a cold relaunch and Sign out.
- Decide the GitHub home for the repo and the bundle id (`org.deburca.hivelog` was a
  placeholder) before 0209. The bundle id is now `nu.verdigris.vinculum` (see 0209).
- Backend release: the sign-out endpoint is new API surface, so a minor bump
  (2.9.0) when you next release.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
