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
populated). The workaround was `'demo'` in `file_scan_ignore_directories` in each site's `settings.php` (done on
kbg, hvg, shh and default); `vdg`'s `settings.php` is read-only and was not changed.

## Acceptance criteria
- [ ] Decide the fix: keep the per-site setting, or stop the generated Drupal being created inside the module tree
      (build the demo outside it, or in a directory discovery already skips)
- [ ] `vdg` covered (make its settings writable for the edit, or the fix needs no setting)
- [ ] The Update module's stale project list cleared on each site after the fix
- [ ] The eight local-only kernel test errors gone (or each explained) on a development site with `demo/` built
- [ ] README of the demo kit and `AGENTS.md` updated

## Related
- Project:: [[ios-field-app]]
- Follows:: [[0215-demo-site-kit]]
- Commits::
