---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-29
branch: ui/error-scenes-and-lists
release: 3.4.0
depends-on: ["[[0238-friendly-page-not-found-page]]"]
blocked-by:
---
# Task: The 403 scene is centred and both scenes follow dark mode

## Context
Two loose ends from 0238: the access-denied hive sat right of centre in its card, and neither scene followed dark mode
(a bright cream card on a dark page).

## Acceptance criteria
- [x] The access-denied scene is drawn about the hive, so the whole scene is shifted to put the hive at the card's centre
- [x] The module keeps no dark mode of its own: a theme redefines the `--hivelog-error-*` properties, and beeswax now does so under `.dark` (a dusk palette; bees, sign and ink unchanged)
- [x] Kernel tests for the component still pass

## Outcome (2026-10-29)
- `error-page.twig` wraps the scene in a group translated by -115 for `access-denied`; `not-found` is unchanged.
- beeswax carries the dark palette (its own release).
- Checked at 1280px light and 390px dark on hivelog.ddev.site.

## Related
- Follows:: [[0238-friendly-page-not-found-page]]
