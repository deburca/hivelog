---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: docs
created: 2026-10-04
branch: feature/0189-apiary-membership-access-adr
release:
depends-on:
blocked-by:
---
# Task: ADR: apiary membership access model

## Context
Market survey ([[2026-10-04-beekeeping-software-survey]])
proposal F. Security-critical: route-level entity access ([[0133-route-
level-entity-access]]) and list row filtering ([[0124-list-page-row-
access-filter]]) must both honour any new grant.
Part of [[shared-apiaries-and-team-roles]].

## Acceptance criteria
- [x] ADR (ADR-0108) created: roles (owner/editor/viewer), custom entity vs
      Groups module decision, consequences and migration path
- [x] Versioning impact recorded in ADR-0108 (§ Consequences)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- ADR-0108 (2026-10-18) recommends the lightweight custom-entity approach over
  Groups module. Next step is to build the `ApiaryMembership` entity and UI
  (task 0223), then add role checks across access handlers (task 0191).

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0108-apiary-membership-entity-and-roles]]
- Commits::
