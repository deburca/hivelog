---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-10-30
branch: feat/sensor-charts-improved
release: 4.2.0
depends-on: ["[[0250-beeswax-theme-warm-palette]]"]
blocked-by: []
---
# Task: Weight histograms and sensor charts — improve data visualization

## Context
HiveLog's two data-visualization surfaces are the **weight histogram** on the hive page
(`hivelog.weight-histogram.css`) and the **sensor trend charts** in the nanoprobe submodule
(nanoprobe sensor readings, daily rollups, net colony weight). Both are currently minimal:
the histogram is a bare CSS bar chart with no axis labels beyond the default; the sensor
charts are functional but lack visual hierarchy that helps beekeepers spot trends and
anomalies at a glance.

### Current state — weight histogram
- Bars represent individual scale readings over time
- X-axis shows dates, y-axis shows weights in kg
- Bars use the module's default blue (`#2563eb`) — no severity encoding
- No axis labels beyond tick values; no reference line for empty/hive weight
- Line thickness is thin (~1px); bars are narrow with gap between them

### Current state — sensor trend charts (nanoprobe)
- Temperature, humidity, and weight trends shown in separate tabs
- Basic CSS chart rendering (not a JS canvas/SVG chart)
- No visual distinction between baseline and current readings
- No ability to overlay expected ranges or thresholds

## Desired configuration

### Weight histogram improvements
1. **Severity-encoded bars** — color bars based on colony weight status:
   - Green (#7CB342 / beeswax accent): healthy weight range
   - Amber (#D4A843): below seasonal norm (warning)
   - Red (#D32F2F): critical low weight
   - Gray (#9ca3af): pre-deployment readings (before hive established)
   The severity thresholds come from a configurable service or the beeswax theme's
   token values for the active season.

2. **Empty weight reference line** — a dashed vertical marker at the hive's tare
   weight (`Hive::getEmptyWeightKg()`), so beekeepers see the colony weight relative
   to empty. The line is drawn as a CSS pseudo-element on the chart container:
   `.weight-histogram__line--empty-weight { border-left: 1px dashed var(--hivelog-warning); }`.

3. **Axis labels** — add a y-axis label ("Weight (kg)") and x-axis label ("Date") as
   screen-reader-visible text, with visible axis titles on desktop (>768px):
   - Y-axis label rotated 270°, left of the chart
   - X-axis label below the date tick marks

4. **Bar width and spacing** — wider bars (6–8px) with tighter gaps for readability;
   on phones the bars become full-width segments with no gap between them.

5. **Hover/focus state** — desktop hover shows tooltip with exact weight and date;
   keyboard focus highlights the bar and reads the value via `aria-label`.

### Sensor trend chart improvements
1. **Baseline overlay** — each sensor chart shows a faint "baseline" band (min/max
   from historical data) as a filled area behind the current reading, so anomalies
   are obvious without cross-referencing a number.

2. **Net colony weight emphasis** — the net weight tile (latest weight minus tare) is
   the most actionable sensor metric. It gets its own chart at the top of the sensor
   panel with:
   - Thicker line (3px vs 1px baseline)
   - Color-coded based on status (green/amber/red like the histogram)
   - A subtle pulse animation on page load to draw attention

3. **Multi-sensor comparison** — when both temperature and humidity sensors are present,
   they render as dual-axis lines on one chart:
   - Temperature: solid line, warm color
   - Humidity: dashed line, cool color
   - Shared y-axis (percentage for humidity); temperature mapped to a secondary axis

4. **Data density** — sensor readings are averaged into 6-hour bins for charts that span
   more than 24 hours (retention window is ~30 days), so the chart doesn't become
   unreadable at high frequency. The daily rollups (`SensorReadingDaily`) provide the
   binning data.

## Implementation notes
- Key files: `css/hivelog.weight-histogram.css`, `modules/nanoprobe/css/nanoprobe.sensor-trend.css`,
  `modules/nanoprobe/src/Controller/SensorController.php` (chart rendering)
- Severity colors come from the active beeswax palette via CSS custom properties:
  `var(--hivelog-chart-healthy)`, `var(--hivelog-chart-warning)`, etc. — these are
  beekeeping-specific semantic tokens, not module-wide severity tokens.
- The empty-weight reference line is a CSS pseudo-element; no SVG/JS needed for this
  surface because it's data from the PHP controller baked into the template as a data attribute.
- The pulse animation on load uses `@keyframes` with `prefers-reduced-motion: reduce`
  to disable (per AGENTS.md design principles).

## Acceptance criteria
- [ ] Weight histogram bars are severity-encoded (green/amber/red/gray) based on colony weight status
- [ ] Empty weight reference line shown as dashed marker with label
- [ ] Y-axis ("Weight (kg)") and x-axis ("Date") labels visible on desktop, hidden on phone
- [ ] Bar width 6–8px on desktop; full-width segments on phone with no gap
- [ ] Hover/focus state shows tooltip with weight and date (desktop) or aria-label (mobile)
- [ ] Sensor charts show baseline band as filled area behind current reading
- [ ] Net colony weight has its own emphasized chart at the top of sensor panel
- [ ] Multi-sensor overlay: temperature (solid warm) + humidity (dashed cool) on shared chart
- [ ] Data binning to 6-hour intervals for charts spanning >24 hours
- [ ] `prefers-reduced-motion` respected for all animations
- [ ] Visual review with real sensor data on hivelog.ddev.site

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]], [[0106-hive-component-weight-tracking]]
- Follows:: [[0250-beeswax-theme-warm-palette]]
