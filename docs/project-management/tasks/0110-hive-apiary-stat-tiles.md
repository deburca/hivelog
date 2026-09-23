---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-22
completed: 2026-09-22
branch: feature/0110-hive-apiary-stat-tiles
release:
depends-on: ["[[0106-sensor-device-management-ui]]"]
blocked-by:
---
# Task: "At a glance" stat tiles on the Hive/Apiary/Dashboard pages

## Context
User request, made while reviewing [[0107-assimilate-mock-sensor-data-module]]'s
demo hive page: the dashboard's own `hivelog:stat-tile` row (value +
label + link) should also appear at the top of `/hivelog/hive/{hive}`
(and, symmetrically, the Apiary page) — an AI Insight tile showing the
current verdict, and one tile per sensor device showing its latest
"vital statistic." Two design questions were resolved with the user
before implementing:
- The AI Insight tile links to the existing "AI Insight" panel further
  down the *same* page (an anchor link), not a separate page —
  `HiveInsight` has exactly one recommendation and a bullet-point
  `signals` string, not a structured to-do checklist, so there's
  nothing a dedicated page would add.
- Each sensor tile links to a *new* full-history trend page per device
  (there was no collection route for `SensorReading` at all before
  this task), filterable by metric and date range — richer than a
  plain paginated data table.

**Follow-up, same day**: reviewing the live result, the user asked for
the same treatment on the dashboard's own "AI Insights" section
(`/hivelog`) — each insight as its own stat tile linking to the
impacted hive — and separately flagged that the old "0 hives all clear
today" summary text was genuinely ambiguous: it read the same whether
every hive was confirmed fine or nothing had been analysed yet.
Folded into this same task rather than a new one, since it's the
identical "convert a text/row summary into per-item stat tiles"
pattern applied to one more page.

## Acceptance criteria
- [x] New core hooks `hook_hivelog_hive_stat_tiles()`/
      `hook_hivelog_apiary_stat_tiles()` (`hivelog.api.php`), mirroring
      `hook_hivelog_app_nav_items()`'s own "implementation returns plain
      descriptors, core builds the uniform markup" shape (task 0105) —
      `HivelogStatTileBuilder` collects, sorts by weight, and renders
      every tile via the same `hivelog:stat-tile` component the
      dashboard uses. `hivelog` core contributes no tiles of its own;
      `HiveController`/`ApiaryController` inject the builder and place
      the row at `#weight => -1` (before every other section).
- [x] `.hivelog-stat-tiles` (the grid wrapper) moved from
      `css/hivelog.dashboard.css` into `components/stat-tile/stat-tile.css`
      so it auto-attaches wherever the `hivelog:stat-tile` component
      renders, regardless of which module built the tile — no library
      wiring needed in nexus/nanoprobe.
- [x] `nexus`: `HiveInsightPanelBuilder::buildHiveStatTile()`/
      `buildApiaryStatTile()` — value is the verdict label, sublabel is
      "Checked @time ago", sublabel_variant maps act_now→critical/
      inspect_soon→warning/all_clear→default, url is an anchor to the
      panel's own container (`PANEL_ANCHOR_ID`, now also given an `id`
      attribute). Absent entirely when there's no insight to summarise.
- [x] `nanoprobe`: `SensorPanelBuilder::buildHiveStatTiles()`/
      `buildApiaryStatTiles()` — one tile per enabled device with an
      accessible reading, headlining `PRIMARY_METRIC_BY_DEVICE_TYPE`'s
      metric for that `device_type` (falling back to whichever
      non-diagnostic metric the device most recently reported), a
      "Updated/No data for @time ago" sublabel flagged `warning` past
      `STALE_THRESHOLD_SECONDS` (mirrors `SensorAlertCollector`'s own
      24h offline threshold), linking to the new readings page.
- [x] New `entity.sensor_device.readings` page
      (`SensorDeviceController::readings()`,
      `/hivelog/sensor-device/{sensor_device}/readings`) — every metric
      the device reports, full history by default, filterable via the
      new GET-based `SensorReadingFilterForm` (metric select + date_from
      + date_to, mirroring `HivelogInspectionFilterForm`'s established
      shape exactly). Same access requirement as the canonical page.
- [x] `SensorPanelBuilder`'s trend-chart code (aggregate raw + rollup
      readings, render the inline SVG) extracted into a new
      `SensorTrendChartBuilder` service, parameterised by an explicit
      date range instead of "trailing N days" — reused by both the
      panel's own 30-day chart and the new full-history page, rather
      than duplicating ~150 lines of charting logic.
- [x] Kernel tests: `HivelogStatTileBuilder`'s own merge/sort/empty
      behaviour; both submodules' hooks dispatch and produce the right
      tile shape; the readings page's metric/date-range filtering,
      access control, and route/title; a malformed filter value falls
      back to the default rather than erroring.
