---
type: task
tags: [hivelog/task]
status: in-progress
priority: low
project: "[[shared-apiaries-and-team-roles]]"
area: install
created: 2026-10-26
branch:
release:
depends-on: ["[[0233-roll-out-3-0-x-to-the-sites]]"]
blocked-by:
---
# Task: Move HiveLog hosting from kbg to hvg

## Context
Decided 2026-10-26: the **hvg** site will supersede kbg (`kragebaekgaard.ddev.site`, `kragebaekgaard.dk`) as the host
for HiveLog. Until hvg is installed and configured, kbg stays the only site that runs and is tested with it.

## Acceptance criteria
- [ ] hvg's local and web hostnames recorded here
- [ ] hvg has the module and its dependencies installed (geofield, leaflet, and key and simple_oauth if `nexus` and
      `hivelog_api` are used), its theme and menu set up, and the HiveLog permissions given to its roles
- [ ] OAuth keys generated on hvg with `private://` paths, and the Vinculum app pointed at hvg's address
- [ ] Data brought over from kbg (database export or a migration), checked afterwards: apiaries, owners, roles, CBR
      numbers, hives, history and files
- [ ] `'demo'` in `file_scan_ignore_directories` kept in hvg's `settings.php` (already added); the testing rule
      "HiveLog is tested only on its host" moves to hvg, and `AGENTS.md` and tasks 0233 and 0235 are updated
- [ ] kbg's HiveLog retired or left read-only, as decided when this starts

## Where things stand (surveyed 2026-10-27, read-only)
All sites share one codebase (cms2), so hvg already runs HiveLog 3.0.2's code; its database has not been updated to it.

| | kbg | hvg |
|---|---|---|
| Hostnames | `kragebaekgaard.ddev.site`, `kragebaekgaard.dk` | `hivelog.ddev.site`, `hivelog.eu`, `www.hivelog.eu` |
| Database | `kbg` | `hivelog` |
| HiveLog schema | 10038 (3.0.2) | **10031 (2.11.2): `updb` has not run on it** |
| HiveLog modules enabled | `hivelog`, `hivelog_api`, `collective`, `nexus` (+ `key`, `consumers`, `simple_oauth`, `jsonapi`) | `hivelog` only |
| HiveLog data | 4 apiaries, 13 hives, 15 inspections, 9 memberships, 13 files (6.9 MB) | none (0 apiaries, 0 hives) |
| Users | admin, paddy, vinculum_test, test1, test2, test3 | admin only |
| Theme | | `beeswax` default (Gin admin) |
| `file_scan_ignore_directories` | set | set |

- hvg has no HiveLog data to lose, so the move is a copy in, not a merge. kbg's volume is small (about 40 records), and
  some of its users are test accounts.
- hvg's config sync directory (`config/hvg/sync`) already carries a `hivelog_user` role; the `hivelog_field_app` role
  does not exist there until `hivelog_api` is enabled.
- Nothing on hvg is used by the Vinculum app yet; the app takes the server address at sign-in.

## Plan
1. [ ] hvg to 3.0.2: back up, `updb` (runs 10032 to 10038 on an empty HiveLog), `cr`; status report clean.
2. [ ] Enable on hvg what kbg runs: `hivelog_api` (and `jsonapi`, `consumers`, `simple_oauth`, `key`), and `collective` and
       `nexus` if wanted; give the **HiveLog mobile app user** role to the people who use the app.
3. [ ] Roles and permissions: compare kbg's HiveLog permissions per role with hvg's and copy what is wanted.
4. [ ] OAuth keys on hvg (`private://` paths), then sign in from Vinculum against `hivelog.ddev.site`, then `hivelog.eu`.
5. [ ] Data: decide which of kbg's data is real and what to carry over (see the open decision), then copy it and check
       apiaries, owners and roles, CBR numbers, hives, history and files.
6. [ ] Retire kbg's HiveLog (or leave it read-only), then update `AGENTS.md`, 0233 and 0235 for "tested on hvg".

**Open decision:** how to bring the data over. (a) Re-enter it on hvg by hand, which fits about 40 records and drops
the test users. (b) A scripted copy of kbg's HiveLog tables, files and the real users, with their ids mapped onto hvg's.
(c) Clone kbg's database over hvg's, which would also replace hvg's own content (landing page, admin account).

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0233-roll-out-3-0-x-to-the-sites]]
- Commits::
