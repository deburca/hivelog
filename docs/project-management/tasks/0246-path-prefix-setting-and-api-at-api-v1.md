---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: routing
created: 2026-10-30
branch: feat/path-prefix-and-api-v1
release: 4.0.0
depends-on: ["[[0107-mobile-client-api-and-native-ios-app]]"]
blocked-by:
---
# Task: A setting for the page prefix (hivelog.eu/app) and the API at /api/v1

## Context
On a site named after the module the pages read `hivelog.eu/hivelog/...`. The owner has one user, so now is the
time to change URLs. Two changes, decided together:

1. **A setting, not a rename.** `$settings['hivelog_path_prefix']` in `settings.php`: unset, the pages stay at
   `/hivelog/...`; set to `app`, they are served at `/app/...`. Routes stay *declared* under `/hivelog`, so the
   entity link templates, route names, parameters, access and every test are unchanged, and
   `HivelogPathPrefixRouteSubscriber` moves the paths when the router is built.
2. **The field-app API moves to `/api/v1`** (from `/hivelog/api/v1`), always, because it is a versioned
   contract that should not depend on a page prefix. `api_version` stays 1. The installed Vinculum app breaks
   until it is updated to the new path.

## Acceptance criteria
- [x] `HivelogPaths` reads and validates the setting (lower-case letters, digits, hyphens, at most 32 characters, not a reserved name); an unusable value falls back to `hivelog`
- [x] `HivelogPathPrefixRouteSubscriber` rewrites every route under `/hivelog` (module, submodules, entity link-template routes), except the machine endpoints under `/hivelog/api/` (sensor ingestion, collective context API)
- [x] The breadcrumb builder, `hivelog_preprocess_html()` and `hivelog_preprocess_page()` match by `HivelogPaths::matches()`, not the literal
- [x] `HivelogApiResources::PATH_PREFIX` is `/api/v1`; routes, path processor, subscribers, tests, README, AGENTS.md and the demo kit follow
- [x] `HivelogPathsTest` (unit) and `HivelogPathPrefixTest` (kernel); the `hivelog_api` suite passes on the new prefix
- [ ] hivelog.eu: `settings.php` sets `app`; `system.site` 403/404 pages, `hvg_demo` redirects follow; the app is updated to `/api/v1`

## Notes
- Old `/hivelog/...` URLs stop working once a prefix is set (no redirect): update the site's 403/404 pages and any menu link or hard-coded link to a path.
- A prefix cannot be changed without rebuilding the router (`drush cr`).
- The host firewall on hivelog.eu has refused unfamiliar paths and content types before (see [[0212-hivelog-api-write-compatibility-with-host-firewalls]]): check `/api/v1` with a real sign-in on the live host after deploy.

## Related
- Amends:: [[0107-mobile-client-api-and-native-ios-app]]
