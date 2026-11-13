---
type: task
tags: [hivelog/task]
status: review
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
- [x] Header strip: Hive (linked), Date, Inspector
- [x] Under the existing H2s, tiles replace tables: Queen status (queen seen, cells, eggs), Brood and stores (brood pattern, honey, pollen), Colony condition (temperament, population), Varroa (count), Management (weight, fed, supers)
- [x] External check, Action taken and Notes stay as prose (Disease signs became a tile, since it is an enum with a severity)
- [x] Severity sub-lines for poor/critical values: agree the mapping (e.g. poor brood pattern, high varroa count) and record it in the task or an ADR before coding; none by default where there is no clear threshold
- [x] Heading hierarchy unchanged (H2 sections; WCAG 1.3.1)
- [x] Photos grid and delete-block behaviour unchanged
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/Controller/HiveInspectionController.php`
- Several fields are optional, so empty tiles should be omitted, not shown as "—", to keep the strips compact. Settle that rule in task 0256 and apply it here.

## Severity mapping (decided and implemented)
| Field | Value | Sub-line | Variant |
|---|---|---|---|
| Honey stores | low | Low stores | warning |
| Honey stores | none | No stores | critical |
| Brood pattern | poor | Poor brood | warning |
| Population | weak | Weak colony | warning |
| Disease signs | none | Clear | ok |
| Disease signs | nosema, chalkbrood, sacbrood, other | Disease sign | warning |
| Disease signs | AFB, EFB | Serious disease | critical |

No tint for varroa count (the counting method and season are not recorded, so no honest threshold), queen cells, temperament, pollen stores, or "none" brood (normal in winter). The queen page's Health tile (0258) is a different field and stays untinted.

## Notes on the build
- New `HivelogDetailPageTrait::buildTileSection()` (H2 + strip, empty when no tile has a value), reusable by 0260 and 0262.
- Booleans are stored as "No" when never ticked, so an old inspection shows Queen seen/cells/eggs as "No"; that is what is stored.
- Phone-width visual check outstanding (the in-app browser would not load the site stylesheets); markup checked on hivelog.ddev.site inspection 1.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
