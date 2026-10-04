---
type: task
tags: [hivelog/task]
status: backlog
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
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal F. Security-critical: route-level entity access ([[0133-route-
level-entity-access]]) and list row filtering ([[0124-list-page-row-
access-filter]]) must both honour any new grant.
Part of [[shared-apiaries-and-team-roles]].

## Acceptance criteria
- [ ] ADR (next free number): roles (owner/editor/viewer) and their mapping to
      existing permissions; inheritance to child entities and submodule
      entities; interaction with `administer hivelog`; cache contexts
- [ ] Versioning impact recorded per ADR-0010
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: 
- Commits:: 
