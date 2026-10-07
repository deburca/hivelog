---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[ios-field-app]]"
area: ios
created: 2026-10-07
branch:
release:
depends-on: "[[0219-ios-app-map-zoom-and-hive-rows]]"
blocked-by:
---
# Task: iOS app: tint a hive's badge by its insight

## Context
The owner liked the status shown on a hive's row (task 0219) and suggested colouring it by the hive's
insight status (all clear, out of date, and so on). We settled on **tinting the hive's hexagon badge**
(the lettered "H" mark) rather than the status chip's text: a red "ACTIVE" or green "DEAD" would contradict
itself, and the colour would then mean something other than the word beside it. The tint appears on each
row of an apiary's hive list and on a new header of the hive page.

## Acceptance criteria
- [x] The badge is tinted: green (all clear), amber (inspect soon), red (act now), grey (out of date: a
      stale insight, whatever it said); no insight, or a verdict the app does not know, leaves it plain
- [x] Never by colour alone: a small corner symbol (tick, eye, exclamation, clock) and, on the hive page, the
      words; VoiceOver is told the verdict
- [x] One request for the whole apiary, not one per hive
- [x] The hive page has a header with the tinted badge, the hive's name, breed and type, its status and the
      verdict
- [x] A failed or missing verdict request leaves the list as it was (a server older than the endpoint answers 404)
- [x] Server tests, app tests, checked on the simulator against the demo site
- [ ] Released (server 2.12.0, app)

## Implementation notes
- **Server, `hivelog_api`:** `GET /hivelog/api/v1/computed/apiary/{apiary}/hive-insights`
  (`ComputedViewsController::apiaryHiveInsights`) returns one entry per viewable hive that has an insight
  (`hive` UUID, `verdict`, `verdict_label`, `stale`, `generated`), by invoking the same
  `hook_hivelog_api_hive_insight()` per hive, so it has the same access and the same opt-in rules (an apiary
  must have AI insights on) as the per-hive endpoint, and `hivelog_api` still does not depend on `nexus`.
  Read-only; 401, 403 and 404 as the other computed routes.
- **A bug found on the way:** the field-app role had **no `view own hive insight` permission**, so
  `computed/hive/{hive}/insight` (task 0202) always answered "nothing" for an app token and the app has
  never shown an insight on a real site, the same gap as the sensors in [[0216-hivelog-api-sensor-tiles-for-the-field-app]].
  The role now holds that one permission (view own only; granted only where `nexus` defines it, topped up on a
  later `nexus` install, and `hivelog_api_update_10003()` for existing sites).
- **App:** `HiveVerdict` and `APIClient.hiveVerdicts(in:)` (kit); `HiveInsightTint` (colour, words, symbol),
  `HiveBadge` (the tinted mark with its corner symbol), `HiveListView` (a third best-effort loader beside the
  hives and queens) and a `header` on `HiveDetailView` built from the insight and queen the page already loads.
- **Demo kit:** `nexus` and `collective` (and `drupal/key`) are enabled; the seed writes three insights
  (inspect soon, all clear, and one three days old for "out of date") directly, with no provider;
  `scripts/freshen.php`, run by `ddev demo-reset`, makes them (and the sensor readings) current again after a
  reset, because an insight is out of date after 48 hours and a baseline only gets older. `check.py` checks the
  new endpoint and the sensor tiles.

## Verification
- `hivelog_api` kernel tests: the computed-views class (10 tests, two new for the apiary endpoint: one entry per
  hive, no recommendation text, another's apiary 403, unknown 404, no sign-in 401, read-only), the install test
  (insight is view-own only; `nexus` installed later tops the role up; the update hook) and a new class,
  `HivelogApiHiveInsightsTest` (4, with `nexus` enabled and the real role): the owner gets the insight and the
  verdicts, an old insight is stale, no opt-in means none, another's are never served. **Without the permission,
  2 of the 4 fail.** 25 tests across the three classes pass. phpcs (CI's coder) and phpstan are clean.
- App: `swift test` 150 + 95 pass (verdict decoding and request, the tint rules including stale-wins, an unknown
  verdict, and that every tint has its own words and symbol).
- Simulator against the demo: Hive 1 amber with an eye, Hive 2 green with a tick, Hive 3 grey with a clock; the
  hive page header shows the tint, "ACTIVE" and "ALL CLEAR", and the AI Insight card now appears.
- `check.py` against the demo: all checks pass, including three verdicts of which one is out of date.

## Not verified
- On a real site with `nexus` generating insights daily.
- The grey (out of date) and amber states on the hive page header (the list was checked for all three).
- A large text size: the header may need to stack.

## Related
- Project:: [[ios-field-app]]
- Decisions:: 
- Commits:: not committed yet. Server side on `main` of this repository; app side on the app repository's `feature/0219-map-zoom-and-hive-rows`, together with 0219.
