---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-18
branch: feature/0225-hivelog-entitlement-interface
release:
depends-on: ["[[0224-permission-granularity-by-role]]"]
blocked-by:
---

# Task: Hivelog entitlement interface and default service

## Context
Part of [[shared-apiaries-and-team-roles]]. ADR-0109 gates apiary creation for hosted deployments without baking a
commerce dependency into HiveLog; the commerce package itself is [[0227-hivelog-commerce-submodule-repo]]. This task
is the core half: the contract the commerce layer implements, and a default that allows everything.

**Revised 2026-10-26 for 3.0.0 and the commerce decisions.** The first version counted what a user "owns" through the
apiary's `uid` and asked only "may this account add an apiary". Since 3.0.0 an apiary's owners are its `owner`
memberships (several at once during a hand-over) and `uid` is only "created by", and the hosted model needs more
than creation: a hive limit and a cut-down feature set. Layer 2 (what members do inside an apiary) is
[[0224-permission-granularity-by-role]], done.

## Decisions
1. **The primary owner's plan applies to an apiary.** The primary owner is `Apiary::getPrimaryOwner()`, the owner who
   has held the role longest. An apiary with two owners during a hand-over is therefore counted once, for the first
   owner; when that owner is removed the next owner becomes primary and the apiary, with its hives, moves to their plan.
2. **An apiary is on one of two models:** the plan its primary owner holds, or the **free model**. The free model
   applies whenever the primary owner has no compatible plan: no subscription, a lapsed one, one whose range does
   not cover their hive count, or a hand-over where the new owner has not yet subscribed.
3. **Plans, prices and ranges are the commerce layer's** (0227), configurable there. Core only asks questions and has no
   idea what a plan costs. With no commerce layer installed everything is allowed and unlimited (a self-hosted site
   is unchanged).
4. **The free model** is a configurable set of limits and features: an initial limit of **5 hives**, and the features
   of the full version minus sensors, and potentially minus multiple members and minus hive moves (each decided per
   site, in the commerce layer's configuration).
5. **Data is never deleted** by any of this. Entitlement only decides what can be added or used.

## Acceptance criteria
- [ ] `src/Entitlement/EntitlementInterface.php`:
      - `canAddApiary(AccountInterface $account): bool`
      - `getHiveLimit(AccountInterface $owner): ?int`: how many hives the owner's plan allows across every apiary they
        are the primary owner of; `NULL` means unlimited
      - `hasFeature(Apiary $apiary, string $feature): bool`: whether the apiary's plan includes a feature
      - feature ids as constants: `FEATURE_SENSORS`, `FEATURE_MULTIPLE_MEMBERS`, `FEATURE_HIVE_MOVE`
- [ ] `src/Entitlement/DefaultEntitlement.php`: always TRUE and unlimited (self-hosted behaviour)
- [ ] Service `hivelog.entitlement`, replaceable by the commerce layer (as described in the implementation notes)
- [ ] `Apiary::getPrimaryOwner()` is the only way core finds whose plan applies; nothing reads `uid`
- [ ] A core helper (service `hivelog.entitlement_checker`) answering the questions core needs, built on the interface:
      `canAddHive(Apiary $apiary)`, `hiveCountFor(AccountInterface $owner)` (hives in every apiary whose primary owner
      is that account, each apiary counted once), and `isOnFreeModel(Apiary $apiary)` for messages
- [ ] Features are checked where they are used: nanoprobe (adding a sensor device), the membership add form (a second
      and later member beyond the owners), and the hive move dialog; each shows why a feature is unavailable. A
      feature that is off hides or blocks only the *addition*; existing data stays readable
- [ ] `ApiaryForm`, which creates an apiary, calls `canAddApiary()`; the entitlement check supplements the existing
      `add apiary` permission, a user needs both
- [ ] Results are cacheable with a cache tag per account, `hivelog_entitlement:<uid>`, that the commerce layer
      invalidates when a subscription changes
- [ ] Kernel tests: the default allows everything; a test implementation that denies blocks creation; the primary owner,
      not the creator or a second owner, is the one whose plan counts
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `AGENTS.md` updated

## Implementation notes
### Service replacement (no hard dependency)
The commerce package registers its own `hivelog.entitlement` service, overriding core's by declaring a dependency on
`hivelog` (a service provider alter, or the service id override Drupal applies by module order). A site without it keeps
the default. Hooks are not used for this: untyped, uncacheable, and legacy from Drupal 12.

### Why the interface takes an apiary for features and an owner for the hive limit
A hive limit is a property of a person's plan across all their apiaries; a feature is a property of one apiary, whose
plan is its primary owner's. Both resolve to the same person (`getPrimaryOwner()`), but the question differs.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]], [[0108-apiary-membership-entity-and-roles]]
- Commerce:: [[0227-hivelog-commerce-submodule-repo]]
- Follows:: [[0224-permission-granularity-by-role]], [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
