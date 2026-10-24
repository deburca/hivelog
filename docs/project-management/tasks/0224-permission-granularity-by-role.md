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
2. **Owners are the memberships with role `owner`, and there can be several at once.**
   Ownership is handed over like this: owner A adds B with role `owner`; at some later time
   (immediately, or six months on) B removes A. Access therefore comes from the current
   membership alone, never from the apiary's `uid` field and never from having created a
   record. A previous owner or member keeps no right to edit or delete what they created
   while they held a role. This replaces the creator-based "delete own" rules.
3. **Only owners manage members.** The member role cannot add, edit or remove members.
   Owners may remove other owners; the last owner cannot remove themselves (and cannot be
   removed), so an apiary always has an owner.

**What an owner can do:** everything, including add, edit and delete hives.

**What the member role can do**
4. **Hives:** edit. It cannot add or delete a hive. Moving a hive to another apiary is
   allowed in principle, but see decision 10 and open question 3.
5. **Queens:** edit. Only an owner creates a queen record (and deletes one); the member role
   attaches a queen to an existing hive by editing it, and may detach the old queen from
   it too (if it can attach, it can detach).
6. **Inspections and queen observations:** full access, add, edit and delete, any record in
   the apiary. With 4 and 5 this also settles the parent-access conflict: creating an
   inspection needs `update` on its hive and an observation needs `update` on its queen,
   and the member role has both.
7. **Calendar:** can report jobs as done or ignored. Calendar *actions* (the plan) are
   owner-only.
8. **Inventory:** only an owner adds purchases, because that spends money and the owner
   should carry that responsibility. The member role can use items in stock; "single-use"
   means the existing `consumable` item type. Inventory items and products are otherwise
   owner-only.
9. **Sensor readings:** every member sees them.

**History is never rewritten**
10. When a hive moves from one apiary to another, no existing record is changed. A record
    carries the apiary, hive and user it was written under; after the move, new records
    carry the target apiary. Hive history and apiary history both stay intact. Whoever
    moves a hive needs the permission to add a hive in the **target** apiary.

**To consider (a separate task, not part of 0224):** let a hive inspection record product
usage, for example 5 kg of sugar, so that stock is used where the work is done and not
only when a calendar job is reported.

**Owner-only means:** an owner has read, write and delete; the member role has read only.
Owner-only areas, so far: calendar actions and their requirement and yield estimates, hive
components, queen creation and deletion, inventory items, purchases and products, the
apiary's own settings, and the submodules' management screens (sensors, providers).

## Core areas to consider
These are all the core entity types and areas, so nothing is missed. The last column is a
proposed default for confirmation, built from the decisions above.

| Area | Owner | Member role |
|---|---|---|
| Apiary (name, location, visibility, AI toggle) | read, write, delete | read |
| Apiary membership | read, write, delete | none |
| Hive | add, edit, delete | read, edit (moving it: Q3) |
| Hive inspection | full | full |
| Queen | add, edit, delete | read, edit (attach and detach) |
| Queen observation | full | full |
| Calendar action (the plan) | full | read |
| Hive and apiary action logs (reporting a job) | full | report; edit or delete? (Q9) |
| Calendar action item requirement and product yield (estimates) | full | read |
| Hive component | full | read |
| Inventory item and product | full | read |
| Inventory purchase | add, edit, delete | read |
| Inventory usage and harvest yield | full | use items in stock (Q8) |
| Reports: per-apiary inventory cost report, combined financial report | read | read? (Q10) |
| Dashboard and "needs attention" alerts, full calendar page | read | read |
| Sensor devices (nanoprobe) | full | read |
| Sensor readings and daily rollups | read | read (all members) |
| Hive insights (nexus) | read | read? (Q11) |
| API clients and AI providers (site-wide, not apiary-scoped) | administrators only | none |
| Photos and files attached to records | follow the record | follow the record |

## Open questions
Only what is still undecided. Each says what it blocks.

