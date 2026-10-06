---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[ios-field-app]]"
area: install
created: 2026-10-06
branch: feature/0215-demo-site-kit
release:
depends-on: ["[[0212-hivelog-api-write-compatibility-with-host-firewalls]]", "[[0213-assimilate-requirements-hooks]]"]
blocked-by:
---
# Task: a DDEV kit for the demo site App Review needs

## Context
Part of [[0209-ios-app-demo-server-and-app-store-release]]: App Review needs a public HiveLog
site and a working account, and the app is useless to a reviewer without one. The owner decided
the demo runs on **a small separate server**, built **with DDEV (OrbStack as the Docker
provider)**, tested locally first. This task is that kit; the server and its address are the
owner's. Part of [[ios-field-app]].

## Acceptance criteria
- [x] One command builds the whole site from nothing (`ddev demo-setup`): Drupal 11, HiveLog from
      the released package, the API with its OAuth server and keys outside the web root, the
      sensors and the demo-data module, a reviewer account, invented records, a reset baseline
- [x] The reviewer **owns** the records, so the app shows them (the app lists only what its
      signed-in user owns)
- [x] A handful of alerts, not dozens; photos on some inspections; stat tiles with values
- [x] `ddev demo-reset` puts the database and uploads back to the baseline
- [x] A check that signs in through the real OAuth flow and reads and writes the way the app and
      App Review will (`demo/scripts/check.py`), and the app's own client passes its smoke test
      against the site
- [x] Documented: local use, running it on a server (DDEV with Let's Encrypt), cron for resets
- [ ] Run on the real server, with a public name and certificate (the owner's server)
- [x] Walked through in the simulator by signing in as the reviewer (the owner typed the password):
      apiary, hive, queen, inspections, alerts and the stat tiles
- [ ] Screenshots for the store taken from it

## Implementation notes
Everything is under `demo/` in this repository, excluded from the Composer package.

- `composer.json` (+ `composer.lock`): Drupal 11, Drush, `simple_oauth`, `geofield`, `leaflet` and
  `hivelog/hivelog ^2.11` from Packagist. `.ddev/config.yaml`: PHP 8.4, MariaDB 11.8.
- `.ddev/commands/web/`: `demo-setup [--fresh]`, `demo-reset`, `demo-info` (prints the address,
  the logins and the values for `AppStore/en-GB/review_notes.txt`). Passwords are random, kept in
  `.demo-secrets` (not committed), or set by `DEMO_REVIEWER_PASSWORD` / `DEMO_ADMIN_PASSWORD`.
- `scripts/seed.php`: one apiary (Heathland Apiary, near Copenhagen) with three hives, four queens
  (the first hive's older one retired), fourteen inspections over seven weeks with generated
  photos on two, queen observations, and four seasonal jobs (two due now), plus a few mock sensor
  readings. Each record is validated as it is saved, so a rule change in HiveLog fails the
  seed loudly. It runs as user 1 (creating a child needs update access to its parent) and assigns
  everything to the reviewer.
- `scripts/check.py <url> [--write] [--insecure] [--print-token]`: login form, consent screen, code
  and PKCE verifier for a token, then the reads the app makes and checks that the data is what the
  review notes promise; `--write` also creates an inspection as plain JSON and attaches a photo as a
  multipart form; `--print-token` gives the app's smoke test a token.

## Found while doing it
- **The module's geofield patches cannot be applied in a fresh project.** `hivelog/hivelog`'s
  `composer.json` points at `web/modules/contrib/hivelog/patches/...`, which does not exist yet
  while Composer resolves patches; the install failed with "file could not be downloaded". cms2
  never met it because the module was already installed. The demo carries the two patches in
  `demo/patches/` and tells composer-patches to ignore the module's own
  (`extra.composer-patches.ignore-dependency-patches`). A stale `patches.lock.json` and
  `composer.lock` from a failed attempt kept the old paths: delete both after changing it.
- **`assimilate` cannot be enabled in the same command as `hivelog`**: its demo records need
  HiveLog's entity types, which do not exist yet ("The 'apiary' entity type does not exist"). The
  setup enables HiveLog first. Worth knowing for anyone enabling it by hand.
- **HiveLog gives every new apiary its whole starter calendar** (about 30 jobs). Three hives meant
  80 alerts, 68 of them critical: a reviewer would think the app broken. The seed deletes the
  starter calendar and creates four.
- **A request with the login session cookie and a bearer token is treated as cookie-authenticated**
  and a write is refused for a missing CSRF token (a 403). The app sends no cookie (the web sheet's
  cookies are separate), so it is unaffected, but the check script had to drop its cookie after
  signing in. Worth knowing for anyone testing the API with a cookie jar.
- `*.ddev.site` host names need a hosts-file entry the first time (a `sudo` step DDEV asks for);
  the owner's network does not resolve them.
- **The hive page had no stat tiles for the reviewer** (and so for any beekeeper's phone): the
  field-app role had no sensor permission. Fixed in the module, see [[0216-hivelog-api-sensor-tiles-for-the-field-app]].
- **The seed's spring job was overdue** (red) in October, the first thing a reviewer saw. It is
  replaced by a later varroa job (weeks 50 to 52), and the seed now says no job may be overdue.
- **Hive components:** the seed now adds four durable parts with weights (floor, brood box, honey
  super, roof), bought once, and uses them on the three hives, so Hive 1's *Net Colony Weight* is
  12.47 kg (21.97 kg on the scale less 9.5 kg of hive) instead of "Unknown".
- The sign-in sheet in the simulator took about a minute to draw the login page the first time;
  the server answered each request in two to three seconds (it was the simulator's web view).

## Verification
- `ddev demo-setup --fresh` from nothing: installs, enables, seeds and saves the baseline.
- `scripts/check.py ... --write`: all checks pass (the discovery document, the consent screen
  naming the app, the code exchange, 1 apiary, 3 hives, 4 queens, 14 inspections, 2 with photos,
  7 alerts, the schema, the stat tiles, an inspection recorded as plain JSON, a photo attached
  as a multipart form).
- **Reset:** 14 inspections, one written (15), reset, back to 14; the reviewer can still sign in.
- The app's own client, through its smoke test, reads and writes against it (7 alerts, queen
  Ingrid) and passes.
- In the simulator (iPhone 18 Pro, a fresh install): the app found the server ("Vinculum demo"),
  showed the sign-in picture for the first time, and opened the demo site's own login page in the
  sign-in sheet.

## Not verified
- The demo on a real server with a public name: DDEV's Let's Encrypt support is marked
  experimental, and I have not tried it. Resets by cron likewise.
- Going through *all* the review notes by hand: the new inspection and queen observation forms,
  the map and the QR scanner have not been walked on the demo yet. (Signing in needed the owner to
  type the password; I do not type credentials into a sign-in page.)
- Mock sensor readings are made when the demo is built, so a reset brings back readings that are
  hours or days old ("Updated 55 minutes ago" at build time, older later). Cron makes new ones
  on a running site; `demo-reset` does not.
- App Store screenshots (they need the 6.9-inch simulator, which Claude is not yet allowed to
  use).

## Related
- Project:: [[ios-field-app]]
- Decisions:: 
- Commits:: 
