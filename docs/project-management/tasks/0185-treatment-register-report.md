---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[treatment-register]]"
area: reporting
created: 2026-10-04
branch: feature/0185-treatment-register-report
release:
depends-on: ["[[0183-treatment-entity-and-ui]]", "[[0174-collection-csv-export]]"]
blocked-by:
---
# Task: Printable treatment register report

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal D. The register a beekeeper shows an inspector.
Part of [[treatment-register]].

## Acceptance criteria
- [ ] Per-apiary, per-year register route listing every treatment with all
      required fields
- [ ] Print stylesheet and CSV export (reusing [[0174-collection-csv-export]])
- [ ] Entity access enforced; kernel test
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[treatment-register]]
- Decisions:: 
- Commits:: 
