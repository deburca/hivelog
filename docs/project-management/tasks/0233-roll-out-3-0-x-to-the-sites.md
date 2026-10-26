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
# Task: Roll 3.0.x out to the sites and check it there

## Context
3.0.0 and 3.0.1 are released and the development site (kbg) runs them, but the other sites and the live host have not
been updated. 3.0.0 carries a long update chain (`hivelog_update_10032` to `_10038`: memberships from the old
beekeepers field, role renames, history stamps, transfer flag, `owner_since`) that has run only on kbg and on test
data. 3.0.1 has no schema change.

## Acceptance criteria
- [ ] A database snapshot or backup of each site before its update
- [ ] `updb` and `cr` run on the live host, then on hvg, shh and default, each with no errors
- [ ] Afterwards, on each site: every apiary has an `owner` membership, old beekeepers rows are `novice`, no apiary
      is left without an owner, and the apiary list shows the right owner and CBR number
- [ ] A hive move tried on the live host, at desktop and phone width, with a component and a sensor: the dialog
      reads well, history stays with the old apiary, and the cost and yield report is right for both
- [ ] A hand-over tried on the live host (add an owner, remove the first), the CBR number follows
- [ ] OAuth keys and the Vinculum sign-in still work after the update (`private://` key paths)
- [ ] Anything found is recorded here or as its own task

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]], [[0231-apiary-history-stamping-and-hive-move]], [[0232-apiary-cbr-number-from-primary-owner]]
- Commits::
