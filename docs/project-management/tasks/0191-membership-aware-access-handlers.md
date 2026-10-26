---
type: task
tags: [hivelog/task]
status: done
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
- [x] Every core and submodule access handler and list row filter grants per
      membership role, per the ADR
- [x] `RouteEntityAccessTest` (kernel, hard gate) extended with member/non-
      member cases for every entity type
- [x] Correct cache contexts so one user's access is never cached for another
- [x] AGENTS.md access section updated
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Outcome (closed 2026-10-26)
Delivered by [[0224-permission-granularity-by-role]] in 3.0.0: every core and submodule access handler decides from the
current membership role through `ApiaryAccessTrait`, `RouteEntityAccessTest` and `HivelogApiAccessParityTest` cover
member and non-member cases, and a role change is seen at once (the role cache is reset on membership save and delete).
`AGENTS.md` describes the model. The API follows the same handlers.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: 
- Commits:: merged for 3.0.0 (built as 0224)
