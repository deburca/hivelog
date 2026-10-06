---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[ios-field-app]]"
area: app
created: 2026-10-06
branch: feature/0212-hivelog-api-write-compatibility-with-host-firewalls
release: 2.11.0
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]"]
blocked-by:
---
# Task: HiveLog API: writes that get through a shared host's firewall

## Context
First test of the app against the live site (`kragebaekgaard.dk`, OVH shared hosting): reading
worked, every write failed with "Your account isn't allowed to do that". The web server's log
showed the write answered by Apache itself (a 199-byte 403, no PHP in the line), while
`POST /oauth/token` in the same minute was answered by PHP. Probing the site with harmless
unauthenticated POSTs showed what the host's firewall does:

| Request | Result |
|---|---|
| `POST` as `application/vnd.api+json` (what JSON:API requires for a write) | 403 from the firewall |
| `POST` as `application/octet-stream`, `image/jpeg` or `text/plain` (the photo upload is the first) | 403 from the firewall |
| `POST` as `application/json`, `multipart/form-data` or `application/x-www-form-urlencoded` | reaches Drupal |
| `PATCH` and `PUT` with any type | 403 from the firewall |
| `GET` | reaches Drupal |

So on a host like that the app can read and sign in but cannot record anything, and nothing
in the app or the status report says why. The dev site (DDEV, no firewall) never showed it.
HiveLog is self-hosted, often on exactly this kind of shared hosting, so the API must work
through such a firewall without the beekeeper having to reconfigure the host. Part of
[[ios-field-app]]; the API is [[0201-hivelog-api-submodule-scaffold]].

## Acceptance criteria
- [x] Server: on `/hivelog/api/v1` a `POST` carrying credentials in a header, with
      `Content-Type: application/json`, is treated as `application/vnd.api+json`
- [x] Server: a photo upload can be sent as `multipart/form-data` (one file part) and
      is handled exactly as the `application/octet-stream` upload is
- [x] Nothing changes for a request without a header credential (a browser session), for
      plain `/jsonapi`, or for the computed, schema, sign-out and discovery routes
- [x] The discovery document says the server accepts these (`meta.hivelog_api.features`),
      so an app can use them only when offered
- [x] The API v1 contract is unchanged (additive), and `HivelogApiContractTest` passes
- [x] App: record writes as `application/json` and photos as `multipart/form-data` when the
      server offers it, the standard types otherwise
- [x] App: a 403 that is not HiveLog's own JSON says the host's firewall refused it (a
      403 from HiveLog still says "your account isn't allowed"; showing the server's own
      reason is not done)