**Roles and ownership**
1. What is the member role called? (Suggested: Contributor.) *Blocks the update hook, UI
   text and translations.*
2. With several owners, what is `apiary.uid` for? Access must not use it. Options: keep it
   as "created by" for history only, or drop the owner field from the form. About seven
   places read the owner today (`getOwnerId()`: the access trait, `Apiary::isApiaryMember()`,
   the apiary page summary and the two site-level handlers), plus lists and the API that
   narrow by owner. An update hook must also create an `owner` membership for every
   existing apiary's current owner, or those owners lose access the moment access moves to
   memberships. *Blocks the access trait, the owner field and the migration.*

**Moving a hive between apiaries**
3. Decision 4 lets the member role move a hive, but decision 10 says the mover needs the
   permission to add a hive in the target apiary, and adding hives is owner-only. These
   conflict. Which holds: (a) a move needs an owner of the target apiary (so a member can
   only move a hive into an apiary they own), or (b) a separate "receive a hive" right
   that the member role has in apiaries where it is a member? *Blocks the parent-access
   rule and the hive form.*
4. To keep each apiary's history intact, a record has to carry the apiary it was written
   under. Today inspections, queen observations, hive action logs, inventory usage,
   harvest yields and hive insights carry only a hive, so after a move they would follow
   the hive to the new apiary. Which record types get an `apiary` stamped when they are
   written? Existing rows would be filled in from the hive's current apiary by an update
   hook. *Blocks the data model and the access trait.*
5. Who sees a moved hive's history? My assumption: members of the new apiary see all of
   it, including what was written before the move; members of the old apiary keep seeing
   what was written under their apiary, and nothing after. Costs and usage figures written
   under the old apiary: also visible to the new apiary's members?
6. Current-state data cannot be history: a hive's components use items from its own
   apiary's inventory, its sensors are scoped to it, and its queens go with it. On a move,
   which are blocked (the move is refused while they exist), carried over, or detached?

**Queens**
7. "Detach" a queen: mark her inactive and keep her link to the hive (her observations still
   appear on the hive page, in keeping with history), or clear the hive link entirely? I
   read "add a new queen" as "attach an existing queen record", since only owners create
   them; please confirm.

**Calendar and inventory**
8. Inspection usage: should the inspection form get a repeatable "items used" part (item and
   quantity) that writes inventory usage? Usage today has no screen of its own and links
   to a hive or apiary calendar log only. It would need a new link to the inspection, a
   stock check, and a rule for what happens to the usage when an inspection is edited or
   deleted. Is this a separate task done after 0224, and is 0224 allowed to ship without
   it (usage then only through reported jobs)?
9. Calendar logs: may the member role also edit or delete a log that was already reported,
   or only create one?
10. Does the member role need to see prices and costs (product price, purchase amounts, the
    inventory cost and financial reports, a queen's purchase cost)?

**Other areas**
11. Insights (nexus): do all members see the AI insight and a hive's verdict, like sensor
    readings? And may they see the apiary's AI-insights setting?
12. Hive components: read-only for the member role, but adding or removing a super is
    day-to-day work and changes the net colony weight. Keep it owner-only?
13. Should some fields stay owner-only on records the member role can edit, for example a
    queen's purchase cost and purchase date?

**Rollout**
14. Existing members: all were migrated to `viewer`, and tests and the UI have created some
    `editor` rows. Both map to the member role. Confirm, and note that existing members
    will lose rights they had before roles (adding hives and queens, editing the calendar
    plan, managing inventory).
15. Do roles add to the site permissions (a member needs the apiary role *and* the site
    permission), or replace them? I assume add. With roles doing the work, do the
    per-entity "own" permissions stay?
16. Public apiaries: a non-member with the site permission stays view-only (default)?
17. The API's field-app role holds broad permissions. Should it be narrowed, and should
    app tokens follow these roles through the same handlers (default: yes)?
18. This changes what existing members can do and moves access off the owner field, so it
    is a behaviour change on upgrade. Is it a minor or a major release, and does it need
    upgrade notes?

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
