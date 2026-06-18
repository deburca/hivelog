---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[mobile-ux-improvements]]"
area: theme
created: 2026-06-17
branch: feature/0007-responsive-filter-form-and-heading
release: v1.4.0
depends-on: ["[[0004-responsive-foundation-and-breakpoints]]"]
---
# Task: Responsive filter form & list heading

## Context
`css/hivelog.filter-form.css` is engineered for a single desktop row: the
`.hivelog-filter-form` is a `flex` container and `.hivelog-filter-form__filters`
uses `display: contents` specifically to flatten the filters so every control
plus the Filter/Reset actions sit on one line. On a phone this is cramped and
the inputs become unusably narrow. The sibling `.hivelog-list-heading` (title +
Add action, `justify-content: space-between`) also needs to behave when it
wraps. Rendered on list pages such as the hive inspection list
(`HiveController::view()`). Part of [[mobile-ux-improvements]].

## Resolution
**CSS-only** stacking at `≤768px` in `css/hivelog.filter-form.css` (per
[[0011-responsive-design-strategy]]); no form/markup changes, desktop keeps its
single-row layout.
- `.hivelog-filter-form` becomes a full-width column; `__filters` drops
  `display: contents` and stacks as a column; `.form-item` inputs/selects go
  full-width.
- `.hivelog-filter-form__actions` spans the row (no auto-left margin).
- `.hivelog-list-heading` stacks (title above, action below at natural width;
  `__action` allowed to wrap) — this also tidies the reused Queen-section
  heading.
Tap-target sizing for the action buttons is coordinated with
[[0009-mobile-qa-and-tap-targets]].

## Acceptance criteria
- [x] At `≤768px` filter controls stack vertically and inputs go full-width
      (overrides `display: contents`).
- [ ] Filter / Reset actions remain reachable — now a full-width row;
      **tap-target sizing pending [[0009-mobile-qa-and-tap-targets]]** + visual
      check.
- [x] `.hivelog-list-heading` wraps cleanly: title above, Add action below;
      `__action` no longer forced `nowrap`.
- [x] Desktop single-row layout (`>768px`) unchanged — verified on the test site
      (resizing across the 768px breakpoint).

## Implementation notes
- Both selectors live in `css/hivelog.filter-form.css`; the heading class is
  also reused by the queen section in `HiveController::buildQueenSection()`, so
  test that page too.
- The filter form markup comes from `HivelogInspectionFilterForm` /
  `HivelogHiveFilterForm` (`src/Form/`). Verify which wrapper classes Drupal
  emits before targeting them.

## Dependencies
- Depends on: [[0004-responsive-foundation-and-breakpoints]].
- Coordinates with: [[0009-mobile-qa-and-tap-targets]].

## Related
- Project:: [[mobile-ux-improvements]]
- Decisions:: [[0011-responsive-design-strategy]]
- Commits:: 