- [x] Tests on both sides (`--group hivelog`; the app's Swift tests)
- [x] `AGENTS.md` updated
- [ ] Checked on the live site once it is deployed there (the user's phone)

## Implementation notes

### Design
- **An early request subscriber** in `hivelog_api` (before routing, so before JSON:API's
  `_content_type_format` requirement is matched) that rewrites the request, only when
  *all* of these hold: it is on the versioned prefix and not one of the plain-JSON routes
  (`computed/`, `schema`, `sign-out`, discovery); the method is `POST`; the request has an
  `Authorization` header. The last condition keeps the leniency off cookie sessions: a browser
  form from another site can send `multipart/form-data`, but cannot send an `Authorization`
  header, so the shim adds no cross-site route. (JSON:API's own CSRF header check still
  guards cookie-authenticated writes.)
- **JSON bodies:** `Content-Type: application/json` becomes `application/vnd.api+json`.
- **Photos:** a `multipart/form-data` request with a file part is turned into the
  `application/octet-stream` request JSON:API's upload route expects: the file's bytes become
  the body and the part's file name becomes the `Content-Disposition: file; filename="…"`
  header. JSON:API then does everything it does today (extension and size validation, access,
  creating the file, attaching it). A request with no file part, or one over a size cap, is
  refused with a plain 400 / 413 JSON:API error.
- **Discovery** gains `meta.hivelog_api.features` (a list of names), additive, `api_version`
  stays 1. The app reads it from discovery and falls back to the standard types if absent
  (an older server).
- **Out of scope:** `PATCH` and `PUT` (the app only creates records, and a method override
  would be a bigger, separate change); anything the host's firewall does to the *content* of a
  request, which is not known until a real write is tried.

### Key files
- `modules/hivelog_api/src/EventSubscriber/`, `hivelog_api.services.yml`,
  `src/Controller/DiscoveryController.php`, `AGENTS.md`
- App repo: `Sources/HiveLogKit/API/APIClient.swift`, `APIError.swift`, `Discovery.swift`,
  `Messages.swift`

### Update hook needed?
No.

## What was built
**Server (`hivelog_api`, this repository)**
- `HivelogApiWriteCompatibilitySubscriber` (a `kernel.request` subscriber at priority 40, before
  routing): on the versioned prefix, for a `POST` with an `Authorization` header, not on
  `computed/`, `schema` or `sign-out`, it relabels `application/json` as
  `application/vnd.api+json`, and turns a `multipart/form-data` request with a file part into the
  raw upload JSON:API expects (`Content-Type: application/octet-stream`, the part's file name as
  `Content-Disposition`). No file part, an invalid or empty file: a plain 400; over 10 MB: 413.
- **Core reads the upload from `php://input`, not from the request**, which PHP leaves empty for a
  multipart body, so relabelling is not enough. `HivelogApiInputStreamFileWriter` decorates
  `file.input_stream_file_writer` and, for a request the subscriber marked
  (`hivelog_api.upload_path`), hands core the uploaded temporary file where it would read the
  stream. First tried, and abandoned, rewriting the request body in place: it passed as far as
  the validation and then failed because core never looked at it. Found by a failing test.
- Discovery: `meta.hivelog_api.features` = `["json_write", "multipart_upload"]`
  (`HivelogApiResources::FEATURES`). `api_version` stays 1.
- `HivelogApiCompatibleWritesTest` (8 tests): a record as `application/json` is created and
  keeps client UUIDs, a retry is a 409 and a foreign hive a 422; plain `/jsonapi` is not made
  lenient; no credentials means no rewriting; only POSTs on resource routes are touched; a
  multipart photo is attached with the same bytes and name; no file or an empty file is a 400;
  another beekeeper's record cannot be given a photo; discovery lists the features.
  All 73 `hivelog_api` kernel tests pass; phpcs (CI's coder) and phpstan are clean.

**App (`vinculum`, branch `feature/0212-compatible-writes`)**
- `ServerFeatures` (an actor): asks the discovery document once per launch what the server
  offers, empty if it could not be asked (and asked again next time). Not stored with the
  sign-in, so a server upgraded after signing in is noticed, and a session saved by an older
  build still works (`ServerInfo.features` decodes as empty when missing).
- `APIClient` sends a record as `application/json` and a photo as a multipart form (one part
  named `file`, name filtered to letters, digits and `.-_`) only when offered; otherwise as
  before. The app only ever uses `POST`.
- `APIError.refusedByHost`: a 403 whose body is not HiveLog's JSON error document. Its message
  names the firewall and `/hivelog/api/`; the outbox keeps such a record as "needs attention"
  with the same words, to be sent again once the host is fixed.
- Tests (14 new, plus 3 existing ones updated because a 403 now needs HiveLog's JSON to count
  as the person's account): JSON and multipart exactly as sent, the standard types otherwise,
  a file name cannot break out of the part's header, the two kinds of 403, features asked
  once and asked again after a failure, discovery with and without features, an older saved
  session. The opt-in smoke test now uses the features.

## Verification, and what is not verified
- **Real HTTP against the dev site (`kragebaekgaard.ddev.site`, nginx and PHP-FPM, with the
  working copy of the module):** with an OAuth bearer token for the test user, `curl` created
  an inspection as `application/json` (201, the client UUID kept), attached a photo as
  `multipart/form-data` (200; Drupal stored it as `field-photo.png`), and the standard
  `application/octet-stream` upload still worked. Then the app's own client, through its
  smoke test, did the whole read and write path including a photo, using the features the
  server advertised.
- **Not verified: the live site.** It needs a release (2.11.0, new API surface, so a minor
  bump) deployed there. Whether the host's firewall lets these requests through is not known
  until a real write is tried: the probes showed it accepts the content types, not what it does
  to the bodies (a multipart body holding a JPEG is the likeliest to be questioned). If it
  refuses even these, the remedy is the host's: ask for `/hivelog/api/` to be exempted.
- The app change has not been run on a phone or the simulator; the client is covered by the
  unit tests and by the smoke test against a real server.

## Found while doing it
- `drush uli vinculum_test` makes a login link for **uid 1**: in Drush 13 the argument is a path
  and the user is `--name`. I caught it because the link's user id was in the URL; the dev
  site's admin link was used once in a throwaway cookie jar. Use `drush user:login --name=…`.
- Only `POST`, `application/json` and `multipart/form-data` are known to pass on the live
  host. `PATCH` and `PUT` are refused whatever the type, so any future feature that edits a record
  needs its own answer (a `POST` that means an update).

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
