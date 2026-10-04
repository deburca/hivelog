---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[data-export-and-printable-records]]"
area: routing
created: 2026-10-04
branch: feature/0176-apiary-export-bundle
release:
depends-on: ["[[0174-collection-csv-export]]"]
blocked-by:
---
# Task: Apiary-wide export bundle

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal A. A full backup of one apiary's records in one download.
Part of [[data-export-and-printable-records]].

## Acceptance criteria
- [ ] An Export all button on the Apiary page producing a zip of per-entity-
      type CSVs scoped to that apiary
- [ ] Reuses the CSV writer from [[0174-collection-csv-export]]; access-
      filtered per row
- [ ] Submodule entities included via a hook (document it in
      `hivelog.api.php`)
- [ ] Kernel test
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[data-export-and-printable-records]]
- Decisions:: 
- Commits:: 
