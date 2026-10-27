---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[shared-apiaries-and-team-roles]]"
area: install
created: 2026-10-26
branch:
release: 3.0.1
depends-on: []
blocked-by:
---
# Task: Roll 3.0.x out to the live site and check it there

## Context
3.0.0 and 3.0.1 are released and the local site (`kragebaekgaard.ddev.site`) runs them, but the live site
(`kragebaekgaard.dk`) has not been updated. HiveLog is tested exclusively on kbg; the other sites of the multisite do
not run it and are not part of this task. hvg took over as host on 2026-10-27 ([[0236-move-hivelog-hosting-from-kbg-to-hvg]]) and kbg's HiveLog was
uninstalled, so this rollout was to kbg and the move to hvg was its own task. 3.0.0 carries a long update chain (`hivelog_update_10032` to `_10038`: memberships from the old
beekeepers field, role renames, history stamps, transfer flag, `owner_since`) that has run only locally and on test
data. 3.0.1 has no schema change.

## Acceptance criteria
- [x] A database backup of the live site before its update ✅ 2026-10-26
- [x] `updb` and `cr` run on the live site with no errors ✅ 2026-10-26
- [x] Afterwards, on the live site: every apiary has an `owner` membership, old beekeepers rows are `novice`, no apiary ✅ 2026-10-26
      is left without an owner, and the apiary list shows the right owner and CBR number
- [x] A hive move tried on the live site, at desktop and phone width, with a component and a sensor: the dialog ✅ 2026-10-26
      reads well, history stays with the old apiary, and the cost and yield report is right for both
- [x] A hand-over tried on the live site (add an owner, remove the first), the CBR number follows ✅ 2026-10-26
- [x] OAuth keys and the Vinculum sign-in still work after the update (`private://` key paths) ✅ 2026-10-26
- [x] Anything found is recorded here or as its own task ✅ 2026-10-26

## Test checklist (live site)
Tick each item as it passes. Anything that fails goes under "Findings" below.

### Before the update
- [x] Database backup taken ✅ 2026-10-26
- [x] Noted the number of apiaries and the `apiary_membership` rows with their roles ✅ 2026-10-26
- [x] Noted which users are in the old `beekeepers` field (they become novices) ✅ 2026-10-26

### The update
- [x] `drush updb -y` finishes with no errors (watch `hivelog_update_10032`) ✅ 2026-10-26
- [x] `drush cr` done ✅ 2026-10-26
- [x] Re-running `updb` is a no-op ✅ 2026-10-26
- [x] Status report (`/admin/reports/status`) shows no hivelog errors ✅ 2026-10-26
- [x] Drupal log (`/admin/reports/dblog`) shows no new errors ✅ 2026-10-26

### Data after the update
- [x] Every apiary has an `owner` membership; none is left without an owner ✅ 2026-10-26
- [x] Old `beekeepers` rows are now `novice` ✅ 2026-10-26
- [x] Old `editor` roles are now `keeper` ✅ 2026-10-26
- [x] `owner_since` is filled in for every owner ✅ 2026-10-26
- [x] Apiary list shows the right owner and CBR number per apiary ✅ 2026-10-26
- [x] Inspections, queen observations and hive action logs carry an `apiary` stamp equal to their hive's apiary ✅ 2026-10-26

### Roles in the browser
- [x] Novice: reads everything, sees no add/edit/delete buttons, gets a 403 on the scoped add routes ✅ 2026-10-26
- [x] Beekeeper: can add hives, calendar actions, inventory and logs ✅ 2026-10-26
- [x] Beekeeper: cannot reach the apiary edit form, Members, or delete ✅ 2026-10-26
- [x] Owner: can do all of it, including Members (Access tab of the Apiary edit form) ✅ 2026-10-26
- [x] Non-member on a public apiary: reads it only ✅ 2026-10-26
- [x] Former beekeepers who should keep editing have been promoted to Beekeeper ✅ 2026-10-26

### Ownership and CBR number
- [x] Hand-over: add B as owner, remove A; the CBR number is A's, then B's once A is removed ✅ 2026-10-26
- [x] The last owner cannot be removed or given another role ✅ 2026-10-26
- [x] Three owners shows the warning on the apiary page ✅ 2026-10-26
- [x] A new apiary gives its creator an `owner` membership ✅ 2026-10-26

### Hive move (desktop width)
- [x] Moving a hive with a component and a sensor: the dialog reads well ✅ 2026-10-26
- [x] Move the parts with the hive ✅ 2026-10-26
- [x] Detach the parts first ✅ 2026-10-26
- [x] Cancel leaves everything unchanged ✅ 2026-10-26
- [x] The Hive form's apiary field is read-only and "Move to another apiary" is offered ✅ 2026-10-26
- [x] Old apiary's members still see what was written under it ✅ 2026-10-26
- [x] New apiary's members read the whole history ✅ 2026-10-26
- [x] Moved component: item created in the target apiary, purchase there, negative `transfer` purchase in the source ✅ 2026-10-26
- [x] Cost and yield report is right for both apiaries (3.0.1 fix) ✅ 2026-10-26
- [x] Dashboard "inspections this month" is right for both apiaries (3.0.1 fix) ✅ 2026-10-26

### Hive move (phone width)
- [x] The dialog is usable and reads well ✅ 2026-10-26

### API and Vinculum
- [x] OAuth keys use `private://` paths; the status report's "OAuth keys" entry is green ✅ 2026-10-26
- [x] Vinculum sign-in works ✅ 2026-10-26
- [x] A novice's token is read-only ✅ 2026-10-26
- [x] A write as `application/json` works through the host firewall ✅ 2026-10-26
- [x] A photo upload as multipart works ✅ 2026-10-26
- [x] `PATCH`/`DELETE` via `X-HTTP-Method-Override` works ✅ 2026-10-26
- [x] Deleting an own inspection or queen observation from the app follows the web rules ✅ 2026-10-26
- [x] Sign-out works ✅ 2026-10-26
- [x] `GET /hivelog/api/v1` returns the discovery document ✅ 2026-10-26

### Quick regression
- [x] Dashboard loads ✅ 2026-10-26
- [x] Apiary, hive, inspection and calendar pages load ✅ 2026-10-26
- [x] No errors from `ApiaryMemberManagerForm` (type error fixed in 0228) ✅ 2026-10-26

### Findings
- **Sign-in stopped with "The 'grant simple_oauth codes' permission is required".** Simple OAuth checks that
  permission against the person signing in, and it sits on the `hivelog_field_app` role, which install creates
  but gives to nobody. The test user had not been given the role. Giving it to the user fixed sign-in. The role
  does not widen what the person can do: a token's roles are the scope's intersected with the user's own, and
  the apiary role still applies, so a novice with the role stays read-only through the API.
- **The role's name did not say what it was for.** Renamed "HiveLog mobile app user" in
  [PR 163](https://github.com/deburca/hivelog/pull/163): new installs get the new label and
  `hivelog_api_update_10005()` renames the old default, leaving a label a site chose alone. Not yet released, so
  it reaches kbg with the next release and its `updb`.
- **Rollout step for any site:** after enabling `hivelog_api`, give the `hivelog_field_app` role to every person
  who may use the app (the README now says so). A user who was using the app before the update already has it;
  3.0 does not touch the role.
- Nothing else failed in the checklist.
- **3.0.2 rolled out to kbg (2026-10-27):** `updb` ran `hivelog_api_update_10005`; the People page shows the role as
  "HiveLog mobile app user".

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]], [[0231-apiary-history-stamping-and-hive-move]], [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
