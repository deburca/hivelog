---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[data-export-and-printable-records]]"
area: routing
created: 2026-10-04
branch: feature/0174-collection-csv-export
release:
depends-on:
blocked-by:
---
# Task: CSV export of collection lists

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal A. No HiveLog list can be exported today.
Part of [[data-export-and-printable-records]].

## Acceptance criteria
- [ ] An Export CSV link on every `HivelogListBuilder` collection page (core
      and submodules), placed via the list heading, offered only to
      users who can view the collection
- [ ] Export honours the active filter form values and sort, and only includes
      rows `load()` already access-filters
- [ ] Columns match the list's `buildHeader()`/row labels; dates ISO 8601;
      formula-injection-safe (cells starting `= + - @` escaped)
- [ ] Routes under `/hivelog/`, streamed response for large lists
- [ ] Kernel tests: access filtering, filter honouring, escaping
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Consider a shared trait/hook on `HivelogListBuilder` so submodule lists get it for free
- Calendar Actions collection is built by its controller, not a list builder — handle separately

## Related
- Project:: [[data-export-and-printable-records]]
- Decisions:: 
- Commits:: 
