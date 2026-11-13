---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/prose-block-detail-pages
release:
depends-on: ["[[0256-stat-tile-strip-helper]]", "[[0265-prose-note-block]]"]
blocked-by: []
---
# Task: Apply the prose note block to the detail pages

## Context
Task 0265 adds a shared prose note block. These four pages still show free text as a one-row table. The inspection page also has a question of order: **External check (before opening)** is a free-text field (`string_long`, no length limit) describing what the beekeeper sees at the hive entrance before opening it, so it is prose, not a tile; but because it happens first in a visit it should sit first on the page. Decision (2026-11-13, user): separate blocks in the order of the visit.

## Acceptance criteria
- [x] Inspection page (`HiveInspectionController`): **External check** block directly after the header strip (Hive, Date, Inspector) and before the tile sections; **Action taken** then **Notes** after the tile sections, before the photos. Each shown only when written. The H2 for the first is "External check" (drop "(before opening)" from the heading and say it in the block's lead-in or keep the field label; pick one and apply it the same on the form and page)
- [x] Queen observation page (`QueenObservationController`): Notes as a prose block
- [x] Queen page (`QueenController`): Notes as a prose block; the "Details" table goes (Origin becomes a tile in task 0267)
- [x] Calendar action page (`CalendarActionController`): Description as a prose block, bullets kept (`SimpleBulletText`); the requirements and yields tables are unchanged
- [x] No page keeps a label/value table whose only row is free text
- [x] Tests updated for the moved sections (existing tests assert `details`, `external_check`, `action_taken`, `notes` and `description` keys; keep the keys or update the assertions)
- [x] AGENTS.md "Stat-tile strips" section: remove the sentences saying External check, Action taken and Notes stay tables
- [ ] Phone width (≤480px) checked on each of the four pages

## Implementation notes
- Key files: `src/Controller/HiveInspectionController.php` (the `$prose` loop near the end of `view()`), `QueenObservationController.php`, `QueenController.php` (`$build['details']`), `CalendarActionController.php` (`description` section).
- Cache metadata: the blocks depend on the entity already added by each `view()`; nothing new.

- Inspection heading for External check is the field's own label, "External check (before opening)", so page and form agree with no change to the form. Order is header strip, External check, tile sections, Action taken, Notes, photos.
- Queen: Notes is a note block; **Origin stays in a one-row Details table** until task 0267 makes it a tile, so the page does not lose it between the two tasks. 0267 removes that table.
- Calendar action: the Description section is now left out when empty (it used to show a dash).
- Phone width not checked (the browser pane cannot load the site stylesheets).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
