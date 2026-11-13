---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `HivelogDetailPageTrait::buildTileStrip()` (name may change) takes tile definitions keyed by field name (optional label/value/variant/url overrides) and returns a stat-tile row; booleans render yes/no, enum values show their allowed-value label, empty values show "—", dates use the module's format
- [ ] Reference fields can link the *value* (not only the whole tile through `url`); decide and document the component change (new prop or slot)
- [ ] `sublabel_variant` gains an `ok` value (positive states) with a tint token, themable like `critical` and `warning` (`--hivelog-ok` / `--hivelog-ok-tint`)
- [ ] Long text values truncate or wrap by one stated rule
- [ ] One neutral wrapper class (e.g. `hivelog-stat-tiles--facts`) replaces the apiary-specific `hivelog-apiary-facts`; the apiary page moves onto the helper
- [ ] `stat-tile.component.yml` schema and the "Theming HiveLog" section updated; beeswax tokens noted as a follow-up in the theme repo
- [ ] Tests added/updated (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed
- [ ] Phone width (≤480px) checked: strip wraps, no horizontal scroll

## Implementation notes
- Key files: `src/HivelogDetailPageTrait.php`, `components/stat-tile/`, `css/hivelog.responsive.css` (tokens), `src/Controller/ApiaryController.php` (`buildSummary()`)
- No entity schema change, no update hook.
- The computed API tile endpoint (`computed/hive/{hive}/stat-tiles`) must tolerate the new `ok` variant: check `HivelogApiContractTest` and the app's variant handling before shipping.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
