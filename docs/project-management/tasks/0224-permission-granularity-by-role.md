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
role table above wherever they differ; that table stays only until the open questions
below are answered and it can be rewritten.

**Roles**
1. **Two roles, not three:** `owner`, and one other role (name still to be chosen, called
   "the member role" below). The old `viewer` and `editor` values go. The member role is
   active, not read-only. Renaming stored role values needs an update hook that rewrites
   existing rows.
2. **The apiary owner and the membership with role `owner` are the same thing.** That makes
   ownership transferable from owner to owner. It follows that **access comes only from
   the current membership, never from having created a record**: a previous owner or
   member keeps no right to edit or delete what they created while they held a role.
   (This replaces the creator-based "delete own" rules.)
3. **Only the owner manages members.** The member role cannot add, edit or remove members.

**What the owner can do:** everything, including add, edit and delete hives.

**What the member role can do**
4. **Hives:** edit (not add, not delete).
5. **Queens:** edit (write access to edit a queen).
6. **Inspections and queen observations:** full access, add, edit and delete, any record in
   the apiary. (With 4 and 5 this also settles the parent-access conflict: creating an
   inspection needs `update` on its hive and an observation needs `update` on its queen,
   and the member role has both.)
7. **Calendar:** can report jobs as done or ignored. Calendar *actions* (the plan) are
   owner-only.
8. **Inventory:** the member role does the day-to-day work, so it has whatever inventory
   permissions that work needs (see open question 6). Inventory items, purchases and
   products are otherwise owner-only.
9. **Sensor readings:** every member sees them.

**Owner-only means:** the owner has read, write and delete; the member role has read only.
Owner-only areas, so far: calendar actions and their requirement and yield estimates, hive
components, queen creation and deletion, inventory items, purchases and products, the
apiary's own settings, and the submodules' management screens (sensors, providers).

## Core areas to consider
These are all the core entity types and areas, so nothing is missed. The last column is a
proposed default, for confirmation, built from the decisions above.

| Area | Proposed owner | Proposed member role |
|---|---|---|
| Apiary (name, location, visibility, AI toggle, owner) | read, write, delete | read |
| Apiary membership | read, write, delete | none |
| Hive | add, edit, delete | read, edit |
| Hive inspection | full | full |
| Queen | add, edit, delete | read, edit |
| Queen observation | full | full |
| Calendar action (the plan) | full | read |
| Hive and apiary action logs (reporting a job) | full | add, edit, delete? (Q5) |
| Calendar action item requirement and product yield (estimates) | full | read |
| Hive component | full | read |
| Inventory item, purchase, product | full | read |
| Inventory usage and harvest yield (written when a log is reported) | full | written by the reporting form (Q6) |
| Reports: per-apiary inventory cost report, combined financial report | read | read? (Q9) |
| Dashboard and "needs attention" alerts, full calendar page | read | read |
| Sensor devices (nanoprobe) | full | read |
| Sensor readings and daily rollups | read | read (all members) |
| Hive insights (nexus) | read | read? (Q10) |
| API clients and AI providers (site-wide, not apiary-scoped) | administrators only | none |
| Photos and files attached to records | follow the record | follow the record |

## Open questions
Only what is still undecided. Each says what it blocks.

**Roles and ownership**
1. What is the member role called? (Suggested: Contributor.) *Blocks the update hook, UI
   text and translations.*
2. When ownership is transferred, what happens to the previous owner: removed, or kept as
   a member? Decision 2 says they keep no rights over what they created, which suggests
   removed. Is there a transfer action, and is it one owner at a time, so that `apiary.uid`
   and the one `owner` membership are always the same person? *Blocks the owner field,
   the members UI and the migration.*
3. The apiary owner field is editable today. Under decision 2 it should change the
   `owner` membership with it (or the owner field becomes the transfer action). Which?

**What the member role may do**
4. Queens: it can edit a queen but not add or delete one. Replacing a queen is ordinary
   beekeeping (a new queen goes in, the old one becomes inactive). Is adding the new queen
   owner-only, or may the member role add queens too? And may it move a queen to another
   hive, or change a hive's apiary (moving a hive to another apiary)?
5. Calendar logs: reporting a job creates a log. May the member role also *edit or delete*
   a log it (or anyone) has already reported, or only create one?
6. Inventory: what exactly does the day-to-day work need? Reading items and products is
   covered. Reporting a job as done writes inventory usage and harvest yield as a side
   effect; I propose the reporting form may write those even though the member role has no
   inventory write access. Does it also need to **add a purchase** (buying sugar
   mid-season) or edit usage afterwards?
7. Hive components: read-only for the member role, but adding or removing a super is
   day-to-day work and changes the net colony weight. Keep it owner-only?
8. Hive fields: should some hive or queen fields stay owner-only even though the member
   role can edit the record, for example a queen's purchase cost and purchase date?

**Visibility**
9. Money: may the member role see prices and costs (product price, purchase amounts,
   the inventory cost and financial reports, a queen's purchase cost)?
10. Insights (nexus): do all members see the AI insight and the hive's verdict, like
    sensor readings? And may they see the apiary's AI-insights setting?

**Rollout**
11. Existing members: all were migrated to `viewer`, and tests and the UI have created
    some `editor` rows. Both map to the member role. Confirm, and note that existing
    members will lose rights they had before roles (adding hives and queens, editing the
    calendar plan, managing inventory).
12. Do roles add to the site permissions (a member needs the apiary role *and* the site
    permission), or replace them? I assume add. A related question: with roles doing the
    work, do the per-entity "own" permissions stay?
13. Public apiaries: a non-member with the site permission stays view-only (default)?
14. The API's field-app role holds broad permissions. Should it be narrowed, and should
    app tokens follow these roles through the same handlers (default: yes)?
15. This changes what existing members can do, so it is a behaviour change on upgrade.
    Is it a minor or a major release, and does it need a note in the upgrade notes?

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
