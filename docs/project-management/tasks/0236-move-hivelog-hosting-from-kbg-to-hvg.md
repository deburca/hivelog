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
1. [x] hvg to 3.0.2: backed up, `updb` (10032 to 10038 on an empty HiveLog, plus the pending updates of tagify, ECA, menu_link_attributes and trash), `cr` ✅ 2026-10-27 (local DDEV). Backups: `cms2/hvg-before-hivelog-move.sql.gz`, `cms2/kbg-before-hivelog-move.sql.gz`.
2. [x] Enable on hvg what kbg runs: `hivelog_api` with `jsonapi`, `consumers` and `simple_oauth` ✅ 2026-10-27 (local DDEV). `collective`
       and `nexus` hold no data on kbg (0 API clients, 0 AI providers, 0 insights) and are left off unless wanted; give the **HiveLog mobile app user** role to the people who use the app.
3. [ ] Roles and permissions: compare kbg's HiveLog permissions per role with hvg's and copy what is wanted.
4. [ ] OAuth keys on hvg (`private://` paths), then sign in from Vinculum against `hivelog.ddev.site`, then `hivelog.eu`.
       Local hvg done 2026-10-27: a key pair in `private/hvg/oauth-keys/` (directory 700, keys 600, outside the web root),
       `simple_oauth.settings` `private_key` and `public_key` set to `private://oauth-keys/private.key` and `.../public.key`
       (as on kbg). The status report shows "HiveLog API: OAuth keys: Configured" and the discovery document answers
       (`api_version` 1, features `json_write`, `multipart_upload`, `method_override`). Not yet done: the sign-in from Vinculum
       (give `paddy` the **HiveLog mobile app user** role first if the app should work for a non-administrator), and the
       same on the live `hivelog.eu`, where the keys are generated on that host, never copied. The two paths are now active
       config on hvg; they are not in `config/hvg/sync` until exported.
5. [x] Data copied to the **local** hvg (`hivelog.ddev.site`) on 2026-10-27 by `cms2/hivelog-move.php` (a dry run first, then
       `DRY_RUN=0`; kbg is only read). Arrived with ids kept: 3 apiaries (1 Vand Værk, 4 Ravnholt Home, 5 Søndermarken), 12 hives,
       5 inspections, 5 queens, 1 queen observation, 75 calendar actions (+2 product yields), 2 inventory items, 1 purchase,
       2 products, 3 harvest yields, 2 hive and 4 apiary action logs, and 3 memberships. Every count matched kbg's, and all
       copied entities validate with 0 violations. kbg's `admin` and `paddy` became one hvg account, `paddy` (uid 2, roles
       `content_editor` and `administrator`, kbg's password hash, CBR number `4592017570`), who is `owner` of all three
       apiaries and shows as their primary owner. Søndermarken's notes carry "Former CBR number (kbg admin account): 4471-092".
       Left behind: apiary 10 and its records, `vinculum_test`, `test1` to `test3`, and the novice memberships (admin's on
       apiaries 1 and 4, the test users' on 4). No photos needed copying: all seven inspection images belong to the test
       apiary. Still to do for this step: the same copy on the **live** hivelog.eu, and a look through the pages as paddy.
6. [ ] Retire kbg's HiveLog (or leave it read-only), then update `AGENTS.md`, 0233 and 0235 for "tested on hvg".

**Decided 2026-10-27:** a scripted copy. Copy `paddy`; map kbg's `admin` onto the same hvg account (one person); leave the
test apiary (10, "Vinculum test apiary") and the test users (`vinculum_test`, `test1` to `test3`) behind. Copies apiaries 1, 4
and 5 (12 hives, 5 inspections, queens, 75 calendar actions, 2 inventory items) with ids kept, since hvg has no HiveLog rows;
only users and files are mapped. hvg's own table columns match kbg's (checked after `updb`).

**Open question found while preparing the copy:** the CBR number lives on the user, and the two kbg accounts carry different
numbers: `admin` (owns Søndermarken) has `4471-092`, `paddy` (owns Vand Værk and Ravnholt Home) has `4592017570`. One account can
hold one number, so merging them makes Søndermarken show paddy's number.

**Earlier open decision (settled above):** how to bring the data over. (a) Re-enter it on hvg by hand, which fits about 40 records and drops
the test users. (b) A scripted copy of kbg's HiveLog tables, files and the real users, with their ids mapped onto hvg's.
(c) Clone kbg's database over hvg's, which would also replace hvg's own content (landing page, admin account).

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0233-roll-out-3-0-x-to-the-sites]]
- Commits::
