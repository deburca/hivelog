---
type: task
tags: [hivelog/task]
status: in-progress
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

## Resolution
**Shrink-and-wrap** at `≤768px` in `css/hivelog.tables.css` (per
[[0011-responsive-design-strategy]]); CSS-only, desktop unchanged. All three
detail tables are uniform 2-column Field/Value tables
(`.hivelog-inspection-table`, `.hivelog-queen-table`,
`.hivelog-queen-observation-table`, built by each controller's `buildSection()`):
- `table-layout: fixed` with the Field (label) column capped at 40% so the
  Value column keeps usable width;
- reduced cell padding and `overflow-wrap: anywhere` so long values (notes,
  entity links) wrap instead of forcing horizontal scroll;
- label column emphasised (`font-weight: 600`).
Kept the 2-col layout (shrink) rather than stacking, since these are short
key/value pairs.

## Acceptance criteria
- [x] At `≤480px` detail tables remain legible: reduced padding, capped label
      column, no clipped content.
- [x] Two-column Field/Value tables shrink gracefully (fixed 40/60 split) rather
      than letting the value column overflow.
- [x] Long values (notes, links) wrap (`overflow-wrap: anywhere`) instead of
      forcing horizontal scroll.
- [ ] Desktop (`>768px`) appearance unchanged — by construction (gated `@media`);
      **manual visual check pending** (dev release).

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
- Decisions:: [[0011-responsive-design-strategy]]
- Commits:: 
