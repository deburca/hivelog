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
- [ ] OAuth keys generated on hvg with `private://` paths (done on the live host), and the Vinculum app pointed at hvg's address
- [x] Data brought over from kbg (database export or a migration), checked afterwards: apiaries, owners, roles, CBR
      numbers, hives, history and files ✅ 2026-10-27 (live kbg's Vand Værk; the six orphaned logs left behind)
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
       apiary. The live copy is below (the live kbg holds different data: Vand Værk only).
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

## Live hivelog.eu (SSH access to the host, `ssh ovh`, from 2026-10-27)
The Vinculum app can be tried only against the live `hivelog.eu` (a phone cannot reach `hivelog.ddev.site` without DDEV's
certificate authority). `sites.php` maps `hivelog.eu` to the same `hvg` site directory as `hivelog.ddev.site`. Project at
`/homez.2186/xvxvpii/verdigris`, drush at `vendor/bin/drush --uri=hivelog.eu` (PHP 8.4, drush 13.8).

State found by a read-only survey on 2026-10-27, before anything was written: the host already ran hivelog 3.1.0 and
beeswax 1.1.1; the live hvg database was at schema 10038 (`updb` had run), `hivelog`, `hivelog_api`, `simple_oauth`,
`consumers` and `jsonapi` were enabled, an OAuth key pair existed under `private/hvg/oauth-keys/` (`simple_oauth.settings`
pointing at `private://oauth-keys/...`), and there were two users: `admin` (uid 1) and `paddy` (uid 3, roles
`administrator` and `hivelog_field_app`). HiveLog data: none.

1. [x] Back up the live hvg and kbg databases ✅ 2026-10-27: `~/backups/hvg-before-hivelog-move-20261027-2138.sql.gz` and
       `~/backups/kbg-before-hivelog-move-20261027-2138.sql.gz` on the host (directory mode 700), made with `drush sql:dump --gzip`.
2. [x] `updb` and `cr`: already done on the live hvg when the survey ran (schema 10038).
3. [x] `hivelog_api` enabled: already done (with `jsonapi`, `consumers` and `simple_oauth`).
4. [x] OAuth keys generated on the live host and the two paths set: already done, `private.key` mode 600, `public.key` 644.
       **To tighten:** the key directory is mode 755, where the runbook says 700. Not yet checked from Vinculum.
5. [x] Data copied from the **live** kbg ✅ 2026-10-27, see below.
6. [x] `paddy` has the **HiveLog mobile app user** role on the live hvg already.
7. [ ] Sign in from Vinculum against `https://hivelog.eu`, record an inspection with a photo, edit and delete one, sign out
       (the host-firewall behaviours from tasks 0212 and 0220 apply to this host too: check them).

### What live kbg holds (it is not the local kbg)
The live `kragebaekgaard.dk` has **2 apiaries**: Vand Værk (id 1: 4 hives VV-01 to VV-04, 5 queens, 2 queen observations,
31 calendar actions, 24 hive components, 32 inventory items, 35 purchases, 2 products, 54 + 6 hive action logs, 16 apiary
action logs, no inspections and no photos) and "Test" (id 4). Ravnholt Home and Søndermarken, in the local copy above, exist only
in the local kbg database. Its users: `admin`, `paddy` (uid 2), `demo`, `test1` to `test3`; Vand Værk's owners were `paddy` and
`test1`, with `test2` and `test3` as novices.

### How the copy was done
kbg and hvg are on **different MySQL servers** on the host (`xvxvpiikbg`, `xvxvpiihivelog`), so the local script's
cross-database reads cannot work. Instead:
1. a fresh `drush sql:dump --tables-list=` of kbg's 20 HiveLog tables, table names prefixed with `kbgsrc_` by `sed`, loaded
   into the hvg database as **scratch tables**;
2. `cms2/hivelog-move-live.php` (untracked in cms2; options `APIARIES`, `KBG_USERS`, `EXTRA_TO_PADDY`, `KBG_PADDY_CBR`,
   `DEST_USER`, `SKIP_ORPHANS`) reads the scratch tables, keeps the destination's own `paddy` account, copies Vand Værk and
   everything under it with the ids kept in one transaction, and checks the columns match, every count, and that every entity
   validates; it is a dry run unless `DRY_RUN=0`, and stops on a row owned by an unmapped user or on any photo row;
3. a dry run first on a copy of the local hvg database (against a dump of live kbg), then on the live hvg, then the real run
   (`DRY_RUN=0 SKIP_ORPHANS=1`); then the 20 scratch tables were dropped and the script and the dump files removed from the host.

### Six action logs left behind
The dry run found six hive action logs on VV-01 (ids 1, 2, 3, 5, 12, 17; 2026, five `done` and one `ignored`) whose calendar
action (ids 1, 2, 3, 5, 12, 17) **no longer exists on kbg**: Vand Værk's calendar actions there start at id 4. Copied, they fail
validation. They were left behind with `SKIP_ORPHANS=1`; they stay on kbg, and the jobs can be reported again on hivelog.eu.

### Result and verification (2026-10-27)
Every count matched the source, and 0 validation violations: 1 apiary, 1 membership (`paddy`, owner), 4 hives, 5 queens,
2 queen observations, 31 calendar actions (+2 requirements, +2 product yields), 24 hive components, 32 inventory items,
35 purchases, 1 inventory usage, 2 products, 2 harvest yields, 54 hive action logs and 16 apiary action logs. Checked again on
the live database afterwards: `paddy` is the primary owner with CBR `4592017570` and can view and update the apiary; each hive
shows its queen and an empty weight (4.2, 5.0, 4.1, 5.0 kg); the alert collector builds. Live kbg is unchanged. Left behind on
purpose: the Test apiary, the test users and their memberships.

### Still to do
- Sign in at `https://hivelog.eu` as `paddy` and look through the pages; then Vinculum (step 7).
- Decide whether Ravnholt Home and Søndermarken (local only) should be brought to hivelog.eu from the local hvg.
- Step 3 of the plan (compare roles and permissions kbg and hvg), then step 6 (retire kbg's HiveLog; update `AGENTS.md`, 0233, 0235).
- Tighten the key directory to mode 700.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0233-roll-out-3-0-x-to-the-sites]]
- Commits::
