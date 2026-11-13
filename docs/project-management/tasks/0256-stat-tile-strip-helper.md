---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/stat-tile-strip-helper
release:
depends-on:
blocked-by: []
---
# Task: Shared stat-tile strip helper and component additions

## Context
Detail pages (Queen, Inspection, Queen Observation, Inventory Item/Purchase/Product, Calendar Action, both action logs) render short enumerated values as stacked two-column tables through `HivelogDetailPageTrait::buildSection()`. The apiary page (task 0255) and hive page already use `hivelog:stat-tile` strips for the same kind of fact, but each controller builds its own tiles and its own wrapper class (`hivelog-apiary-facts`). A survey of the app (2026-11-13) found about a dozen pages that would read better as tiles. This task builds the one shared mechanism and the component gaps so each page task is a few lines. It is the prerequisite for tasks 0257-0263.

## Acceptance criteria
- [x] `buildTileStrip()` (in the new `HivelogStatTileStripTrait`, used by `HivelogDetailPageTrait`) takes tile definitions keyed by field name (optional label/value/variant/url overrides) and returns a stat-tile row; booleans render yes/no, enum values show their allowed-value label, empty values show "—", dates use the module's format
- [x] Reference fields: the *whole tile* links to the referenced record (a link inside a link is invalid markup, so a value is never its own link); no component change needed. `'url' => FALSE` suppresses it
- [x] `sublabel_variant` gains an `ok` value (positive states) with a tint token, themable like `critical` and `warning` (`--hivelog-ok` / `--hivelog-ok-tint`)
- [x] Long text values truncate or wrap by one stated rule
- [x] One neutral wrapper class (e.g. `hivelog-stat-tiles--facts`) replaces the apiary-specific `hivelog-apiary-facts`; the apiary page moves onto the helper
- [x] `stat-tile.component.yml` schema and the "Theming HiveLog" section updated; beeswax tokens noted as a follow-up in the theme repo
- [x] Tests added/updated (`--group hivelog`)
- [x] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/HivelogDetailPageTrait.php`, `components/stat-tile/`, `css/hivelog.responsive.css` (tokens), `src/Controller/ApiaryController.php` (`buildSummary()`)
- No entity schema change, no update hook.
- The computed API tile endpoint (`computed/hive/{hive}/stat-tiles`) must tolerate the new `ok` variant: check `HivelogApiContractTest` and the app's variant handling before shipping.

- Implemented in `src/HivelogStatTileStripTrait.php`; the facts CSS moved from `hivelog.map.css` to `stat-tile.css`; `--hivelog-ok` tokens in `hivelog.responsive.css`.
- Not done here: the beeswax theme tokens for `ok`, and checking the app tolerates the `ok` variant (nothing emits it yet, so no tile reaches the API with it).
- Phone-width visual check outstanding: the Browser pane would not load the site's stylesheets, so only the markup was checked on hivelog.ddev.site.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
