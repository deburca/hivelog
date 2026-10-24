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

## Decisions (2026-10-24)
Recorded from the owner's review of this task after 0223 shipped. They supersede the
role table above where they differ; the table is kept only until the open questions
below are answered and it can be rewritten.

1. **The `viewer` role is a misnomer.** The role is active, not read-only: it adds and
   edits inspections and queen observations. It will be renamed (name still to be
   chosen; see Open questions). Renaming a stored `list_string` value needs an update
   hook that rewrites existing rows, not just a new allowed value.
2. **Only the owner manages members.** Members, of any role, cannot add, edit or remove
   members. (Already how 0223 behaves; it stays.)
3. **Queens, calendar actions and hive components are owner-only.**
4. **Submodule areas are owner-only** (sensors, insights and the like).

## Open questions
Answer these before the role table is rewritten and the handlers are changed. Each is
marked with what it blocks.

**Roles**
1. Do we keep three roles (owner, editor, renamed viewer) or two (owner and the renamed
   role)? With queens, calendar, components and submodules all owner-only, it is not
   clear what an editor can do that the renamed role cannot. *Blocks the whole table.*
2. What is the renamed role called? *Blocks the update hook, UI strings, translations.*
3. Is the apiary's `uid` owner the same as a membership with role `owner`? Today the
   membership access handler only checks the `uid` owner, so a member given role `owner`
   cannot manage members. Are co-owners allowed, and may they manage members?

**What the renamed role may do**
4. Hives: may it add, edit or delete hives, or only view them?
5. Inspections and queen observations: may it edit and delete other people's, or only its
   own? (The API today lets a member delete only their own.)
6. Calendar: the calendar *actions* (the plan) are owner-only, but may the renamed role
   *report* them (done or ignored) through the hive and apiary action logs? That is the
   everyday way people tick off jobs. *Blocks the calendar handlers.*
7. Inventory, products, purchases, usage and yields: owner-only too? Note that reporting a
   job as done can write inventory usage and harvest yield as a side effect, so a log
   permission without an inventory permission would conflict.

**Owner-only areas: hidden or read-only?**
8. For queens, calendar, components, inventory and the submodule panels, does "owner-only"
   mean the renamed role cannot *change* them but still sees them, or cannot see them at
   all? The hive page shows queen details, components, empty weight and net colony
   weight, so hiding them would change that page. *Blocks list builders and page panels.*
9. "Such as": are there other core areas beyond queens, calendar and components that
   should be owner-only? (Apiary edit and delete are already owner-only.)
10. Submodules: owner-only to *manage* sensors and providers, but may members see the
    readings, stat tiles and insights for their apiary?

**A technical conflict to resolve**
11. Creating an inspection needs `update` on its hive, and a queen observation needs
    `update` on its queen, because of the parent-access constraint (task 0133 and
    `HivelogParentAccess`). If members may not edit hives or queens, they cannot add
    inspections or observations. Do we introduce a separate "contribute" level that the
    constraint accepts for those two child types, or allow `update` on the parent?
    *Blocks the constraint and the API.*

**Existing data and rollout**
12. `hivelog_update_10032` migrated every old beekeeper to `viewer`, and before roles are
    enforced they could edit everything. Which role should existing members get, and
    should an update hook do it? Enforcing roles will otherwise change what they can do.
13. Public apiaries: does a non-member with the "own" permission stay view-only?
14. Do roles *add to* the site permissions (a member needs both the apiary role and the
    site permission), or replace them? The task assumes the former.
15. The API field-app role holds broad permissions (including `edit own apiary|hive|queen`).
    Should it be narrowed once roles exist, and how should app tokens for non-owners be
    checked?

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
