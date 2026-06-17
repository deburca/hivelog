---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[mobile-ux-improvements]]"
area: theme
created: 2026-06-17
branch: feature/0006-responsive-detail-tables
release: v1.4.0
depends-on: ["[[0004-responsive-foundation-and-breakpoints]]"]
---
# Task: Responsive detail tables

## Context
Canonical detail pages use the plain-CSS tables defined in
`css/hivelog.tables.css`: `.hivelog-inspection-table`, `.hivelog-queen-table`,
and `.hivelog-queen-observation-table`. They are `width: 100%` with fixed
`padding: 0.5rem 0.75rem` and no responsive handling. The queen summary table
on the hive page (built in `HiveController::buildQueenSection()`, a Field/Value
two-column table) is the most common one; the inspection and observation detail
tables carry more columns and are tighter on phones. Part of
[[mobile-ux-improvements]].

## Acceptance criteria
- [ ] At `<=480px`, detail tables remain legible: no clipped content, sensible
      wrapping, reduced horizontal padding.
- [ ] Two-column Field/Value tables (e.g. the queen summary) stack or shrink
      gracefully rather than letting the value column overflow.
- [ ] Long values (notes, links) wrap instead of forcing horizontal scroll.
- [ ] Desktop (`>768px`) appearance unchanged.

## Implementation notes
- All three selectors live in `css/hivelog.tables.css`; add `@media` rules there
  per the [[0004-responsive-foundation-and-breakpoints]] convention.
- Distinguish these **detail** tables (plain CSS) from **list** tables (the SDC
  in [[0005-responsive-entity-list-tables]]) — different mechanisms, keep the
  fixes separate.
- The `hivelog/tables` library depends on `hivelog/buttons`, so any button-cell
  inside these tables inherits button styling — verify against
  [[action-button-consistency]].

## Dependencies
- Depends on: [[0004-responsive-foundation-and-breakpoints]].

## Related
- Project:: [[mobile-ux-improvements]]
- Decisions:: responsive-strategy ADR (from 0004)
- Commits:: 
