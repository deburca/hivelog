---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-22
completed: 2026-09-22
branch: feature/0111-hive-page-declutter-dedicated-insights-page
release:
depends-on: ["[[0110-hive-apiary-stat-tiles]]"]
blocked-by:
---
# Task: Declutter the Hive canonical page — move AI Insight/Sensors panels to a dedicated Insights page

## Context
User request, same day as [[0110-hive-apiary-stat-tiles]]: `/hivelog/hive/{hive}`
had become too busy to see what's actually important — the full "AI
Insight" panel (recommendation, signals, confidence) and the full
"Sensors" panel (a 30-day trend chart per device/metric) had grown real
content once 0110's stat tiles gave a compact preview of the same
information. User's own breakdown, verbatim: AI Insight tiles OK,
`nexus-hive-insight-panel` → move to a dedicated page, weight
histogram OK, `nanoprobe-sensors-panel` → move to a dedicated page,
Queen/Hive Activity/Inspections/Observations/Seasonal Calendar OK.

Scoped to the **Hive** canonical page only, per the literal request —
the Apiary canonical page's own copies of these two panels are
unchanged; it hasn't grown the same "too busy" problem (yet).

## Acceptance criteria
- [x] New core hook `hook_hivelog_hive_insights_panels()`
      (`hivelog.api.php`) — same contract as the existing
      `hook_hivelog_hive_view_panels()` (access is the implementation's
      own responsibility; unique module-prefixed keys; each panel sets
      its own `#weight`), just a second, separate injection point for a
      different page, per ADR-0099.
- [x] New route `entity.hive.insights`
      (`/hivelog/hive/{hive}/insights`, `HiveController::insights()`),
      same access requirement as the canonical page — this is just a
      deeper view of a page the user can already see.
- [x] `nexus`/`nanoprobe` each moved their *hive-scoped* panel
      contribution from `hivelog_hive_view_panels()` to
      `hivelog_hive_insights_panels()` — the panel-building logic itself
      (`HiveInsightPanelBuilder::buildHivePanel()`,
      `SensorPanelBuilder::buildHivePanel()`) is unchanged, only which
      page invokes it. Their *apiary-scoped* implementations
      (`hivelog_apiary_view_panels()`) are untouched.
- [x] `HiveInsightPanelBuilder::buildHiveStatTile()`'s tile now links to
      `entity.hive.insights#nexus-hive-insight` instead of an anchor on
      the same page (`entity.hive.canonical#nexus-hive-insight`) — the
      panel it points at moved. `buildApiaryStatTile()` is unchanged
      (still anchors the same Apiary page, since that panel didn't
      move). Sensor tiles are unaffected — they already linked to each
      device's own full-history page (`entity.sensor_device.readings`,
      task 0110), a different page from the one that just lost its
      inline Sensors panel.
- [x] Breadcrumb: `entity.hive.insights` gets a distinct "Insights"
      terminal crumb after the hive link
      (`Home › HiveLog › <Apiary> › <Hive> › Insights`), mirroring
      `$apiary_page_crumbs`' existing pattern for named apiary sub-pages
      — there was no hive-page equivalent map yet, so one was added.
- [x] Kernel tests: the new hook dispatches independent of any
      implementer (core); both submodules' hive-scoped panels dispatch
      via the new hook and their apiary-scoped ones still dispatch via
      the old one (regression coverage for the split, not just the
      move); the stat tile's updated URL; the breadcrumb's new terminal
      crumb.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- Reused the exact ADR-0099 mechanism this project already leans on
  repeatedly (0092/0093/0105/0106/0110) rather than inventing a new
  "how does hivelog core learn what submodules want to show" pattern —
  a second hook for a second page is a smaller, more consistent change
  than teaching the existing hook about "which page is this for."
- `HiveController::insights()` deliberately has no fallback/empty-state
  markup when no submodule contributes anything (e.g. neither nexus nor
  nanoprobe installed) — matches `hivelog_hive_view_panels()`'s own
  established "an optional panel that isn't there contributes nothing,
  not a placeholder" convention.
- Verified live against `cms2`: the Assimilate Demo Hive's canonical
  page now shows only the stat tiles, weight histogram, Queen, Hive
  Activity, and Seasonal Calendar — the full AI Insight and Sensors
  panels are gone from it entirely. The AI Insight tile correctly links
  to `/hivelog/hive/22/insights#nexus-hive-insight`; that page shows
  both full panels together, titled "Insights: Assimilate Demo Hive",
  with the breadcrumb correctly ending "… › Assimilate Demo Hive ›
  Insights" (confirmed hivelog's own breadcrumb builder, not the
  `easy_breadcrumb` module also installed on this site, wins per its
  documented priority-1004 requirement). The Apiary canonical page's
  own AI Insight/Sensors panels are untouched, confirmed unchanged.
