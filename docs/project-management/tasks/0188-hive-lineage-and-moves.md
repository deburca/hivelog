---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[colony-lifecycle-events]]"
area: entity
created: 2026-10-04
branch: feature/0188-hive-lineage-and-moves
release:
depends-on: ["[[0186-colony-lifecycle-adr]]"]
blocked-by:
---
# Task: Hive lineage and moves between apiaries

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal E.
Part of [[colony-lifecycle-events]].

## Acceptance criteria
- [ ] Optional parent-hive reference (split/swarm origin) shown on the Hive
      page with links to children
- [x] A Move hive action (done by [[0231-apiary-history-stamping-and-hive-move]], 3.0.0; history is stamped, not
      rewritten). Recording it as a colony event waits for [[0187-colony-event-entity-and-timeline]]
- [x] Access, breadcrumb and apiary-scoped reports verified after a move; kernel tests (0231 and the 3.0.1 fix)
- [ ] Update hook
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Status (2026-10-26)
Reviewed for closure. The **move** half is done by 0231 (and a report fix in 3.0.1). The **lineage** half (an optional
parent-hive reference for splits and swarms, shown with links to children) is not built, so this task stays open,
narrowed to lineage and to logging the move as a colony event once 0187 exists. Still depends on
[[0186-colony-lifecycle-adr]].

## Related
- Project:: [[colony-lifecycle-events]]
- Decisions:: 
- Commits:: 
