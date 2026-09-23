---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[page-structure-consistency]]"
area: theme
created: 2026-09-23
branch: feature/0131-single-detail-table-css-class
release:
depends-on: ["[[0125-shared-detail-page-builder]]"]
blocked-by:
---
# Task: One CSS class for detail-page label / value tables

## Context
From the page-structure review of 2026-09-23. Detail pages render their
two-column label / value tables with a per-entity class, 13 in all:
`hivelog-inspection-table`, `-queen-table`, `-queen-observation-table`,
`-calendar-action-table`, `-hive-action-log-table`,
`-apiary-action-log-table`, `-inventory-item-table`,
`-inventory-purchase-table`, `-product-table`, `-sensor-device-table`,
`-api-client-table`, `-ai-provider-config-table` (plus the Hive page's
queen table). `css/hivelog.tables.css` repeats each of them in the
same 9 selector positions, so every new entity type means editing 9
selector lists. That is the same silent-failure trap as the
`hivelog.buttons.css` context-wrapper allow-list
([[0113-destructive-action-styling-sensor-device-api-client]]).

## Acceptance criteria
- [ ] One generic class, `hivelog-detail-table` (and
      `hivelog-detail-section` for the wrapper), emitted by the shared
      builder from [[0125-shared-detail-page-builder]] and by the
      submodule controllers.
- [ ] The per-entity class is **kept alongside** the generic one on the
      element for at least one minor release, because AGENTS.md
      ("Theming HiveLog") treats `.hivelog-*` class names as theming API.
      Removing them later needs its own task and a release note.
- [ ] `css/hivelog.tables.css` rules rewritten against the generic
      class only. The 13-way selector lists are gone.
- [ ] Visual regression check on `cms2`: every detail page looks
      identical before and after (same widths, borders, label column).
- [ ] beeswax checked for selectors on the per-entity classes
      (`src/hivelog.css`). If present, a follow-up there to move to the
      generic class.
- [ ] AGENTS.md "Theming HiveLog" stable-class list gains
      `hivelog-detail-table` / `hivelog-detail-section`.

## Implementation notes
- Depends on [[0125-shared-detail-page-builder]] so the class is added
  in one place rather than 13.
- `hivelog-inventory-report-table` is a different, genuinely distinct
  table style (report tables) and stays as-is.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]]
- Tasks:: [[0125-shared-detail-page-builder]],
  [[0113-destructive-action-styling-sensor-device-api-client]]
- Commits::
