---
type: task
tags: [hivelog/task]
status: in-progress
priority: high
project: "[[mobile-ux-improvements]]"
area: theme
created: 2026-06-17
branch: feature/0005-responsive-entity-list-tables
release: v1.4.0
depends-on: ["[[0004-responsive-foundation-and-breakpoints]]"]
---
# Task: Responsive entity-list tables

## Context
Embedded list tables render through the `hivelog:entity-table` SDC
(`components/entity-table/entity-table.twig`), whose only mobile affordance is a
`overflow-x-auto` wrapper around a `w-full` table. The worst case is the hive
page's **eight-column** inspection list built in
`src/Controller/HiveController.php` (~line 170: Date, Weight, Queen, Brood,
Honey, Temperament, Population, Operations). On a phone this overflows or
crushes columns. Part of [[mobile-ux-improvements]].

## Resolution
**Stacked "card" pattern** at `≤768px` (per [[0011-responsive-design-strategy]]),
implemented in the shared `hivelog:entity-table` SDC so every entity list
benefits:
- `entity-table.twig`: the table gains a `hivelog-entity-table` class and each
  data `<td>` gets `data-label="{{ headers[loop.index0] }}"` — reusing the
  existing `headers` prop, so **no component-contract change**.
- `entity-table.css`: `@media (max-width: 768px)` collapses rows into labelled
  cards (`td[data-label]::before` shows the column name), visually hides the
  header row, and centres the label-less empty-state cell.
- `entity-table.component.yml`: now also depends on `hivelog/responsive` for the
  shared spacing tokens.
Desktop (`>768px`) is untouched. Operations-cell **tap-target** sizing is
deferred to [[0011-unify-button-group-sizing]].

## Acceptance criteria
- [x] At `≤768px` the list is readable without horizontal pinching — **stacked
      card layout** (label per cell via `data-label`).
- [ ] The "Operations" cell stays usable — buttons render inside the card;
      **tap-target sizing pending [[0011-unify-button-group-sizing]]** + visual
      check.
- [x] Same treatment covers all SDC-rendered lists (apiary→hives, queens, etc.)
      via the shared component.
- [x] Empty-state row still renders (its cell has no `data-label`, styled
      separately).
- [ ] Desktop (`>768px`) layout unchanged — by construction (additive class /
      attribute + gated `@media`); **manual visual check pending** (dev release).

## Implementation notes
- If choosing the stacked pattern, the SDC needs each `<td>` to carry its header
  label (e.g. a `data-label` attribute) — `entity-table.twig` currently emits
  bare `<td>{{ cell|raw }}</td>`, so the component contract (props in
  `entity-table.component.yml`) may need a `headers`-to-cell association.
- List builders to check: `HiveListBuilder`, `HiveInspectionListBuilder`,
  `QueenListBuilder`, `QueenObservationListBuilder`, `ApiaryListBuilder`.
- Consider reducing column count on mobile by hiding low-priority columns
  (e.g. Population/Temperament) rather than stacking, if simpler.

## Dependencies
- Depends on: [[0004-responsive-foundation-and-breakpoints]] (breakpoints).
- Coordinates with: [[0011-unify-button-group-sizing]] (Operations cell).

## Related
- Project:: [[mobile-ux-improvements]]
- Decisions:: [[0011-responsive-design-strategy]]
- Commits:: 
