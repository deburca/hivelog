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
- [ ] Name and logo: clearance search of the existing "Vinculum" filings done and a filing
      strategy chosen (see "Trademark: the name and the logo"); a trademark attorney has looked at it
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
5. **The name's second word, and what to file** (see "Trademark: the name and the logo").

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

### Trademark: the name and the logo (opened 2026-10-07; not started)
**Problem.** A few trademarks for "Vinculum" are already filed by others. The idea was to add a
second, Borg-related word to make ours unique and still relevant ("Vinculum" is the Borg's linking
device, a Latin word meaning "bond"). This section records the reasoning so it is not re-derived;
it is not legal advice, and nothing here has been searched or cleared.

**What a second word does and does not do**
- An office compares the *dominant, distinctive* part of a mark. A descriptive word added to it
  (Hive, Bee, Apiary, Beekeeping) is normally disclaimed, so "Vinculum" stays the part that is compared
  with the earlier filings: it adds nothing.
- A second word helps only if it is distinctive in itself (coined, arbitrary or suggestive).
- Whether the earlier filings matter at all depends on their **classes, countries and goods or
  services**, not on the word. A "Vinculum" for accounting software or a medical device may not
  conflict with a beekeeping app in classes 9 (software), 42 (software as a service) and 31 (bees);
  a narrow specification of goods can be part of the filing. Unknown until the filings are read.

**Borg risk.** Paramount owns the Star Trek terms; "Vinculum" is a real Latin word, but pairing it
with Borg-specific words invites association with the franchise and an objection. **Avoid** "Borg",
"Assimilate", "Unimatrix", "Locutus", "Resistance is futile", and anything that sounds like a
character or species. The submodule names (`assimilate`, `nexus`, `collective`, `nanoprobe`) are
internal and are not in the mark; do not market them with Borg references.

**Candidates** (none searched or cleared):

| Option | For | Against |
|---|---|---|
| **Vinculum Apis** | Latin "bond of the bee"; coined-sounding pair, no Paramount link; consistent with the Latin name | Not a Borg wink; "Apis" is used in the bee trade |
| **Vinculum Collective** | Dictionary word that evokes both the Borg and a colony | Common and weak; probably disclaimed |
| **Vinculum Hivemind** | Borg nod and bee metaphor | Likely crowded with existing "Hivemind" marks |
| **Vinculum Drone** | Bee and Borg drone | Suggests UAVs: confuses a beekeeping app |

**Recommendation:** **Vinculum Apis** as the word mark, and the logo filed separately as a
figurative mark. A logo can coexist with similar word marks if its visual elements differ. A plain
hexagon is hopeless to register (it is a generic shape, and a hive-cell hexagon is descriptive for
this product), so the logo needs a distinctive element: the idea so far is a **bee hatching out of
its brood cell**, or two linked hexagons (the *vinculum*, the link). Settle the logo before filing it.

**Logo sketches (2026-10-07).** A supplied AI-generated bee-in-a-hexagon was judged unusable as the
registered mark (generic and descriptive; unsettled ownership; flat-topped, where the app's hexagon
points up). Two vector sketches of the distinctive ideas are in
`docs/project-management/assets/logo-sketches/` (`make_sketches.py` writes them, `make_sheet.py` builds `logo-sketches-sheet.png`,
which shows each at size, in one colour and two, and as an app-icon tile). They are plain geometry, to brief
a designer with, not finished artwork:
- **A, a bee leaves its cell** (`a-hatching-*.svg`): a bee in three-quarter view flies up and out of a
  hexagonal cell, head first, wings up, with speed lines behind it; the cell wall is simply open
  where it went through (the folded flaps of the first drafts were dropped as unnecessary detail). Drawn after a supplied AI picture of a bee leaving
  a cell, which had more movement and positivity than the first sketch (the geometry is our own).
  Charming and clearly "bee", but it is still a bee in a hexagon at heart, and the detail turns to
  mush at 32 px.
- **A (alternate), front view** (`a-front-*.svg`): the first sketch of the idea, a bee sitting head-up
  in the cell with its head and antennae through the torn cap. Calmer; kept for comparison.
- **B, linked hexagons** (`b-linked-*.svg`): two hexagon rings, one through the other, left over right at
  the top crossing and right over left at the bottom. Simple, holds up at 32 px, works in one colour,
  and ties directly to the name (*vinculum*, the link). Less obviously a bee app, which the store
  listing, the name and the colours can carry (pine green and wax, the app's own).
- **Leaning to B** as the registered logo: more distinctive, scales, and does not depend on a bee
  illustration. A (the flying bee) could live on as a secondary mascot or an empty-state picture, or be the logo if
  the owner values movement over scalability. The transparent
  crossings are cut with masks, not a background-coloured halo, so B sits on any background.
- Still to do: a designer redraws the chosen one (the sketches have a hairline at the crossing's
  clip edge and unrefined proportions), and the figurative-mark search runs on the *redrawn* shape.

**Steps**
- [ ] Read the earlier "Vinculum" filings in TMview / EUIPO eSearch (and the UK IPO and USPTO if
      the app will be sold there): owner, status, **classes (9, 42, 31)** and specification. Record
      the ones that matter here.
- [ ] Search the second-word candidates the same way ("Apis", "Collective", "Hivemind", "Drone" with
      "Vinculum", and on their own in classes 9, 42 and 31).
- [ ] Decide the mark: word, word plus second word, or logo only; which countries (the App Store
      listing decides where the name is seen).
- [ ] Settle the logo (hatching bee or linked hexagons; sketched, B preferred) before any figurative filing.
- [ ] Have a trademark attorney review the choice and the specification of goods and services.
- [ ] File; then add the symbol and a short trademark note to the repository and store text. A
      licence does not grant the name or icon (see the licence facts above).
- [ ] Check the App Store name is free in App Store Connect (it was reserved as "Vinculum"; a
      second word may need the listing name or subtitle to change).

**Not decided / not known:** which earlier filings exist and in which classes; the countries to file
in; whether a conflict makes the plain name unusable and the second word a necessity rather than a
nicety.

### Demo site: built as a DDEV kit ([[0215-demo-site-kit]]); still to run on the owner's server
The kit is in `demo/` of the HiveLog repository, tested locally (setup, reset, an end-to-end check
through the real OAuth flow, and the app's own smoke test). What remains is the owner's server and
its public address. The design below is what was built.
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
