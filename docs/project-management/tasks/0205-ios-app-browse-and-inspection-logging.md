---
type: task
tags: [hivelog/task]
status: review
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
- [x] Apiary list and map (apiary `geolocation`), hive list per apiary
- [x] Hive screen: active queen, last inspection, recent inspections
- [x] New inspection form covering the inspection payload, with enum
      values loaded from the API rather than hard-coded in Swift
- [x] Camera and photo-library capture, uploaded to the inspection's
      `images` field. Library capture verified end to end on the simulator;
      **camera capture verified on a real iPhone (2026-10-06), against the live
      site, after [[0212-hivelog-api-write-compatibility-with-host-firewalls]].**
- [x] Server-side validation errors shown against the right field
- [ ] Large tap targets; usable one-handed with gloves (manual check). *On a real phone
      (2026-10-06) the owner found the interface "very fat-finger friendly"; it has not been
      tried with gloves on, so this stays open.*
      **Built for it (every control at least 56 points tall, whole rows are the
      target, choices are big buttons), but only a person with gloves on a real
      phone can tick this.**

## Implementation notes

### Backend (this repo)
Two things the app could not work without, found by trying to build it:

- **Filtering was broken on the API for every ordinary beekeeper.** Any request
  with a `filter` came back empty. JSON:API adds an always-false condition to a
  filtered query unless a module vouches for filtering across the entity type
  (`hook_jsonapi_entity_filter_access()`), and HiveLog's types had never done so;
  only `administer hivelog` got through. 0203's access tests never filtered, so it
  passed. `HivelogApiQueryAccess::filterAccess()` now says "allowed among all" for the
  exposed types **on the versioned prefix only**: the condition JSON:API sets for that
  answer is that the query is narrowed to viewable records, which the 0203 alter
  already does. `HivelogApiFilterTest` pins that a filter cannot probe another user's
  records (not by `filter[name]`, nor through `filter[hive.name]` /
  `filter[hive.apiary.name]`), that filtering by owner only ever matches the user
  themself, and that plain `/jsonapi` keeps JSON:API's default.
- **`GET /hivelog/api/v1/schema`** (the "small schema endpoint" this note anticipated).
  JSON:API does not expose allowed values, labels or limits. The endpoint reads the
  entity field definitions and returns, per exposed type, each writable field's `kind`,
  `label`, `required`, `multiple`, `options` (value + label), `min`/`max`/`max_length`
  and a reference's `target`. The app's form is built from it, so a new option or field
  on the server needs no app release. `HivelogApiSchemaTest` submits every offered
  option of every type and requires the server to accept it, and requires every field to
  be in the v1 contract fixture. See AGENTS.md.

### App (`~/Development/vinculum`, branch `feature/0205-browse-and-inspection-logging`)
- **Data layer (`HiveLogKit`)**: `APIClient` (apiaries, hives, active queen, recent
  inspections with photos, schema, create inspection, upload photo), JSON:API decoding
  kept internal, models of its own, `InspectionDraft` (only what was answered is sent;
  answers hidden by a switch turned off again are dropped), `InspectionLayout` (groups
  the schema's fields in the order a beekeeper works down a hive; a field the server
  adds later still appears, under "More").
- **Screens (`HiveLogUI`)**: apiary list / map, hive list, hive page, inspection detail,
  the inspection form (every control at least 56 points tall; a choice is a grid of big
  buttons, a count has large minus / plus buttons), library and camera photos.
- **Saving**: the inspection is saved first, then its photos one by one. If a photo
  fails the inspection stays saved and only the missing photos are retried, so nothing
  is saved twice. Photos are scaled to 2048 pixels, re-encoded as JPEG and stripped of
  metadata (the picture's GPS position) before they leave the phone.
- **Errors**: the server's 422 comes back field by field; the form scrolls to the first
  one. A sign-in that ends mid-use sends the person back to sign in.

### Verification
- Server: 13 new tests; all 76 `hivelog_api` tests pass; phpcs and phpstan clean.
- App: 103 tests pass on the Mac and on an iOS 27 simulator, against responses captured
  from the real dev site (`Tests/HiveLogKitTests/Fixtures/api-*.json`).
- An opt-in smoke test (`RealServerSmokeTests`, skipped unless given a server and
  tokens) ran the app's real client against the kbg dev site: read, a refused value, a
  create, a photo upload and a read-back.
- **Run on the simulator against the kbg dev site, signed in as a test user:** apiary
  list and map (pin at the real coordinates, tap opens the hives), hive list, hive page
  (queen with marking colour, last and earlier inspections with photo counts), the form
  (selecting choices, a switch revealing its dependent field, a server rule shown under
  its field and the form scrolling to it, twice: varroa count and feed type), a photo
  from the library, saving, the saved inspection read back with its photo, a cold
  relaunch resuming the saved session and refreshing the expired token.

### Found while doing it
- **The sign-in sheet in the simulator does not share Safari's cookies**, so the person
  logs in inside the sheet. That is how it will normally work on a phone too, unless
  Safari already has a session.
- **kbg redirects a login to its admin Dashboard**, ignoring the `destination` the OAuth
  consent flow sets, so after logging in inside the sheet the person lands on the
  Dashboard, not the consent page. Closing the sheet and tapping Sign in again goes
  straight to consent. This is a site setting on kbg (something redirects after login),
  not the module or the app; worth checking before real users sign in there.
- **Labels follow the site's language; the choices do not.** Field labels come from the
  server in the interface language (Danish on kbg) while the options ("Good", "Fair",
  "Abundant") are untranslated strings in `baseFieldDefinitions()`, and the section
  titles are English in the app. A Danish beekeeper sees a mixture. Fixing it properly
  means translating the allowed values on the server (and localising the app's section
  titles); left for a later task.
- **Photos are fetched without credentials.** They are shown from the file's public URL.
  A site that serves inspection photos from the private file system would show nothing;
  the kbg dev site uses public files.
- **The map zooms in very tightly on a single apiary.** Fine for one; wants a minimum
  span when there is only one pin.

### Not done
- Gloves check; the camera on a real phone. (Since 2026-10-06 the app has been on a physical
  iPhone against the live site, and **adding an inspection with photos works** there, after
  [[0212-hivelog-api-write-compatibility-with-host-firewalls]]; whether those photos came from the
  camera or the library, and use with gloves, were not noted.)
- Editing or deleting an inspection (not in this task; the role has no delete).
- Offline: this version needs a connection to open anything. The outbox and read cache
  are 0207.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
