---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/prose-note-block
release:
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Shared prose note block for free text beside tiles

## Context
The detail pages are now mostly stat-tile strips (tasks 0256–0263), but free text cannot be a tile: a tile cuts its value at 80 characters and has no room for line breaks or bullets. Today the prose fields (inspection External check, Action taken and Notes; queen and queen observation Notes; calendar action Description) still render through `HivelogDetailPageTrait::buildSection()` as a one-row label/value table, which looks out of place under a tile strip. Decision (2026-11-13, user): show prose in one shared **note block** used by every page, not a wide tile and not a table.

## Acceptance criteria
- [ ] A helper `buildProseBlock($title, $body)` in `HivelogDetailPageTrait` (or a small trait beside `HivelogStatTileStripTrait`): an H2 (task 0128 heading hierarchy) followed by the text full width. `$body` is a render array or a plain string, so notes (escaped, `nl2br`) and the calendar description (`SimpleBulletText::render()`, bullets) both fit
- [ ] Returns an empty array when the text is empty, so a page shows the block only when something was written
- [ ] Styled by a stable `.hivelog-prose` class (plus `__body`) using only the `--hivelog-*` tokens (`--hivelog-rule`, `--hivelog-ink`, `--hivelog-surface-2`), no table, no label column, line breaks and bullets preserved, readable line length on wide screens, wraps on a phone
- [ ] Library wiring follows the CSS rules in AGENTS.md (depends on `hivelog/responsive`; add to an existing library the detail pages already attach, or declare a new one)
- [ ] Unit/kernel test: empty text gives `[]`; text renders with the heading, escaped, line breaks kept; bullet text renders as a list
- [ ] AGENTS.md "Stat-tile strips" section names the prose block as the rule for long text, and the theming section lists `.hivelog-prose` as a stable class
- [ ] Phone width (≤480px) checked: block wraps, no horizontal scroll

## Implementation notes
- Key files: `src/HivelogDetailPageTrait.php` (`buildSection()` is the model), `css/hivelog.tables.css` or a new `css/hivelog.prose.css`, `hivelog.libraries.yml`.
- This task only builds the block. Task 0266 applies it to the pages.
- Escape user text exactly as the controllers do now (`Html::escape()` then `nl2br()`); do not render raw HTML.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