- [x] **Follow-up**: `DashboardAiInsightsBuilder::build()` (the
      dashboard's "AI Insights" section) rebuilt around one
      `hivelog:stat-tile` per hive with a current insight — act_now/
      inspect_soon *and* all_clear alike, not just the actionable ones
      — each linking straight to that hive, sorted act_now →
      inspect_soon → all_clear. A stale `all_clear` still gets no tile
      (an out-of-date "nothing's wrong" must never read as current
      reassurance — unchanged from before); a stale act_now/
      inspect_soon now *does* still get one, since old actionable
      information is still actionable, and this section otherwise had
      no way to represent it if it fell out of a "24h window."
- [x] **Follow-up**: the old "N hives all clear today" aggregate line
      is gone. When there are zero insights of any kind to show, the
      section now says so explicitly ("No AI insights yet — insights
      are generated on cron for each hive whose apiary has opted in.")
      instead of leaving the reader to infer meaning from an absent or
      zero-valued count — the exact ambiguity the user reported.
- [x] **Follow-up, three iterations**: `.hivelog-stat-tiles`' layout
      went through CSS Grid `auto-fit` → CSS Grid `auto-fill` →
      flexbox-with-per-tile-cards before landing on something that
      actually satisfies both "tile width consistent everywhere" and
      "no dead space" at once:
      1. Original (`auto-fit`): a row with only 1-2 tiles (a quiet AI
         Insights section, a Hive page with one sensor) stretched them
         to fill the whole row — visibly wider than the same tiles in a
         fuller row.
      2. `auto-fill`: fixed the width-consistency problem, but reserves
         empty grid tracks for the row's *full* width regardless of
         how many tiles exist — user-reported follow-up: "a lot of
         empty space to the right of the tiles."
      3. Tried flexbox with `width: fit-content` on the wrapping row
         (so the shared bordered box would shrink to fit its actual
         tiles) — empirically verified broken: browsers don't have a
         well-defined sizing algorithm for `fit-content` on a
         `flex-wrap` container, and it produced a degenerate one-tile-
         per-line layout, confirmed with a standalone test page (not
         guessed from the spec).
      4. Moved the border/background from the shared row onto each
         `.hivelog-stat-tile` individually, so the row itself never
         needs a width of its own — a plain 100%-wide `flex-wrap` row
         wraps predictably at any tile count, every tile is the same
         size everywhere, and a partial row just shows the page's own
         background to the right, not a highlighted empty box. Verified
         with a standalone test page: 1/6/10 tiles all render at a
         consistent width, and 10 tiles in a wide container correctly
         wrap 6+4 across two rows.
      5. **Final tuning, user-verified live**: with no `flex-grow`, a
         tile never stretches past its own basis — correct for keeping
         a lone tile from ballooning, but it means the basis itself has
         to be wide enough that a genuinely full row (the dashboard's
         own 6 tiles) actually reaches the edge of the available width.
         `9rem` didn't — real screen, visible gap after the 6th tile.
         `15rem` does.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Real bug caught only by actually rendering the page in a browser**:
  `SensorReadingFilterForm`'s constructor originally used PHP 8
  constructor property promotion (`protected RequestStack $requestStack`)
  — `FormBase` (like `ControllerBase`) already declares `$requestStack`
  untyped, and PHP forbids redeclaring an inherited property with a
  type. Fatal error on every page load. Fixed by assigning
  `$this->requestStack = $request_stack;` in a plain constructor
  instead, the exact pattern `HivelogInspectionFilterForm` already used
  for this same reason (documented in that class's own precedent, just
  not followed here at first).
- **Filter value validation**: `date_from`/`date_to` are plain `Y-m-d`
  strings from a GET query string, not a real `date` field's own
  validated value — `extractValidDate()` regex + `checkdate()`-validates
  before ever reaching `strtotime()`, so a hand-crafted malformed query
  value falls back to the default range instead of producing a bogus
  timestamp or PHP warning.
- **Verified live against `cms2`**: the stat-tile row renders correctly
  on the Assimilate demo hive (AI Insight "Act now" + two sensor tiles),
  every link resolves to the right anchor/route (confirmed via `href`
  inspection — the browser pane's JS/CSS sandbox blocks real visual
  rendering, same known limitation as tasks 0105/0106), the readings
  page's metric and date-range filters both work (confirmed a real
  chart renders once a second calendar day of data and a `date_from`
  covering it both exist), and the moved `.hivelog-stat-tiles` CSS is
  served correctly by the real web server (confirmed via `curl` against
  the live asset path, since the browser pane can't be trusted for
  visual CSS verification either). The dashboard follow-up was verified
  the same way: `/hivelog`'s "AI Insights" section now shows a single
  tile ("Act now — Assimilate Demo Hive — Complete autumn feeding
  immediately…") linking to `/hivelog/hive/22`, replacing the old
  ambiguous "0 hives all clear today" line entirely.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0099-submodule-canonical-page-panel-hook]]
- Tasks:: [[0105-submodule-navigation-menu-links]] (the
  descriptor-based hook pattern this mirrors),
  [[0107-assimilate-mock-sensor-data-module]] (the demo data this was
  verified against)
- Commits::
