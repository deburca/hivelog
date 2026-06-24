---
type: task
tags: [hivelog/task]
status: in-progress
priority: high
project: "[[queen-observation-enhancements]]"
area: routing
created: 2026-06-16
branch: feature/0001-queen-observation-csv-export
release:
---
# Task: CSV export of a queen's observations

## Context
Beekeepers want a portable record of a queen's history. The
`QueenObservation` entity already captures `observation_date`, `health`,
`temperament`, `active`, and `notes`; we just need to stream them out as CSV.
Part of [[queen-observation-enhancements]].

## Acceptance criteria
- [ ] New route `hivelog.queen.observations_csv` at
      `/hivelog/queen/{queen}/observations.csv`.
- [ ] Controller method returns a `StreamedResponse` with
      `text/csv` + `Content-Disposition: attachment`.
- [ ] Columns: date, health, temperament, active, notes.
- [ ] Access mirrors the queen canonical route and current apiary-membership
      rules; no access regression versus the normal UI.
- [ ] **Export** button added next to **Add Observation** on the hive page.
- [ ] Kernel test in `tests/src/Kernel/` (`@group hivelog`) asserting row count,
      header, and access-filtering behaviour.

## Implementation notes
- Key files:
  - `hivelog.routing.yml` — add the CSV route.
  - `src/Controller/QueenController.php` — add `observationsCsv()`.
  - `src/Controller/HiveController.php` — add the button to the render array.
- No entity schema change → **no update hook required**.
- Reverse-lookup pattern: load observations via the `queen` reference, newest
  first (same ordering as the canonical page list).
- Keep aligned with [[0018-csrf-and-safe-http-methods]] and
  [[0020-access-parity-custom-routes]].

## Related
- Project:: [[queen-observation-enhancements]]
- Decisions:: [[0002-no-geocoder-dependency]], [[0018-csrf-and-safe-http-methods]], [[0020-access-parity-custom-routes]]
- Commits::
