---
type: project
tags: [hivelog/project]
status: planning       # planning | active | paused | done | dropped
target:                # target release, e.g. v1.2.0
created: 2026-06-16
---
# Project: <title>

## Goal
One paragraph: the outcome and who benefits.

## Scope
- In scope:
- Out of scope:

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
_(Set each task's `project:` frontmatter to `[[<this note>]]` to populate.)_

## Open questions
- 

## Related decisions
- 
