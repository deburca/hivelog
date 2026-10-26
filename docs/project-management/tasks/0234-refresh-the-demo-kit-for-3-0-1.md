---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[ios-field-app]]"
area: install
created: 2026-10-26
branch:
release:
depends-on: ["[[0215-demo-site-kit]]"]
blocked-by:
---
# Task: Refresh the demo kit for 3.0.1 and clear its security advisories

## Context
The demo kit's lock was moved to hivelog 3.0.1 by hand (the lock file only; Composer was not run), so the demo site
has not been rebuilt on it. Roles, memberships and the hive-move dialog are new since the kit was built, so its seed
and its end-to-end check have not been exercised against them. The lock also carries five known security advisories
in its dependencies.

## Acceptance criteria
- [ ] `composer validate` and `composer install` succeed from the lock; `ddev demo-setup` builds a fresh demo on 3.0.1
- [ ] `python3 demo/scripts/check.py <url>` passes through the real OAuth flow
- [ ] The seed gives the `reviewer` account an `owner` membership on the invented apiary, and the demo opens with
      the amber alerts and insights as before
- [ ] `ddev demo-reset` restores the baseline and `freshen.php` still sets timestamps current
- [ ] `composer audit` run; each advisory fixed by updating the dependency or recorded here with the reason it is
      accepted for a throw-away site
- [ ] `AGENTS.md` demo section updated if the kit's steps changed

## Related
- Project:: [[ios-field-app]]
- Follows:: [[0215-demo-site-kit]]
- Commits::
