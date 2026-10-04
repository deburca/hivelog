---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Shared Apiaries and Team Roles

## Goal
Let an apiary owner invite other site users as collaborators with
owner/editor/viewer roles, on top of today's own/any permission model.
ApiManager (Queen/Bee/Drone roles), BeeKeepPal (team plans) and BeeHero
(team management) all offer this. This is the largest and riskiest
proposal: it touches every access handler, route-level access check and
list row filter.

Proposal F from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - ADR amending the access model (route-level entity access, task 0133; list row filtering, task 0124)
  - An apiary membership entity and manage-members UI
  - Access handlers and list filtering honouring membership, with full security test coverage
- Out of scope:
  - Organisation/tenant hierarchy above apiaries
  - Email invitations to people without site accounts

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0189-apiary-membership-access-adr]]
- [[0190-apiary-member-entity-and-ui]]
- [[0191-membership-aware-access-handlers]]

## Open questions
- Do child records (hives, inspections) inherit apiary membership only, or can they be shared individually?
- Does this warrant a major version bump (permission semantics change)?

## Related decisions
- 
