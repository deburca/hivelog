---
type: task
tags: [hivelog/task]
status: backlog
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

## Acceptance criteria
- [ ] At `<=768px`, filter controls stack vertically and inputs go full-width
      (override `display: contents` back to a block/column flow at the
      breakpoint).
- [ ] Filter / Reset actions remain reachable (full-width or right-aligned row)
      and keep adequate tap-target size — coordinate with
      [[0009-mobile-qa-and-tap-targets]].
- [ ] `.hivelog-list-heading` wraps cleanly: title above, Add action below,
      without the action overflowing (`__action` is currently
      `white-space: nowrap`).
- [ ] Desktop single-row layout (`>768px`) unchanged.

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
- Decisions:: responsive-strategy ADR (from 0004)
- Commits:: 
