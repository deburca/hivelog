---
type: task
tags: [hivelog/task]
status: backlog
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

## Acceptance criteria
- [ ] At `<=480px`, the inspection list is readable without horizontal pinching
      — via either a stacked/"card" layout (label per cell) or an enhanced
      horizontal scroll with a sticky first column (decision recorded).
- [ ] The "Operations" cell (a `hivelog:button-group`) stays usable at mobile
      widths; coordinate sizing with [[0011-unify-button-group-sizing]].
- [ ] The same treatment covers other list builders rendered through the SDC
      (apiary→hives, etc.), not just inspections.
- [ ] Empty-state row (`colspan = headers|length`) still renders correctly.
- [ ] Desktop (`>768px`) layout unchanged.

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
- Decisions:: responsive-strategy ADR (from 0004)
- Commits:: 
