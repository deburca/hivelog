---
type: release
tags: [hivelog/release]
version: X.Y.Z
status: planned        # planned | in-progress | released
date:                  # release date (ISO)
---
# Release X.Y.Z

## Highlights
- 

## Included tasks
_Use exact release values in task frontmatter (for example `release: 1.4.0`) so this Dataview query resolves cleanly._
```dataview
LIST
FROM #hivelog/task
WHERE status = "done" AND contains(string(release), "X.Y.Z")
```

## Schema / update hooks
- New `hivelog_update_NNNN` hooks in this release? List them and what they do.

## Release checklist
- [ ] All target tasks `done`
- [ ] `--group hivelog` PHPUnit suite green
- [ ] `ddev drush updb -y && ddev drush cr` clean on a copy of prod data
- [ ] `hivelog.info.yml` version bumped
- [ ] `README.md` / `AGENTS.md` updated if behaviour changed
- [ ] Tag created: `git tag X.Y.Z`
