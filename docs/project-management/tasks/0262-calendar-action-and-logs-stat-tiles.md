---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Calendar action: Category, Scope, Window ("W14–W18", sub-line "Recurring"), Status (enabled/disabled, warning when disabled); Description, requirements and yields unchanged
- [ ] Hive/apiary action log: Status (done ok, ignored default, pending warning), Year · Week, Action (linked), Inspection (linked, hive logs only); Notes stay as text
- [ ] Apiary logs have no inspection tile
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/CalendarActionController.php`, `HiveActionLogController.php`, `ApiaryActionLogController.php`

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
