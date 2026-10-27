---
type: task
tags: [hivelog/task]
status: todo
priority: low
project: "[[ios-field-app]]"
area: tests
created: 2026-10-26
branch:
release:
depends-on: ["[[0215-demo-site-kit]]"]
blocked-by:
---
# Task: Keep the nested demo site out of module discovery on development sites

## Context
`demo/` holds a generated Drupal (`demo/web`, `demo/vendor`). When the module sits in a development site, Drupal's
extension discovery walks into it and finds a second copy of core, which produced an "Undefined array key drupal"
warning from the Update module and eight kernel test errors that appear only locally (CI never has `demo/`
populated). The workaround was `'demo'` in `file_scan_ignore_directories` in a site's `settings.php`.

**Scope (decided 2026-10-26, moved 2026-10-27):** HiveLog is tested exclusively on the **hvg** site (`hivelog.ddev.site`
locally, `hivelog.eu` on the web), which took over from kbg ([[0236-move-hivelog-hosting-from-kbg-to-hvg]]); kbg's HiveLog
was uninstalled. The other sites (vdg, shh, default) do not run it and are out of scope. The setting is needed only on the
site that runs HiveLog: in place on hvg's `settings.php` (kbg still has it, harmlessly).

## Acceptance criteria
- [ ] Decide the fix: keep the per-site setting, or stop the generated Drupal being created inside the module tree
      (build the demo outside it, or in a directory discovery already skips)
- [ ] The Update module's stale project list cleared on kbg, and on hvg once it hosts HiveLog, after the fix
- [ ] The eight local-only kernel test errors gone (or each explained) on a development site with `demo/` built
- [ ] README of the demo kit and `AGENTS.md` updated

## Decision so far (2026-10-27)
For now `'demo'` is added to `file_scan_ignore_directories` in the `settings.php` of the site that runs HiveLog. How the demo is
hosted permanently (inside or outside the module tree) is undecided, so the first criterion stays open until that is.

## Related
- Project:: [[ios-field-app]]
- Follows:: [[0215-demo-site-kit]]
- Commits::
