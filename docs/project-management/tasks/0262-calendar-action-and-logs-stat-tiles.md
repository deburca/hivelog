---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/calendar-action-and-logs-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Calendar action and action log pages as tile strips

## Context
The calendar action page shows Overview (apiary, title, category, enabled, scope), Schedule (week start/end, recurring) and Description, then the requirements and yields tables. The hive and apiary action log pages show Overview (parent, action, year, status) and Details (week, notes, inspection, user). These are small records whose facts fit one strip each.

## Acceptance criteria
- [x] Calendar action: Category, Scope, Window ("W14–W18", sub-line "Recurring"), Status (enabled/disabled, warning when disabled); Description, requirements and yields unchanged
- [x] Hive/apiary action log: Status (done ok, ignored default, pending warning), Year · Week, Action (linked), Inspection (linked, hive logs only); Notes stay as text
- [x] Apiary logs have no inspection tile
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/CalendarActionController.php`, `HiveActionLogController.php`, `ApiaryActionLogController.php`

- Added `ActionPagesStatTilesTest`. Status on a log: done ok, pending warning ("Not reported"), ignored plain. A disabled action reads "Disabled" with a warning "Not scheduled" sub-line.
- The parent (apiary or hive) and the title are not tiles: the breadcrumb and the page title name them. Notes and "uid" stay in a Details table.
- Phone-width check not done (the browser pane cannot load the site stylesheets).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
