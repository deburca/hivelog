---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/hivelog-ok-tokens
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Beeswax theme — map the HiveLog `ok` tokens

**Repository: the beeswax theme (`github.com/deburca/beeswax`, `../beeswax`), not this module.** Beeswax has no
project-management vault of its own, so the task is tracked here with the rest of the stat-tile rollout.

## Context
Task 0256 added an `ok` sub-line variant to the stat tile (positive states: an active queen, a done action, a
positive net) with two new tokens in `css/hivelog.responsive.css`: `--hivelog-ok` and `--hivelog-ok-tint`, whose
module defaults are a neutral green (`#1f6b3a` / `#e4f2e9`). Beeswax redefines the `--hivelog-*` surface and severity
tokens from its own `--bw-*` palette inside `body.hivelog-page` in `src/hivelog.css` (critical and warning today).
Until it does the same for `ok`, a beeswax site shows the module's default green, which will not match the theme and
has no dark-mode value (the module has no dark mode; beeswax sets it under `.dark`).

Nothing emits the `ok` variant yet; tasks 0258, 0262 and 0261 (queen and action log status, report net) will. This
task should land before or with the first of them.

## Acceptance criteria
- [ ] `src/hivelog.css` maps `--hivelog-ok` and `--hivelog-ok-tint` in the `body.hivelog-page` block beside critical and warning
- [ ] Beeswax has its own `--bw-ok` and `--bw-ok-tint` in `src/theme.css`, light and dark (`.dark`) values, rather than aliasing `--bw-accent`: "ok" is a status and must stay distinguishable from the brand colour on a tile that also has accent-coloured links
- [ ] Text contrast of `--bw-ok` on `--bw-surface` and of the sub-line on the tile is at least 4.5:1 in both modes
- [ ] The `ok` sub-line renders on a stat tile in light and dark (check on `hivelog.ddev.site`; an `ok` tile can be forced by passing `'variant' => 'ok'` to `buildTileStrip()` from a scratch controller, or after 0258)
- [ ] The README's hivelog compatibility line names the hivelog version that introduced the tokens
- [ ] Beeswax release notes / version bumped per that repo's convention

## Implementation notes
- Key files (beeswax): `src/theme.css` (palette, light `:root` block near line 75 and dark block near line 132), `src/hivelog.css` (token mapping, near line 80), `README.md`.
- Existing palette for reference: accent `#2f6b4f` (dark `#63b389`), accent-tint `#e6efe8`, warning `#a4630a`, critical `#b3261e`. The accent is already a green, so choose an `ok` hue that reads apart from it (a yellower or clearer green) or justify reusing it.
- Needs a hivelog release that contains 0256 first; beeswax `1.0.x` states the minimum hivelog it expects.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
