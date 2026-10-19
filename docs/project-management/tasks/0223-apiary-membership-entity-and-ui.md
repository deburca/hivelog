---
type: task
tags: [hivelog/task]
status: in-progress
priority: high
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-18
branch: feature/0223-apiary-membership-entity-and-ui
release:
depends-on: ["[[0189-apiary-membership-access-adr]]"]
blocked-by:
---

# Task: ApiaryMembership entity and member management UI

## Context
Part of [[shared-apiaries-and-team-roles]]. Decision in ADR-0108: use a lightweight
custom `ApiaryMembership` entity (not Groups module). Each row links one apiary,
one user and one role (`owner` / `editor` / `viewer`).

## Acceptance criteria
- [x] `ApiaryMembership` entity type (`src/Entity/ApiaryMembership.php`) with base
      fields: `apiary` (entity_reference to Apiary), `account` (entity_reference to
      user), `role` (list_string: owner/editor/viewer)
- [x] Base table `hivelog_apiary_membership`; unique key on `(apiary, account)`
        enforced by preSave guard + database unique index via update hook
- [x] Reference field `apiary_memberships` added to Apiary, replacing `beekeepers`
- [x] Update hook (`hivelog_update_10032`) migrates existing beekeepers →
      memberships (all as viewer role) and removes old field
- [x] Delete-dependency rows (0108-1: apiary → apiary_membership CASCADE,
      0108-2: user → apiary_membership CASCADE)
- [x] Member management section on Apiary edit page: add member (dropdown + role),
      list current members with role editor and remove button, owner cannot be
      removed as last owner
- [x] Permissions in hivelog.permissions.yml (add / edit own/any / delete own/any)
- [ ] Tests run clean on DDEV (await CI after merge)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- **Entity definition**: extends `ContentEntityBase`, uses `[#[ContentEntityType]`
  attribute. One base row per apiary-user pair — enforce uniqueness in both the
  database schema (`->setUniqueKey()`) and a constraint plugin on the add form.
- **Role field**: list_string with allowed values `owner`, `editor`, `viewer`. Not
  configurable at runtime (extending roles requires editing the entity class).
- **Reference field on Apiary**: replaces the current `beekeepers` multi-value user
  reference with a single multi-value reference to `ApiaryMembership` entities. The
  existing `isApiaryMember()` method changes from iterating `beekeepers.user` to
  loading related `apiary_memberships` and checking `account.id`.
- **Update hook pattern**: follow the geofield migration hooks in `hivelog.install`
  (read old data, uninstall storage, install new storage, re-save entities). The
  tricky part is avoiding a fatal when the `beekeepers` column is removed before the
  entity is fully rebuilt — use `field_get_instance()` or direct schema inspection.
- **Member UI**: build as a custom form section on the Apiary edit form (not a separate
  page). Reuses the existing apiary form (#type = 'details' collapsible section).
  Member picker uses `entity_reference_autocomplete` with an admin-filtered user query.
- **Access guard**: only owners can see and manage the members section. The access
  handler checks `checkApiaryOwnerDeleteAccess()` — same owner check as apiary delete.
- **Cache context**: member list must cache on `user` (a non-owner sees nothing);
  member role changes invalidate the parent apiary's cache via `addCacheableDependency()`.

### Key files
- `src/Entity/ApiaryMembership.php` — entity definition + base fields
- `src/Entity/ApiaryMembershipListBuilder.php` — list builder (optional; can use default)
- `src/Form/ApiaryMembershipForm.php` — add/edit member form
- `src/Form/ApiaryMemberManagerForm.php` — list members on apiary edit page
- `src/Entity/Apiary.php` — update `beekeepers` → `apiary_memberships`, update
  `isApiaryMember()` method, update `baseFieldDefinitions()`
- `hivelog.install` — update hook for data migration
- `hivelog.permissions.yml` — new permissions (see task 0224)
- `src/Delete/ApiaryMembershipDeleteHandler.php` — delete-registry row

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0108-apiary-membership-entity-and-roles]]
- Commits::
