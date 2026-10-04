---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-04
branch: feature/0191-membership-aware-access-handlers
release:
depends-on: ["[[0190-apiary-member-entity-and-ui]]"]
blocked-by:
---
# Task: Membership-aware access handlers and list filtering

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal F.
Part of [[shared-apiaries-and-team-roles]].

## Acceptance criteria
- [ ] Every core and submodule access handler and list row filter grants per
      membership role, per the ADR
- [ ] `RouteEntityAccessTest` (kernel, hard gate) extended with member/non-
      member cases for every entity type
- [ ] Correct cache contexts so one user's access is never cached for another
- [ ] AGENTS.md access section updated
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: 
- Commits:: 
