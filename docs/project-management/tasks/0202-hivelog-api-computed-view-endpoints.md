---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: integration
created: 2026-10-04
branch: feature/0202-hivelog-api-computed-view-endpoints
release:
depends-on: ["[[0201-hivelog-api-submodule-scaffold]]"]
blocked-by:
---
# Task: Computed-view endpoints for the app

## Context
Values the app shows that no entity stores
([[0107-mobile-client-api-and-native-ios-app]] context §4): alerts,
stat tiles, empty/net weight, item availability. Each endpoint must
call the same service the web page uses, never reimplement it.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] `GET` needs-attention alerts for the current user (same collector as
      the dashboard, including submodule `hook_hivelog_needs_attention_alerts()`
      contributions)
- [x] `GET` hive stat tiles via `hivelog.stat_tile_builder` (includes
      nanoprobe's net-weight tile when enabled)
- [x] `GET` latest `HiveInsight` per hive when `nexus` is enabled
- [x] Each route under `/hivelog/api/...`, OAuth-authenticated, with
      entity access on the subject hive/apiary
- [x] Cacheable metadata carried through (per-user)
- [x] Kernel tests for each, including 403 on another user's hive
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes

### Endpoints
All under the versioned prefix, plain JSON, read-only, GET only (`405` otherwise),
OAuth bearer or session, and `application/json` errors:
- `GET /hivelog/api/v1/computed/alerts` — the dashboard's "Needs attention" queue
  for the current user: `{data: [{severity, chip, title, kind, detail, apiary,
  hive, calendar_action}], meta: {count, critical, year, week}}`. `apiary` and
  `hive` are `{id (UUID), name}`; `calendar_action` is `{id, scope}` on a seasonal
  row, which is what the app needs to report it done through the JSON:API.
- `GET /hivelog/api/v1/computed/hive/{hive}/stat-tiles` — the hive page's tiles in
  weight order (`key, label, value, sublabel, sublabel_variant, web_url`) and
  `meta.empty_weight_kg` (`null` when the hive has no components or an item has
  no weight), so the app can show net weight.
- `GET /hivelog/api/v1/computed/hive/{hive}/insight` — the latest AI insight as
  data, or `{data: null}`.

`{hive}` is the hive's **UUID**, the id the app already has from JSON:API (the
task text assumed `/hivelog/api/...` with no version; these sit under `/v1`). An
unknown UUID is 404, a hive the user cannot view is 403 (the same as the web
page and the JSON:API), no credentials is 401.

### The data-only split (the task's implementation note)
- **Alerts:** the collection code moved out of `DashboardController` into a
  service, `hivelog.alert_collector` (`HivelogAlertCollector`), unchanged apart
  from the move; the dashboard now calls it. Each alert row keeps its render
  fields and gains two optional data keys, `kind` and `subject`. Core's seasonal
  and low-stock rows set them, and so do nanoprobe's three rules and nexus's
  provider-stale rule; the hook documentation says a new source should. A row
  without them is still served, with no apiary/hive.
- **Stat tiles:** `HivelogStatTileBuilder::tilesForHive()` returns the sorted
  descriptors; `buildForHive()` now builds its markup from it.
- **Insight:** `hivelog_api` defines `hook_hivelog_api_hive_insight()`
  (`hivelog_api.api.php`) and nexus implements it through a new
  `HiveInsightPanelBuilder::buildHiveInsightData()`, which applies the panel's
  rules (apiary opted in, insight viewable) and returns data. So `hivelog_api`
  never depends on `nexus`.

### Decisions and findings
- The endpoint tests prove "the same collector" rather than assume it: the API's
  rows equal `hivelog.alert_collector->collect()` for the same user.
- Alerts cache **per user** (`user` context), carry the apiary list tag and the
  collector's own per-row dependencies, and expire by midnight, because the rows
  are week-relative. Tiles and insight get a short max-age, since sensor readings
  and components change without touching the hive.
- Low-stock alerts are in the queue (they are on the dashboard) though the app
  has no inventory screen; they are served as text with no resource to follow.
- `web_url` on a tile is the website URL, for "open in browser"; the app should
  prefer the resource ids.
- A moved-code side effect: the phpstan ratchet counts findings per file, so
  moving the code made its old findings "new". The moved code's types were
  tightened instead (no new baseline entries), and the dashboard's entries
  shrank (`get()` 12 to 4, two removed).
- Not built: item availability (inventory is web-only for the app), per-apiary
  alert filtering, push. Nothing paginates, since a user's alert queue is small.

### Verification
8 new kernel tests for the three endpoints (owner-scoped alerts that equal the
shared collector's rows, 401 without credentials, per-user cache metadata, tile
order and serialisation, the empty weight, the insight hook, 403 on another
user's hive, 404 on an unknown one, read-only), 3 new nexus tests for the
insight data (latest of several, empty cases and staleness, the hook wired to
nexus), and the OAuth functional test extended with bearer calls to the new
routes. **Full suite: 1,259 tests / 21,421 assertions, 0 failures, 0 errors**
(1,248 before + 11 new), run as twelve chunks covering every test file once, with
the dashboard refactor in. phpcs and phpstan clean; the baseline shrank.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
