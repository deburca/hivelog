---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-10-18
supersedes:
---

# ADR-0108: Apiary membership entity and role-based permissions

## Status
accepted, 2026-10-18 — custom `ApiaryMembership` entity replacing the current `beekeepers`
reference field. Followed by [[0223-apiary-membership-entity-and-ui]],
[[0224-permission-granularity-by-role]]. See [[shared-apiaries-and-team-roles]].

## Context

HiveLog currently supports shared apiaries through the `beekeepers` field on the
`Apiary` entity (a multi-value user reference, task 0189–0191 in the PM vault).
This gives all listed beekeepers identical access: the same `"own"` scoped
permissions as the owner, no distinction between owner, editor and viewer.

The market survey ([[2026-10-04-beekeeping-software-survey]]) proposal F calls
for role-based team membership — comparable to Apiary's Queen/Bee/Drone roles,
BeeKeepPal's team plans, and BeeHero's team management. Specifically:

- **Owner**: full access including delete apiary, manage members, change ownership.
- **Editor**: can edit all records in the apiary (hives, inspections, queens,
  inventory, calendar actions) but cannot delete the apiary itself or manage
  members. Cannot transfer ownership.
- **Viewer**: read-only access to all records; can delete only their own
  hive inspections and queen observations (same as current `"own"` scope).

The `beekeepers` field already handles *who* is a member. What's missing is
*what role* each member has, and permissions that vary by that role. The key
question is **how** to add roles: use Drupal's contributed **Groups** module
(approach B), or build a lightweight custom entity (approach A)?

### Current state recap

- `Apiary` owns an `owner` (`uid` entity key) and a `beekeepers` reference field.
- `Apiary::isApiaryMember()` returns `TRUE` for the owner or anyone in
  `beekeepers` — no role discrimination.
- `ApiaryAccessTrait::checkApiaryViewAccess()`, `::checkApiaryEditAccess()`,
  and `::checkApiaryOwnerDeleteAccess()` are called by all 16 access handlers;
  they check `isApiaryMember()` as a binary test.
- A private apiary is viewable only to members; a public one is viewable to any
  authenticated user with the `"own"` permission regardless of membership.
- The `shared-apiaries-and-team-roles` project tracks this work (tasks 0189,
  0190, 0191), all in backlog.

### Approach A: Lightweight custom entity (recommended)

Create an `ApiaryMembership` entity (`apiary_membership`) with fields for apiary,
user and role, linked from the apiary via a reference field replacing `beekeepers`.

**Pros:**
- No new contrib dependency — HiveLog stays at 2 deps (`geofield`, `leaflet`).
- Full control over schema, update hooks and behaviour.
- Minimal surface: one entity type, one access handler, a few UI forms.
- Directly replaces the existing `beekeepers` field with a drop-in migration.
- Fits HiveLog's philosophy of avoiding unnecessary dependencies (ADR-0006).

**Cons:**
- Must build member list UI, add/edit/delete member forms from scratch.
- Must implement update hooks to migrate data from the current `beekeepers`
  field onto the new entity type.
- Must write delete-dependency registry rows and corresponding tests.
- Role-to-permission mapping logic must be written manually (not a huge amount
  of code, but novel).

### Approach B: Drupal Groups module

Use the **Groups** contrib module (`drupal/groups`) as the membership backbone.
Each apiary is a group; members are added through the Group module's UI.

**Pros:**
- Role system is built in — maps directly to owner/editor/viewer concepts.
- Member management UI, forms, access checks all exist out of the box.
- Drupal ecosystem alignment — contributors expect Groups patterns.
- Less custom code overall.

**Cons:**
- Adds a third contrib dependency. HiveLog currently carries only 2 required deps
  (`geofield`, `leaflet`), both with a narrow scope and well-maintained status.
  Groups is **heavy** (~50+ hooks, 7 entity types, multiple permissions,
  upgrade paths) and its maintenance rhythm is unpredictable.
- Update hooks are complex: migrating from `beekeepers` to Group memberships,
  handling existing group content, dealing with Group's own update sequences.
- Groups uses its own permission system (`group access`, `membership`, etc.)
  that must be reconciled with HiveLog's `"own"` / `"any"` permissions — not a
  straightforward mapping.
- Security implications of a large contributed module are greater than a few
  lines of custom code; security reviews on every Groups release add ongoing cost.
- Groups module is not part of Drupal core and its roadmap is independent of
  HiveLog's needs (ADR-0006: *"Do not add a contrib dependency unless the problem
  is impossible to solve with fewer moving parts"*).

### Recommendation

**Approach A — lightweight custom entity.** The role system is small enough to
implement as one entity type and one access handler without introducing a heavy
third-party dependency. The existing `beekeepers` field provides a clean migration
path (replace with an `apiary_memberships` reference), and the role-to-permission
mapping is straightforward (4 new permissions, each checked in the existing 16
access handlers). This keeps HiveLog's dependency budget intact while delivering
the feature.

Groups would be worth reconsidering if:
- multiple sites need *complex* membership models (sub-groups, nested teams),
- there is a strong demand for Groups interoperability with other Drupal modules, or
- the role system grows beyond simple owner/editor/viewer to something Groups
  already handles (e.g., approval workflows, invitation flows).

Neither condition exists today.

## Decision

Implement apiary membership as a custom `ApiaryMembership` entity type instead of
using the Groups module. Each row links one apiary, one user and one role
(`owner` / `editor` / `viewer`). The apiary entity carries a reference field
(`apiary_memberships`) listing these rows (replacing the current `beekeepers`
field). Access handlers check the member's role to determine which permissions apply.

## Consequences

- Positive:
  - No new contrib dependency added; HiveLog stays at 2 deps.
  - Migration from existing `beekeepers` data is well-defined.
  - Role-to-permission mapping is explicit and easy to audit in tests.
  - Delete-dependency rows on `Apiary` propagate to `ApiaryMembership`.
- Negative / trade-offs:
  - Member management UI must be built from scratch (one page per apiary).
  - Update hook complexity for data migration (existing beekeepers → memberships).
  - If the role model later needs approval flows, invitations or sub-groups,
    Groups would be a better fit — that's a re-decision point.
- Follow-up tasks: [[0223-apiary-membership-entity-and-ui]],
  [[0224-permission-granularity-by-role]]. See also [[shared-apiaries-and-team-roles]]
  and [[hivelog-commerce-submodule]].
