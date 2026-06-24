---
type: project
tags: [hivelog/project]
status: active
target:
created: 2026-06-16
---
# Project: Queen Observation Enhancements

## Goal
Make the `QueenObservation` entity more useful to beekeepers: let them export
an observation history and tidy up navigation around the queen canonical page.
Builds on the existing **Add Observation** flow that hangs off the hive page.

## Scope
- In scope: CSV export of a queen's observations; reconciliation of older queen-breadcrumb planning notes against the current implementation.
- Out of scope: new observation fields; charting/graphing (revisit later).

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
_(Set each task's `project:` to `[[queen-observation-enhancements]]`.)_

Currently:
- [[0001-queen-observation-csv-export]] — in-progress
- [[0002-breadcrumb-queen-canonical]] — done (already present in code/tests; closed during vault reconciliation)

## Open questions
- Should export be CSV only, or also JSON for re-import later? (CSV first.)
- Which release should carry the export once it is implemented? (Not assigned yet.)

## Related decisions
- [[0002-no-geocoder-dependency]] (use core facilities; no CSV library)
- [[0018-csrf-and-safe-http-methods]] (export stays a safe `GET`)
- [[0020-access-parity-custom-routes]] (export must mirror existing access rules)
