---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-04
branch: feature/0190-apiary-member-entity-and-ui
release:
depends-on: ["[[0189-apiary-membership-access-adr]]"]
blocked-by:
---
# Task: Apiary member entity and manage-members UI

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal F.
Part of [[shared-apiaries-and-team-roles]].

## Acceptance criteria
- [x] Membership entity (apiary, user, role) with a Members section on the
      Apiary page, visible to owners
- [x] Add/change/remove members among existing site users; an owner cannot
      remove the last owner
- [x] Update hook; delete-registry rows; kernel tests
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Outcome (closed 2026-10-26)
Delivered by [[0223-apiary-membership-entity-and-ui]] and the role work of [[0224-permission-granularity-by-role]],
shipped in 3.0.0: the `apiary_membership` entity, the Members item on the Apiary edit form (owners only), add, change
and remove among site users, the last owner cannot be removed, `hivelog_update_10032` and `_10034`, delete-registry
rows 0108-1/0108-2, and `ApiaryMembershipTest`. The roles are `novice` < `keeper` < `owner`, not the
owner/editor/viewer the task was written with.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: 
- Commits:: merged for 3.0.0 (built as 0223, with roles in 0224)
