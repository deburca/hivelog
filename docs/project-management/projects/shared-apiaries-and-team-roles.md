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
Apiary (Queen/Bee/Drone roles), BeeKeepPal (team plans) and BeeHero
(team management) all offer this. This is the largest and riskiest
proposal: it touches every access handler, route-level access check and
list row filter.

Proposal F from the [[2026-10-04-beekeeping-software-survey]].

## Two deployment models — how they fit together

HiveLog supports **two** distinct use cases that share a common membership layer but
differ at their entry point:

```
Use Case 1 (friend/family):     User creates apiary → adds members with roles
Use Case 2 (SaaS host):         Commerce subscribes user → auto-creates account+apiary → user adds members with same roles
                                          ↑                              ↑
                                    Entitlement gate              Membership roles (ADR-0108)
```

**Layer 2 — Membership roles (shared):** ADR-0108 defines the role system (owner/editor/
viewer). This layer is identical in both use cases. A beekeeper on a SaaS site or a self-
hosted friend/family site both see and use the same member management UI.

**Layer 1 — Entitlement gating (SaaS only):** ADR-0109 defines an entitlement interface that
commerce modules implement to gate *who can create apiaries at all*. Self-hosted sites never
need this; SaaS hosts configure it via the commerce submodule. These two layers are orthogonal
— one controls access, the other controls creation — and neither depends on the other's existence.

### Use case 1: Friend/family (self-hosted)
A beekeeper installs HiveLog on their own Drupal site. The `"add apiary"` permission is
granted to any authenticated user or a custom role. No entitlement check fires beyond the
standard Drupal permission system. They manage apiaries and invite friends using the member
management UI built in this project.

**Requires:** membership roles (this project) + standard Drupal permissions. No commerce.

### Use case 2: Hosted SaaS (hivelog.com or similar)
The site owner hosts HiveLog as a service where beekeepers subscribe. A commerce module
(`hivelog_commerce` in a separate repository) integrates with the payment platform:
1. Beekeeper signs up, pays for a tier.
2. Commerce webhook auto-creates a Drupal account with `"add apiary"` role.
3. Tier's quota determines how many apiaries/hives they can create (enforced by the
   entitlement interface).
4. If subscription lapses: new apiaries are blocked; existing data stays accessible.
5. Beekeeper uses the same member management UI as case 1 to invite friends/family.

**Requires:** this project + `hivelog_commerce` submodule (separate repo) + a commerce platform.

## Scope
- In scope:
  - ADR amending the access model (route-level entity access, task 0133; list row filtering, task 0124)
  - An apiary membership entity and manage-members UI
  - Access handlers and list filtering honouring membership, with full security test coverage
  - Entitlement interface for SaaS subscription gating (ADR-0109) — **does not add a commerce dependency to core**
- Out of scope:
  - Organisation/tenant hierarchy above apiaries
  - Email invitations to people without site accounts
  - Payment gateway integrations (handled by `hivelog-commerce` submodule, separate repo)
  - Multi-tenant hosting infrastructure

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0189-apiary-membership-access-adr]] (ADR decision — **done**, see ADR-0108)
- [[0223-apiary-membership-entity-and-ui]] (API membership entity + member management UI)
- [[0190-apiary-member-entity-and-ui]] (superseded by 0223; keep for history)
- [[0224-permission-granularity-by-role]] (role-based permissions across all access handlers)
- [[0191-membership-aware-access-handlers]] (access parity tests and cache contexts)
- [[0225-hivelog-entitlement-interface]] (EntitlementInterface + default service + quota enforcement in ApiaryForm)
- [[0226-hivecount-quota-enforcement]] (quota checking, subscription lifecycle hooks, grace period)

## Projects
- [[shared-apiaries-and-team-roles]] (this project — Layer 2: membership roles)
- [[hivelog-commerce-submodule]] (Layer 1: commerce integration in a separate repo — ADR-0109)

## Open questions
- Do child records (hives, inspections) inherit apiary membership only, or can they be shared individually?
  → For v1: **apiary membership only**. A hive is always scoped to its parent apiary's members.
  Individual hive sharing is a future phase if real users need it.
- Does this warrant a major version bump (permission semantics change)?
  → **Yes.** The `"own"` permission on apiaries, hives and inspections will now have different
  meanings for owners vs editors vs viewers. Sites upgrading may see access changes.
  Document in release notes; recommend testing on staging first.
- How does the SaaS host's commerce integration handle refund/cancel scenarios?
  → Per ADR-0109: no auto-deletion of user data. The commerce submodule handles grace periods,
  reactivation, and notification emails. This is outside core's scope.

## Related decisions
- [[0108-apiary-membership-entity-and-roles]] (ADR — recommends lightweight custom entity over Groups module)
- [[0109-entitlement-interface-commerce-gating]] (ADR — entitlement interface + separate commerce submodule)
- [[0019-authorisation-model]] (current apiary-scoped model — ADR-0108 amends this)
- [[0022-authentication-and-membership]] (existing beekeepers field and visibility model)
- [[0098-nanoprobe-collective-locutus-submodule-split]] (ADR — submodule split philosophy, guides commerce submodule approach)
