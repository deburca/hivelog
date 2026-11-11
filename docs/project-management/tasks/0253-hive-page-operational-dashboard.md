---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-10-30
branch: feat/hive-page-restructure
release: 4.2.0
depends-on: ["[[0251-inspection-list-grouping]]"]
blocked-by: []
---
# Task: Canonical hive page — restructure as operational dashboard

## Context
The canonical hive page (`/hivelog/hive/{hive}`) is where beekeepers spend the most
time during a visit. It currently renders as a **form-like detail page**: a sequence of
label/value tables (Queen status, Hive details, Health, External check…) followed by
Hive Activity sub-sections (Inspections and Queen Observations). This reads like a
data-entry form rather than an operational overview.

Beekeepers need to know:
1. Is the hive okay right now? (severity, last inspection date)
2. What changed since the last visit? (weight trend, queen status)
3. What should I do today? (seasonal actions due, alerts)
4. How has this hive performed over time? (historical weight/harvest)

The current page doesn't answer these in priority order.

### Current structure (as rendered — `HiveController::view()`)
```php
$build['stat_tiles']         // 'at a glance' tiles from submodules (#weight -1)
  $build['hive']              // entity field rendering via getViewBuilder('hive') (#weight 5)
  $build['components']        // Hive Components section + computed empty weight (#weight 6)
  $build['weight_histogram']  // Inspection weights for current year chart (#weight 7)
  $build['queen']             // Queen section (details table or "No active queen") (#weight 8)
  $build['hive_activity_heading'] // <h2> Hive Activity (#weight 9)
  $build['hive_activity']     // [inspections column + observations column] (#weight 10)
  $build['images']            // Pictures grid (only if hive has photos) (#weight 20)
  $build['calendar_heading']  // <h2> Seasonal Calendar (#weight 25)
  $build['calendar_filter']   // Filter form for checklist (#weight 26)
  $build['calendar_table']    // Checklist table via hivelog:entity-table (#weight 27)
```

The hive entity fields (`$build['hive']`) are rendered by core's default view builder — no custom tables. The module has **no exported config** for field display; everything is defined in `baseFieldDefinitions()`. The current page reads like a sequence of sections without visual hierarchy: the first thing after Edit/Delete is raw entity fields (Queen, Type, Material, Installed date… etc.), followed by Hive Components, then Weight Histogram, then Queen details, then Hive Activity.

## Desired configuration

### Top section: at-a-glance severity banner + key stats
- **Severity banner** at the very top (full-width, left severity stripe):
  - Green border + "All clear — last inspection X days ago" when no alerts
  - Amber/red border + "Inspect soon — due 3 days ago" / "Check now — queen missing" 
  - The banner disappears if there are no current alerts (not shown on every page)
- **Key stat tiles** immediately below the banner (4 columns on desktop, 2 on tablet,
  1 on phone):
  - Latest weight (from sensor or last scale reading), with trend arrow (↑↓→)
  - Net colony weight (weight minus tare) with severity color
  - Days since last inspection + "due" status for next scheduled action
  - Active queen name/status

### Second section: condensed hive identity row
- The entity fields ($build['hive']) currently render as raw Drupal form-style field rows.
  Replace them with a **single condensed identity row** that surfaces the four most actionable
  fields side by side: Queen, Type/Material, Installed date, Status (active/inactive).
  This is not a detail table — it's a flat summary strip that fits on one line where space allows,
  or wraps to two lines on a phone.
- The "Edit hive" link stays visible but doesn't dominate the row.

### Third section: Hive Components (unchanged)
- This section stays as-is — it already has a clear heading table format with the computed
  Empty Weight line below. No rework needed.

### Fourth section: weight histogram (visual update only per task 0252)
- The chart itself is unchanged structurally; severity encoding and axis labels come from
  task 0252. Position stays at weight 7 (between components and queen).

### Fifth section: Hive Activity (structural reorganization from current)
- Currently the page has: `$build['queen']` at weight 8 — an isolated Queen section with its
  own heading, details table or "No active queen" text — **before** the Hive Activity block.
  This splits Queen context away from the activity that mentions queens.
- Reorder: move the Queen section *into* the Hive Activity area as a header row above the
  two tables, so it reads: "Hive Activity → Active queen is VV-01 (Calm, Italian) [Edit Queen] | [View all Queens]"
  followed by the Inspections and Queen Observations columns.
- The calendar checklist (`$build['calendar_table']`) also stays inside this area since it's
  operational context for the hive, not a separate "page" unto itself.

## Visual design notes
- Sections are separated by **hairline rules** (`--hivelog-hairline`), not card backgrounds.
  The page reads as a single document, not a collection of cards (the dashboard uses cards
  because it mixes heterogeneous content; the hive page has one narrative).
- **No card backgrounds on the canonical page** — this is a deliberate design choice. Cards
  fragment what should be a continuous overview. The severity banner and stat tiles are the
  only elements with filled backgrounds.
- Section headings use H2 level (per AGENTS.md heading hierarchy). No nested H3 inside H2
  except where Hive Activity has Inspections and Queen Observations as subsections (H3 under
  the H2 "Hive Activity").

## Implementation notes
- Key files: `src/Controller/HiveController.php`, `components/stat-tile/stat-tile.twig`,
  `css/hivelog.dashboard.css` (stat tile grid), `css/hivelog.buttons.css` (severity banner)
- The severity banner reuses the same CSS class structure as the dashboard's "Needs attention"
  rows: `.hivelog-severity-banner` with modifier classes for color.
- The condensed hive identity row uses a single-table layout with side-by-side key/value pairs where space allows,
  not a separate Twig template. It replaces `$build['hive']` in `HiveController::view()`.
- The operational context section pulls from `HivelogAlertCollector` and the calendar copier
  to show what's due this week/month for this specific hive.

## Acceptance criteria
- [ ] Severity banner at top of page when alerts exist; hidden when all clear
- [ ] Four stat tiles at top: weight, net colony weight, days since inspection, queen status
- [ ] Stat tiles render 4/2/1 columns at the three breakpoint tiers
- [ ] "What to do today" operational section with actionable cards linked to forms
- [ ] Condensed hive identity row (entity fields replaced with single-line summary)
- [ ] Queen section moved into Hive Activity area as a header row above the tables
- [ ] Weight histogram updated per task 0252
- [ ] Hive Activity reorganized: Queen header → Inspections/Queen Observations
- [ ] Calendar checklist stays under "Hive Activity" area
- [ ] Per-hive inventory summary: consumable stock + harvest totals
- [ ] Sections separated by hairline rules, no card backgrounds on the body of the page
- [ ] Heading hierarchy correct (H2 sections, H3 subsections under "Hive Activity")
- [ ] Responsive at 390px, 768px, 1280px
- [ ] Tests: `HiveControllerTest` assertions for section presence and rendering

## Related
- Project:: [[hive-log-frontend-polish]]
- Follows:: [[0251-inspection-list-grouping]], [[0250-beeswax-theme-warm-palette]]
