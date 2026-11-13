---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Queen: one strip of Status (ok variant when active), Hive (linked), Year · Colour, Breed, Temperament, Introduced (sub-line "n months in hive"), Purchase cost; Origin and Notes stay as text
- [ ] Observation: one strip of Queen (linked), Date, Health, Temperament, Actively laying (yes/no); Notes and photos unchanged
- [ ] Unassigned queen (no hive) renders the Hive tile as "Unassigned" without error
- [ ] Observations list at the end of the queen page unchanged
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/QueenController.php`, `src/Controller/QueenObservationController.php`
- Health could take a warning/critical sub-line for poor values; keep it to the same mapping the inspection page uses (decide once in task 0259 or here).

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
