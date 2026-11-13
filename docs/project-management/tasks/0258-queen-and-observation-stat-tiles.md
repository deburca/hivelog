---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/queen-and-observation-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Queen and queen observation pages as tile strips

## Context
The queen page stacks four label/value tables (Overview, Identity, Acquisition, Notes) of two to five rows each before the observations list. The queen observation page does the same with Overview, Observations and Notes. Almost every value is short and enumerated, so these pages are the clearest space win in the survey.

## Acceptance criteria
- [x] Queen: one strip of Status, Hive (linked), Year · Colour, Breed, Temperament, Introduced (sub-line "n months in hive"), Purchase cost; Origin and Notes stay as text
- [x] Observation: one strip of Queen (linked), Date, Health, Temperament, Actively laying (yes/no); Notes and photos unchanged
- [x] Unassigned queen (no hive) renders the Hive tile as "Unassigned" without error
- [x] Observations list at the end of the queen page unchanged
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/QueenController.php`, `src/Controller/QueenObservationController.php`
- Health could take a warning/critical sub-line for poor values; keep it to the same mapping the inspection page uses (decide once in task 0259 or here).

- Deviations: the Status tile has no `ok` variant (the variant tints a sub-line, and an "Active" tile has nothing true to say beneath it, so a made-up sub-line would be noise); the queen's origin and notes are a "Details" table, hidden when both are empty; "Created by" (uid) and the purchase date as its own row are gone (the date is the Purchase cost tile's sub-line). The queen's name is the page title, so no tile repeats it.
- Health has no warning/critical sub-line here; the mapping is still to be decided with the inspection page (0259).
- `QueenController` gained a `datetime.time` constructor argument for the months-in-hive line.
- Phone-width visual check outstanding (the in-app browser would not load the site stylesheets); the queen page's markup was checked on hivelog.ddev.site.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
