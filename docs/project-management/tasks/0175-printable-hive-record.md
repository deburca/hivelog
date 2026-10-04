---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[data-export-and-printable-records]]"
area: theme
created: 2026-10-04
branch: feature/0175-printable-hive-record
release:
depends-on:
blocked-by:
---
# Task: Printable per-hive record

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal A. A beekeeper (or inspector) wants one document per hive.
Part of [[data-export-and-printable-records]].

## Acceptance criteria
- [ ] A Print record button on the Hive page opening a print-optimised route
      (`/hivelog/hive/{hive}/record`)
- [ ] Includes hive details, components/empty weight, queens, inspections
      (date range selectable), action logs and treatments once
      [[treatment-register]] exists
- [ ] Print stylesheet in its own library depending on `hivelog/responsive`;
      no app nav or buttons in print
- [ ] Entity access enforced as for the canonical page; kernel test
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Start with print CSS + browser Save as PDF; record whether a server-side PDF is still wanted

## Related
- Project:: [[data-export-and-printable-records]]
- Decisions:: 
- Commits:: 
