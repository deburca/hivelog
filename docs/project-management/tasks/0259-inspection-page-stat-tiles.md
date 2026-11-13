---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/inspection-page-stat-tiles
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Inspection page: grouped stat tiles

## Context
The inspection page has eight sections (Overview, External check, Queen status, Brood/honey/pollen, Colony condition, Varroa and disease, Management, Notes), each its own table, most with one to three short fields. It is a long page of small tables for a record a beekeeper wants to read at a glance.

## Acceptance criteria
- [ ] Header strip: Hive (linked), Date, Inspector
- [ ] Under the existing H2s, tiles replace tables: Queen status (queen seen, cells, eggs), Brood and stores (brood pattern, honey, pollen), Colony condition (temperament, population), Varroa (count), Management (weight, fed, supers)
- [ ] External check, Disease signs, Action taken and Notes stay as prose
- [ ] Severity sub-lines for poor/critical values: agree the mapping (e.g. poor brood pattern, high varroa count) and record it in the task or an ADR before coding; none by default where there is no clear threshold
- [ ] Heading hierarchy unchanged (H2 sections; WCAG 1.3.1)
- [ ] Photos grid and delete-block behaviour unchanged
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/HiveInspectionController.php`
- Several fields are optional, so empty tiles should be omitted, not shown as "—", to keep the strips compact. Settle that rule in task 0256 and apply it here.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
