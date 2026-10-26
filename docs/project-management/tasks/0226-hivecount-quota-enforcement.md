---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-18
branch: feature/0226-hivecount-quota-enforcement
release:
depends-on: ["[[0225-hivelog-entitlement-interface]]"]
blocked-by:
---

# Task: Hive limit enforcement and what happens when ownership or a subscription changes

## Context
Part of [[shared-apiaries-and-team-roles]]. Follows [[0225-hivelog-entitlement-interface]]. With the contract in
place, this task enforces the hive limit where hives are created or moved, and defines how the limit follows a hand-over
and falls back to the free model. The commerce layer ([[0227-hivelog-commerce-submodule-repo]]) is what supplies real
limits; core only enforces what the entitlement service says.

**Revised 2026-10-26 for 3.0.0 and the commerce decisions.** The first version counted by `uid`, withdrew `add apiary`
and `add hive` from the user's roles on a lapse, and had a grace period. Since 3.0.0 owners are memberships, and the
decision is that a lapse or a downgrade moves the apiary to the **free model**, not to a lock.

## Decisions
1. **The count is the primary owner's.** An owner's hive count is the number of hives in every apiary they are the
   primary owner of, each apiary counted once, so an apiary with two owners during a hand-over is the first owner's.
2. **A hand-over moves the count.** When the current owner is removed and the next owner becomes primary
   (3.0.0, task 0232), the apiary and its hives count for the new owner. Until the new owner has a compatible plan the
   apiary is on the **free model**; the previous owner paid until the moment the new owner took over.
3. **Moving a hive** between apiaries moves one hive from one primary owner's count to another's. The target's owner
   limit is checked: a move that would put them over is refused with the reason.
4. **Over the limit is not an error state, it is the free model.** A downgrade below the current hive count, a lapse
   or a missing subscription all give the apiary the free model's limit (initially 5, configurable) and features.
   Existing hives are never removed, hidden or frozen; what is refused is **adding** hives (and a hive move into the
   owner's apiaries) until the count is under the limit, or the owner subscribes to a plan whose range covers it.
5. **No role changes.** Entitlement is checked at the point of use; HiveLog never edits a user's roles or permissions
   because of a subscription.

## Acceptance criteria
- [ ] Adding a hive (`hivelog.hive.add` and the hive form) is blocked, with a message naming the limit and the way to
      raise it, when the apiary's primary owner is at their limit (`hivelog.entitlement_checker->canAddHive()`)
- [ ] The same rule applies through the API and any non-form caller: a validation constraint on a new hive, in the way
      `HivelogParentAccess` works, so the check cannot be bypassed by an invalid-but-persisted route
- [ ] Moving a hive (the 0231 dialog and its service) checks the target apiary's primary owner's limit, and the move
      shows why it is refused
- [ ] Counts are cached per owner with a cache tag invalidated when a hive is added, deleted or moved, and when the
      primary owner of an apiary changes; entitlement results use the `hivelog_entitlement:<uid>` tag from 0225
- [ ] `hook_hivelog_primary_owner_changed(Apiary $apiary, ?UserInterface $previous, ?UserInterface $new)` fires when an
      apiary's primary owner changes (an owner is removed or a membership becomes the longest-held owner), so the
      commerce layer can notify the new owner that they need a plan
- [ ] An apiary on the free model because of a lapse, a downgrade or a pending hand-over shows a notice on its page
      saying which and what the owner can do; it does not appear on a self-hosted site
- [ ] A site-wide setting for the free model's hive limit (default 5), in the commerce layer's configuration when it is
      installed and a core setting otherwise
- [ ] Kernel tests: the limit blocks a hive at the limit and not below it; two owners count an apiary once; a hand-over
      moves the count and drops to free until a plan exists; a hive move is refused over the target's limit; existing
      hives stay editable when over the limit
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `AGENTS.md` updated

## Implementation notes
### Counting
Find the apiaries an account is the primary owner of from the owner memberships (`owner_since`, then id), not from
`uid`, and sum their hives with one aggregate query. Several owners of one apiary share a single count.

### What replaced the grace period
The old note had a configurable grace period before permissions were withdrawn. With no permission changes there is
nothing to delay; any billing grace period (days of past-due before a plan is treated as lapsed) belongs to the
commerce layer.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0109-entitlement-interface-commerce-gating]]
- Commerce:: [[0227-hivelog-commerce-submodule-repo]]
- Follows:: [[0225-hivelog-entitlement-interface]], [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
