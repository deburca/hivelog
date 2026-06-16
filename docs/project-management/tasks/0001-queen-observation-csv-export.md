---
type: task
tags: [hivelog/task]
status: in-progress
priority: high
project: "[[queen-observation-enhancements]]"
area: routing
created: 2026-06-16
branch: feature/0001-queen-observation-csv-export
release: v1.1.0
---
# Task: CSV export of a queen's observations

## Context
Beekeepers want a portable record of a queen's history. The
`QueenObservation` entity already captures `observation_date`, `health`,
`temperament`, `active`, and `notes`; we just need to stream them out as CSV.
Part of [[queen-observation-enhancements]].

## Acceptance criteria
- [ ] New route `hivelog.queen.observations_csv` at
      `/hive/{hive}/queen/{queen}/observations.csv`.
- [ ] Controller method returns a `StreamedResponse` with
      `text/csv` + `Content-Disposition: attachment`.
- [ ] Columns: date, health, temperament, active, notes.
- [ ] Access mirrors the queen canonical route
      (`_permission: 'view hivelog+administer hivelog'`).
- [ ] **Export** button added next to **Add Observation** on the hive page.
- [ ] Kernel test in `tests/src/Kernel/` (`@group hivelog`) asserting row count
      and header.

## Implementation notes
- Key files:
  - `hivelog.routing.yml` — add the CSV route.
  - `src/Controller/QueenController.php` — add `observationsCsv()`.
  - `src/Controller/HiveController.php` — add the button to the render array.
- No entity schema change → **no update hook required**.
- Reverse-lookup pattern: load observations via
  `queen` reference, newest first (same ordering as the canonical page list).

## Related
- Project:: [[queen-observation-enhancements]]
- Decisions:: [[0002-no-geocoder-dependency]] (no new contrib lib — use core
  `StreamedResponse`, don't pull in a CSV package).
- Commits:: _(add hashes as you go)_
