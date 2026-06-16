---
type: task
tags: [hivelog/task]
status: backlog        # backlog | todo | in-progress | review | done | blocked | dropped
priority: medium       # high | medium | low
project:               # [[project-note]] this belongs to, if any
area:                  # entity | routing | theme | install | tests | docs
created: 2026-06-16
branch:                # feature/NNNN-slug
blocked-by:            # [[other-task]] if status = blocked
---
# Task: <title>

## Context
Why this work exists. Link the parent [[project]] and any driving
[[decision]].

## Acceptance criteria
- [ ] …
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `ddev drush updb -y && ddev drush cr` clean (if schema changed)

## Implementation notes
- Key files:
- Update hook needed? (entity schema changes require one — see `hivelog.install`)

## Related
- Project:: 
- Decisions:: 
- Commits:: 
