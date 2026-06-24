---
type: task
tags: [hivelog/task]
status: backlog        # backlog | todo | in-progress | review | done | blocked | dropped
priority: medium       # high | medium | low
project:               # actual project wikilink, e.g. "[[queen-observation-enhancements]]"
area:                  # entity | routing | theme | install | tests | docs
created: 2026-06-16
branch:                # feature/NNNN-slug
release:               # target release, e.g. 1.4.0
depends-on:            # inline array of prerequisite task wikilinks, e.g. ["[[0004-responsive-foundation-and-breakpoints]]"]
blocked-by:            # actual task wikilink, e.g. "[[0010-define-button-tokens-and-source-of-truth]]" if status = blocked
---
# Task: <title>

## Context
Why this work exists. Link the parent project note and any driving
ADR/task notes using real wikilinks once they exist.

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
