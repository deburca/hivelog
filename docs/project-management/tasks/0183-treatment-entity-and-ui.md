---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[treatment-register]]"
area: entity
created: 2026-10-04
branch: feature/0183-treatment-entity-and-ui
release:
depends-on: ["[[0182-treatment-register-adr]]"]
blocked-by:
---
# Task: Treatment entity, forms and hive section

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal D.
Part of [[treatment-register]].

## Acceptance criteria
- [ ] Entity per [[0182-treatment-register-adr]] with scoped add route
      `/hivelog/hive/{hive}/treatment/add`
- [ ] Treatments section on the Hive page; collection list extending
      `HivelogListBuilder` with filters and sortable columns
- [ ] Access handler, permissions,
      `HivelogEntityHierarchy::PARENT_FIELD`/`COLLECTION_TYPES` entries,
      delete-registry rows, nav item under Apiaries
- [ ] Update hook installing the entity type; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[treatment-register]]
- Decisions:: 
- Commits:: 
