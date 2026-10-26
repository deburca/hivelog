---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[ios-field-app]]"
area: install
created: 2026-10-26
branch: chore/0234-refresh-demo-kit
release: 3.0.2
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
- [x] `composer validate` and `composer install` succeed from the lock; `ddev demo-setup` builds a fresh demo on 3.0.1
- [x] `python3 demo/scripts/check.py <url>` passes through the real OAuth flow
- [x] The seed gives the `reviewer` account an `owner` membership on the invented apiary, and the demo opens with
      the amber alerts and insights as before
- [x] `ddev demo-reset` restores the baseline and `freshen.php` still sets timestamps current
- [x] `composer audit` run; each advisory fixed by updating the dependency or recorded here with the reason it is
      accepted for a throw-away site
- [x] `AGENTS.md` demo section updated if the kit's steps changed

## Outcome (2026-10-26)
- `composer validate` and `composer install` from the lock work; `ddev demo-setup --fresh` builds on hivelog 3.0.1.
- **Found:** the seed left the reviewer with no way into the apiary. `assimilate` creates the apiary with no owner, so
  `Apiary::postSave()` grants no membership, and since 3.0 `uid` ("created by") gives no access. The check failed
  with the reviewer seeing 0 apiaries. `demo/scripts/seed.php` now makes the reviewer an `owner` member.
- `python3 demo/scripts/check.py https://hivelog-demo.ddev.site` passes through the real OAuth flow: 1 apiary,
  3 hives, 4 queens, 14 inspections (2 with photos), 6 alerts, 3 sensor tiles, 3 insight verdicts including one out of date.
- `ddev demo-reset` restores the baseline, `freshen.php` sets the timestamps current, and the check passes again.
- **Advisories (5), all fixed by updating** (`composer update "drupal/core*" drupal/leaflet drupal/simple_oauth
  --with-dependencies`, 25 packages): drupal/core SA-CORE-2026-014, -015, -016 (11.4.8 to 11.4.11),
  drupal/leaflet SA-CONTRIB-2026-204 (10.4.12 to 10.4.13), drupal/simple_oauth SA-CONTRIB-2026-242 (6.1.1 to 6.1.3).
  `composer audit` now reports none. None was accepted.
- `AGENTS.md` demo section and `demo/README.md` mention the owner membership. The kit's steps did not change.

## Related
- Project:: [[ios-field-app]]
- Follows:: [[0215-demo-site-kit]]
- Commits::
