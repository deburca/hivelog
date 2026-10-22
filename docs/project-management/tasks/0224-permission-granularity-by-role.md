---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[shared-apiaries-and-team-roles]]"
area: routing
created: 2026-10-18
branch: feature/0224-permission-granularity-by-role
release:
depends-on: ["[[0223-apiary-membership-entity-and-ui]]"]
blocked-by:
---

# Task: Permission granularity by role across access handlers

## Context
Once `ApiaryMembership` exists (task 0223), all 16 access control handlers and
list builders must honour the member's role instead of treating every beekeeper
identically. The three roles map to distinct permission sets:

| Action | Owner | Editor | Viewer |
|---|---|---|---|
| View apiary, hives, inspections | Y | Y | Y |
| Edit apiary (name, location, etc.) | Y | N | N |
| Delete apiary | Y | N | N |
| Add hive | Y | Y | N |
| Edit hive (including members) | Y | Y | N |
| Delete hive | Y | Y* | N |
| Add/edit/delete inspection | Y | Y | Y** |
| Add queen observation | Y | Y | Y*** |
| Manage inventory/products | Y | Y | N |
| View calendar actions | Y | Y | Y |
| Edit/delete calendar action log | Y | Y | N |
| Delete apiary (BLOCK gate) | Y | — | — |

\* Editor can delete hives but not the apiary itself.
\** Viewer can delete their own inspections.
\*** Viewer can delete their own observations.

## Acceptance criteria
- [ ] New permissions defined in `hivelog.permissions.yml`:
      - `edit own apiary` (replaces current meaning — now owner-only)
      - `manage apiary members` (owner-only, for member UI)
      - `delete own hive inspection` (viewer + editor)
      - `delete own queen observation` (viewer + editor)
- [ ] All 16 `*AccessControlHandler.php` files updated to check role from the
      `ApiaryMembership` entity chain instead of binary `isApiaryMember()`
- [ ] `ApiaryAccessTrait` refactored: new methods for each role-check path
      (`checkEditorAccess()`, `checkViewerAccess()`) alongside existing owner-only
      checks; `isApiaryMember()` remains for shared-apiary visibility (public apiary)
- [ ] List builders filter rows by role where appropriate (editors see only records
      they can edit, viewers see all but cannot delete via the operations column)
- [ ] Route-level entity access (`_access` in routes.yml) updated if any route
      needs role-specific access that the handler does not cover
- [ ] Kernel test: `RouteEntityAccessTest` extended with owner/editor/viewer/non-member
      cases for every entity type (as per task 0191's acceptance criteria)
- [ ] Cache contexts correct on all access results (`user`, `url.path` where needed)
      so one user's role is never cached for another
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
### Permission naming convention
Permissions follow the existing pattern: `<action> <scope> <entity>` where scope is
`own`, `any` (apiary member), or a role descriptor. The new ones are:
- `edit own apiary` — renamed from current to be owner-only
- `manage apiary members` — entirely new, only for the member management UI
- `delete own hive inspection` — extends current `"own"` to include editors and viewers

### Access handler changes
Each of the 16 handlers calls one of three trait methods:
1. `checkApiaryViewAccess()` — no change (all roles can view)
2. `checkApiaryEditAccess()` — becomes role-dependent; needs new method
   `checkApiaryRoleEditAccess($apiary, $account, 'any_role', 'editor')` where the second
   arg is the minimum role to edit
3. `checkApiaryOwnerDeleteAccess()` — no change (owner-only)

The key new logic path is: given an account and apiary, look up the account's
role in `apiary_memberships`. The trait needs a helper:
```php
protected function getMemberRole(?Apiary $apiary, AccountInterface $account): ?string {
  // Load memberships for this apiary/user pair. Return 'owner' if account is the
  // apiary owner; otherwise check membership rows for role value. NULL if not a member.
}
```

### List builder changes
- Operations column: viewers should still see Edit/Delete but they must be
  access-checked individually (the row's edit link should use the route with
  `_custom_access` that checks the handler). A simpler approach: show operations
  to all members, but let the access check fail silently on the actual route.
  The current `buildOperations()` already does this via `EntityListBuilder`.

### API implications
The JSON:API submodule (`hivelog_api`) will inherit these permission changes
automatically because it goes through the same entity handlers. However, the
"field app" role in `hivelog_api.permissions.yml` (granted to OAuth app tokens)
currently gives broad permissions that may need narrowing if viewer/editor/owner
is introduced. See task 0191's note about cache context parity between web and API.

### Test matrix
For every entity type, test the following account states:
1. Site admin (`administer hivelog`) — always allowed
2. Apiary owner (with `edit own apiary`, `delete own apiary`)
3. Member as editor (no `edit own apiary` or `delete own apiary`)
4. Member as viewer (can only view + delete own)
5. Non-member with `"own"` permission on private apiary — denied
6. Non-member on public apiary with `"own"` permission — allowed to view only

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Decisions:: [[0108-apiary-membership-entity-and-roles]]
- Commits::
