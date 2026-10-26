---
type: task
tags: [hivelog/task]
status: todo
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
not run it and are not part of this task. 3.0.0 carries a long update chain (`hivelog_update_10032` to `_10038`: memberships from the old
beekeepers field, role renames, history stamps, transfer flag, `owner_since`) that has run only locally and on test
data. 3.0.1 has no schema change.

## Acceptance criteria
- [ ] A database backup of the live site before its update
- [ ] `updb` and `cr` run on the live site with no errors
- [ ] Afterwards, on the live site: every apiary has an `owner` membership, old beekeepers rows are `novice`, no apiary
      is left without an owner, and the apiary list shows the right owner and CBR number
- [ ] A hive move tried on the live site, at desktop and phone width, with a component and a sensor: the dialog
      reads well, history stays with the old apiary, and the cost and yield report is right for both
- [ ] A hand-over tried on the live site (add an owner, remove the first), the CBR number follows
- [ ] OAuth keys and the Vinculum sign-in still work after the update (`private://` key paths)
- [ ] Anything found is recorded here or as its own task

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]], [[0231-apiary-history-stamping-and-hive-move]], [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
