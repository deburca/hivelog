---
type: task
tags: [hivelog/task]
status: in-progress
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

## Plan, and where it stands (started 2026-10-06)
The task has two kinds of work: what lives in the two repositories (done by me, tested), and
what needs an account, a host or a decision (the owner's). Both are listed so nothing waits
unseen.

### Audit of the app against what App Store review looks for
| Item | State |
|---|---|
| Bundle id, name, icon (1024, no alpha), launch screen | done |
| Export compliance (`ITSAppUsesNonExemptEncryption` = NO) | already set |
| **Privacy manifest** (`PrivacyInfo.xcprivacy`) | **added**: no tracking, no collected data, one required-reason API declared (file timestamps, `C617.1`, from the read cache and the outbox); tested |
| **Version and build number** | **fixed**: Info.plist now reads `MARKETING_VERSION` (1.0) and `CURRENT_PROJECT_VERSION` (1) from `project.yml`; before, the plist held literals and `MARKETING_VERSION` was ignored. The build number must rise with every upload |
| Usage strings | camera only; the photo picker needs none; no location is used |
| App Transport Security | HTTPS only, plus `NSAllowsLocalNetworking` for LAN servers; justified in the review notes (self-hosters on a Raspberry Pi) |
| Account deletion rule (5.1.1(v)) | not applicable: the app creates no accounts |
| Sign in with Apple | not required: the login is the site's own account system |
| Third-party SDKs | none |
| **Device family** | **universal (iPhone and iPad) today**, but no iPad layout was designed or tried and the store would then want iPad screenshots: see Decisions |
| Reachable server and a working account for review | **needs the demo site** (below) |

### Done in the app repository (`feature/0209-app-store-readiness`)
- `AppStore/en-GB/`: `description.txt`, `keywords.txt` (94 of 100 characters), `whats_new.txt`,
  `review_notes.txt` (with `{{PLACEHOLDERS}}` for the demo site), next to the name, subtitle and
  promotional text that existed. `urls.md`, `privacy-policy.md` (a draft to publish) and
  `privacy-labels.md` (the App Privacy answers, with the reasoning).
- **Recommended App Privacy answer: "Data Not Collected."** The developer receives nothing (no
  developer servers, no analytics, no SDKs); the app talks only to the server the person enters,
  which they or their organisation run. `privacy-labels.md` says what would change the answer and
  gives the conservative alternative if the owner prefers it. The owner confirms this answer; it is
  a legal-ish reading of Apple's rules, not a fact I can verify.
- Tests: limits and shape of every text field, the manifest's contents, and an opt-in check
  (`VINCULUM_SUBMISSION=1`) that fails while any `{{placeholder}}` is left (it does, today:
  three files).

### Decided (2026-10-06)
- **iPhone only for 1.0**: `TARGETED_DEVICE_FAMILY` is 1 and the iPad orientation list is gone
  (built and checked: `UIDeviceFamily` is `[1]`). iPad can follow once a layout has been designed.
- **The demo site runs on a small separate server** (not the shared OVH hosting): its setup is to
  be scripts in this repository, tested on a scratch site locally first. Still to settle: the host,
  its address, and the choice below.

- **Seller: the owner's personal name** (an individual developer account); the store shows it.
- **Primary category: Productivity. Age rating: 4+.**

### Decisions still open for the owner
1. **Where the demo site runs, and its address.** It must be public over HTTPS with a valid
   certificate, and stay up through review. Options: a subdomain on the existing OVH hosting (the
   2.11.0 write fix means its firewall is no longer a blocker for HiveLog), or a small separate
   server. A name like `demo.<domain>` is the usual choice.
2. **A support and contact address** for the policy and the support URL, and where the policy is
   published (the seller name is decided: the owner's own).
3. **Open source or not**, and under what licence (project open question). The pictures are
   AI-generated, whose copyright status is unsettled (see 0211), which matters if a licence is
   applied to the repository.
4. **The App Privacy answer** (above): confirm "Data Not Collected".

### Licence facts, for the open-source decision (briefing 2026-10-06; undecided)
- **The HiveLog module** is public and `GPL-2.0-or-later` (as a Drupal module it effectively has to be).
  The `beeswax` theme is the same. The **app repository is private with no licence** (all rights
  reserved by default) and has **no third-party Swift dependencies**.
- Bundled third-party material: the fonts (SIL OFL 1.1, renamed, `OFL.txt` shipped: fine under any
  app licence) and the 14 AI-generated pictures (copyright unsettled; a licence can only cover what
  the law lets be owned).
- **Copyleft and the App Store.** The GPL's "no further restrictions" clashes with Apple's terms for
  *other people's* GPL code in a binary Apple distributes (VLC was pulled in 2011 over it). A sole
  copyright holder can still ship their own binary under Apple's terms while publishing the source under
  the GPL, but only until outside contributions arrive without a CLA or relicensing right.
- **Realistic choices:** MIT or Apache-2.0 (no App Store friction, no CLA; Apache adds a patent grant);
  MPL-2.0 (changes to the app's own files must be shared, App Store compatible); GPL-2.0-or-later (one
  licence across the stack, needs a CLA/DCO-with-relicensing to stay shippable); source-available
  (readable, not reusable; not "open source"); or keep it closed for 1.0 and open it later (opening
  later is possible, un-opening is not).
- **Before publishing anything:** the test fixtures are responses captured from the dev site (real
  apiary and hive names, locations): scrub them and check the git history; decide how the AI images
  are labelled; reserve the *name and icon* in a short trademark note (a licence does not grant them);
  decide a contribution policy.

### Demo site: design (not built yet; needs decision 1)
- A throw-away HiveLog site: Drupal 11 with `hivelog`, `hivelog_api` (and its `simple_oauth`,
  keys outside the web root), `nanoprobe` and `assimilate`. Every setup step as a script in this
  repository (`demo/`), tested on a scratch DDEV project before it touches a host.
- **A reviewer account** with the `hivelog_field_app` role, that **owns** the demo records:
  `assimilate`'s provisioner creates an apiary, a hive, an inspection and sensor devices but sets
  no owner, and the app only shows what the signed-in user owns, so the seeding must run as, or
  assign to, the reviewer. For a useful review it also needs a queen, a second hive, calendar
  actions (so Alerts has something) and a few inspections with photos (public-domain pictures).
- **Reset on a schedule**: a baseline database dump taken once after seeding, restored
  nightly by cron with the uploaded files emptied, so whatever a reviewer records disappears.
- The reviewer password is a throw-away one that belongs only to the demo site; it goes in the review
  notes and nowhere else.
- Also useful for screenshots: capture them from the simulator against this site (the
  6.9-inch iPhone class, 1320 x 2868, is the required size; the simulator list has an
  iPhone 18 Pro Max).

### TestFlight and submission (the owner's hands, with my checklist)
1. Fill in the placeholders, publish the policy and set the support URL; run the opt-in
   submission test.
2. Xcode: Product > Archive, Distribute App > App Store Connect > Upload (automatic signing and
   the cloud distribution certificate); raise `CURRENT_PROJECT_VERSION` for each upload.
3. App Store Connect: select the build, paste the text from `AppStore/en-GB/`, answer App
   Privacy, add screenshots, set the age rating and category, add the review notes and the demo
   account, and submit for TestFlight external review (a short review) for the beekeeper testing,
   then for App Review.
4. Internal testers (team members) need no review, so you can start there with your own phone.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
