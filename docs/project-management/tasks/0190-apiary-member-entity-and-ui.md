---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Membership entity (apiary, user, role) with a Members section on the
      Apiary page, visible to owners
- [ ] Add/change/remove members among existing site users; an owner cannot
      remove the last owner
- [ ] Update hook; delete-registry rows; kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Update hook needed

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: 
- Commits:: 