- **Addendum 1** (same day, follow-up user request): once the Sensors
  panel had a full page to itself here, the per-device metric list —
  previously stacked `<p>` summary lines — was still hard to scan.
  `SensorPanelBuilder::buildDeviceSection()` briefly rendered a proper
  `<table>` (`hivelog-sensor-reading-table`, header Metric/Value/
  Updated) in place of `formatLatestReadingSummary()` (removed), styled
  via the shared `hivelog.tables.css` convention — superseded same-day
  by Addendum 2 below.
- **Addendum 2** (same day, second follow-up): with both the table and
  its separate list of trend charts on the page, the split itself
  became the legibility problem — "the number" and "the chart for that
  number" were in two different places. Replaced both with a single
  new SDC component, `nanoprobe:metric-tabs`
  (`modules/nanoprobe/components/metric-tabs/`): a vertical tab strip,
  one tab per metric, whose content area shows that metric's current
  value, trend chart, and last-updated time together.
  `hivelog-sensor-reading-table` and its `hivelog.tables.css` rules
  were removed again (dead as of this change — nothing renders that
  class any more).
  - CSS-only, no JS anywhere in HiveLog: selection is driven by
    radio inputs. The tab list and content area are two independent
    flex columns (not a shared grid row, which was tried first and
    rejected — pairing each tab's row height to its own panel's
    height, most content is much taller than a label, left the tab
    list full of dead space). `:has()` connects the Nth checked radio
    to the Nth content panel *by position*, not by id, so one generic
    CSS rule set (10 deep, comfortably above
    `SensorReading::METRIC_TYPES`'s real max of 7) serves every
    instance on a page — each device gets its own instance,
    disambiguated by `group` (the device id) as the radio `name`.
    Below 768px the tab strip becomes a horizontal wrapping row of
    pills above the content, matching the app-nav strip's own
    small-screen treatment (task 0105).
  - `SensorPanelBuilder` gained a `RendererInterface` dependency
    (`renderInIsolation()`) to pre-render each metric's trend chart to
    an HTML string before handing it to the component as a prop —
    same "pre-render nested content to a string, don't pass a raw
    render array into an SDC prop" convention the list builders'
    `ops_html` already use.
  - **Follow-up fix, same day**: the first live check missed that the
    chart was rendering with default black fill/stroke instead of its
    intended orange band/line — `renderInIsolation()` deliberately
    discards the bubbleable metadata (including `#attached` libraries)
    of whatever it renders, since it's meant for content rendered
    *outside* the current render process; the chart's own
    `#attached => ['library' => ['nanoprobe/sensor_trend']]` was
    therefore silently dropped, so its CSS never loaded on the page
    and the inline SVG fell back to browser defaults. Fixed by
    attaching `nanoprobe/sensor_trend` explicitly on
    `buildDeviceSection()`'s own returned container instead (which
    renders a chart's markup whenever it renders at all). Verified via
    the page's aggregated CSS bundle (fetched with its full query
    string — Drupal's CSS aggregation needs the `include=` param to
    match; the plain path alone 404s/returns the wrong bundle):
    confirmed `.nanoprobe-sensor-trend__band`/`__avg` now ship with
    their `#f2a42e`/`#d98e1a` colours.
  - Verified live against `cms2` (`/hivelog/hive/22/insights`): both
    devices render as tab strips (3 and 5 metrics respectively), each
    device's radios are independently namespaced by device id so the
    two groups don't collide, switching tabs shows the right value/
    chart/updated-time together, and a metric with only one day of
    data shows its value plus an explicit "Not enough data yet for a
    trend chart" message instead of an empty gap. The two-column vs.
    mobile-accordion CSS behaviour was verified independently first,
    against a standalone static test page (this sandboxed browser's
    `getComputedStyle()`/module-CSS access is unreliable — see prior
    session note — a local `python3 -m http.server` + fresh tab is the
    reliable way to check real layout behaviour).
  - phpcs clean; full hivelog kernel + unit suite (782 tests) re-run
    against `cms2`, no regressions from this change (two pre-existing,
    unrelated failures — `QueenTest::testCreateQueen` and
    `ApiaryCalendarChecklistTest::testFullCalendarFiltersNarrowResults`
    — were already failing before this change and are tracked
    separately, not part of this task).

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0099-submodule-canonical-page-panel-hook]]
- Tasks:: [[0110-hive-apiary-stat-tiles]] (the stat tiles that made
  the full inline panels redundant enough to move)
- Commits::
