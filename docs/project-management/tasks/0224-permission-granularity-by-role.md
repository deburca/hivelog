---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-18
branch: feature/0224-permission-granularity-by-role
release:
depends-on: ["[[0223-apiary-membership-entity-and-ui]]"]
blocked-by:
---

# Task: Apiary roles: owner, beekeeper and novice

## Context
Part of [[shared-apiaries-and-team-roles]]; follows [[0223-apiary-membership-entity-and-ui]] and
[[0108-apiary-membership-entity-and-roles]]. Task 0223 shipped the membership entity and its
screens, but its roles are only stored: every access handler asks "is this user a member?" and
ignores the role, so every member has an owner-like edit right. This task makes the role count.

The design was settled in a review on 2026-10-24 and 2026-10-25 (rounds of questions, all
answered); the decisions below are the result. They supersede the older role table, which
described three roles with different rules and creator-based "delete own" rights.

Release: this is a **major** release (behaviour changes for existing members, a role rename,
access moved off the apiary owner field). It needs upgrade notes naming each update hook.

## Decisions

### Roles
Three ordered roles: **novice < beekeeper < owner**. Every handler asks one question, "is the user
at least this role in this apiary?", which matches the three checks `ApiaryAccessTrait` already has.

| Key | Label (Danish) | May do |
|---|---|---|
| `owner` | Owner (Ejer) | Everything a beekeeper can, plus: manage members and owners, edit the apiary's own settings (the apiary edit form: name, location, map, notes, visibility, the AI-insights consent toggle) and delete the apiary |
| `keeper` | Beekeeper (Biavler) | Every action on the apiary and its hives except the owner extras: add, edit and delete hives, queens (attach and detach), inspections, queen observations, calendar actions, reporting jobs as done or ignored (and editing or deleting those logs), components, inventory items, purchases, products, usage and yields, and sensor devices. May move a hive between apiaries (needs this role in both) |
| `novice` | Novice (Nybegynder) | Read-only: the apiary, hives, inspections, queens, observations, calendar and logs, inventory, components, sensors and readings, insights and the cost reports |

`keeper` avoids a clash with the Drupal site role `beekeeper`, which permissions and tests use.
Nothing in inventory, prices or insights is hidden from a novice.

### Ownership
- Owners are the memberships with role `owner`; there can be several at once. Handover: owner A adds
  B as `owner`; later (immediately or months on) B removes A. Owners may remove other owners; the
  last owner can neither remove themselves nor be removed.
- Access comes from the **current membership only**, never from the apiary's `uid` field and never
  from having created a record. A previous owner or member keeps no right over what they created.
  This removes the creator-based "delete own inspection" rules.
- `Apiary::uid` becomes **history only** ("created by"), is removed from the edit form and grants
  nothing. An update hook creates an `owner` membership for every existing apiary's current owner.
  Every place that reads the owner for access switches to memberships (about seven
  `getOwnerId()` uses, the owner shown on the apiary page, lists, the API's query narrowing).
- Site-wide screens that are not apiary-scoped (API clients, AI providers) stay administrator-only.

### Other rules
- **Both must allow:** the apiary role decides what a user may do in that apiary, and the site-wide
  "own" permission must also be present, so an administrator can still switch an area off. The
  existing "own" permissions keep their meaning.
- **Public apiaries:** a non-member with the site "own" permission sees the apiary and its hives
  read-only, as now.
- **API:** the field-app role keeps its permissions; app tokens go through the same handlers, so a
  token acts with the user's apiary role (a novice's token is read-only). Parity tests get role cases.
- **Queen detach** marks her inactive and keeps her link to the hive, as replacement does today.
- **Existing rows** `viewer` become `novice` (even though those members could edit before roles were
  enforced; accepted) and `editor` become `keeper`. Renaming stored values needs an update hook.

### Split out of this task
- [[0230-inspection-records-items-used]]: record items used on an inspection (for example 5 kg of
  sugar). Usage stays tied to reported jobs until then.
- [[0231-apiary-history-stamping-and-hive-move]]: stamp the apiary on records so history is not
  rewritten when a hive moves, and the move dialog (sensors, components and their stock and cost).

## Acceptance criteria
- [x] `ApiaryMembership::ROLES` is `owner` / `keeper` / `novice` with labels, and the member form shows
      the three roles with one-line descriptions. `hivelog_update_NNNN` rewrites `viewer` to `novice`
      and `editor` to `keeper`
- [x] The same hook, or a sibling, creates an `owner` membership for each apiary's current `uid` owner
      if it has none, and is safe to run twice
- [x] `ApiaryAccessTrait` gets a cached `getMemberRole()` (one query per apiary and user per request)
      and `roleAtLeast()`; `Apiary::isApiaryMember()` and an `isOwner()` use memberships, not `uid`
- [x] A new apiary-level operation for "may write in this apiary" (beekeeper and above) is used by
      the scoped add routes (`hivelog.hive.add`, calendar action, action log, inventory item,
      purchase, product, hive component) and by `HivelogParentAccess`; apiary `update` stays
      owner-only (it is the edit form). Today those routes use `apiary.update`, which only owners pass
- [x] All 17 core and 6 submodule `*AccessControlHandler`s use the role (view: any role; create, edit,
      delete: beekeeper; membership, apiary update and delete: owner); the creator-based delete rules
      (`checkApiaryMemberDeleteAccess()`) are removed
- [x] Membership rules: owners only, owners may remove owners, the last owner is protected (in the
      form and in the entity). The apiary form no longer shows the owner field, and the apiary page
      summary shows owners from memberships
- [x] Delete-dependency and BLOCK gates still pass for owners (deleting an apiary is owner-only)
- [x] The API goes through the handlers; `HivelogApiAccessParityTest` gets owner, beekeeper, novice
      and non-member cases; `hivelog_api` contract unchanged unless a field is renamed
- [x] `RouteEntityAccessTest` and the other access tests get a role matrix for every entity type:
      site admin, owner, beekeeper, novice, non-member (private), non-member (public); existing
      fixtures are updated (former `editor` rows become `keeper`, `viewer` rows `novice`)
- [x] Cache contexts are right on every access result (`user`, and the apiary and its memberships as
      cache dependencies) so one user's role is never served to another
- [x] Danish translations for the role labels and descriptions; upgrade notes for the major release
- [x] `AGENTS.md` updated (roles, ownership, the new operation, uid history-only, update hooks)
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- The role lookup runs on every access check, so query memberships once per apiary and user per
  request and cache the result in a static keyed by apiary id and user id; invalidate it when a
  membership is saved or deleted.
- `HivelogParentAccess` (task 0133) requires `update` on the parent for any child created or moved.
  With roles that parent check must use the new "may write" operation, otherwise a beekeeper still
  cannot add a hive, inspection or queen observation. Re-check the "foreign parent refused" tests.
- Order of work: roles and the migration first; the history stamping and the hive move
  ([[0231-apiary-history-stamping-and-hive-move]]) come after, and will change `resolveApiary()` to
  prefer the stamped apiary on records that carry one.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0108-apiary-membership-entity-and-roles]]
- Follow-ups:: [[0230-inspection-records-items-used]], [[0231-apiary-history-stamping-and-hive-move]]
- Commits::
