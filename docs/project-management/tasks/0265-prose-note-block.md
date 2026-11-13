---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-11-13
branch: feat/prose-note-block
release: 4.3.0
depends-on: ["[[0256-stat-tile-strip-helper]]"]
blocked-by: []
---
# Task: Shared prose note block for free text beside tiles

## Context
The detail pages are now mostly stat-tile strips (tasks 0256–0263), but free text cannot be a tile: a tile cuts its value at 80 characters and has no room for line breaks or bullets. Today the prose fields (inspection External check, Action taken and Notes; queen and queen observation Notes; calendar action Description) still render through `HivelogDetailPageTrait::buildSection()` as a one-row label/value table, which looks out of place under a tile strip. Decision (2026-11-13, user): show prose in one shared **note block** used by every page, not a wide tile and not a table.

## Acceptance criteria
- [x] A helper `buildProseBlock($title, $body)` in `HivelogDetailPageTrait` (or a small trait beside `HivelogStatTileStripTrait`): an H2 (task 0128 heading hierarchy) followed by the text full width. `$body` is a render array or a plain string, so notes (escaped, `nl2br`) and the calendar description (`SimpleBulletText::render()`, bullets) both fit
- [x] Returns an empty array when the text is empty, so a page shows the block only when something was written
- [x] Styled by a stable `.hivelog-prose` class (plus `__body`) using only the `--hivelog-*` tokens (`--hivelog-rule`, `--hivelog-ink`, `--hivelog-surface-2`), no table, no label column, line breaks and bullets preserved, readable line length on wide screens, wraps on a phone
- [x] Library wiring follows the CSS rules in AGENTS.md (depends on `hivelog/responsive`; add to an existing library the detail pages already attach, or declare a new one)
- [x] Unit/kernel test: empty text gives `[]`; text renders with the heading, escaped, line breaks kept; bullet text renders as a list
- [x] AGENTS.md "Stat-tile strips" section names the prose block as the rule for long text, and the theming section lists `.hivelog-prose` as a stable class
- [ ] Phone width (≤480px) checked: block wraps, no horizontal scroll

## Implementation notes
- Key files: `src/HivelogDetailPageTrait.php` (`buildSection()` is the model), `css/hivelog.tables.css` or a new `css/hivelog.prose.css`, `hivelog.libraries.yml`.
- This task only builds the block. Task 0266 applies it to the pages.
- Escape user text exactly as the controllers do now (`Html::escape()` then `nl2br()`); do not render raw HTML.

- Built as `HivelogDetailPageTrait::buildProseBlock($title, array|string $body)` and `buildFieldProseBlock($title, $entity, $field)`, which formats through the controller's own `formatFieldValue()` so notes keep line breaks and the calendar description keeps its bullets; task 0266 only has to call it. New library `hivelog/prose` (`css/hivelog.prose.css`), attached by the block itself.
- A plain string body is escaped with `Html::escape()` and `nl2br()`; a render array is trusted as given (the caller already escaped it).
- The test goes through `QueenController` by reflection, because phpstan analyses the trait in the context of any class that uses it and an anonymous test class lacks the properties the trait expects.
- Phone width not checked (the browser pane cannot load the site stylesheets); the block has no fixed widths and wraps long words.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: 
- Commits:: 
