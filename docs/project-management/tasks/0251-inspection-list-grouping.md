---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: list-pages
created: 2026-10-30
branch: feat/inspection-list-grouping
release: 4.2.0
depends-on: ["[[0250-beeswax-theme-warm-palette]]"]
blocked-by: []
---
# Task: inspection list — date grouping, improved empty states and filter feedback

## Context
The hive canonical page (`HiveController::view()`) shows two tables under the
"Hive Activity" heading (weight 9–10): **Inspections** and **Queen Observations**. Both are paginated to 20 rows each. Currently:

### Current state — Inspections table
- Rows are ordered newest-first (`inspection_date DESC`), no grouping.
- The column layout is eight fields wide: Date / Weight / Queen seen / Brood pattern / Honey stores / Temperament / Population / Operations.
- On a phone this collapses into stacked cards (existing `.hivelog-entity-table` responsive rules).
- Empty states are two static strings in `buildInspectionsColumn()`:
  - `'No inspections match the current filters.'` — filtered path.
  - `'No inspections have been recorded for this hive yet.'` — unfiltered path.
- A filter form (`HivelogInspectionFilterForm`) sits above the table on both surfaces (the embedded hive-page table and the full `/hivelog/inspections` list).

### Current state — Queen Observations table
Same layout, same empty-state problem. The column layout is five fields wide: Date / Queen / Health / Temperament / Active / Operations.
The observations are gathered from *all* queens that have ever been on the hive
(`$hive->getQueens()`) so the column keeps history when a queen is replaced.

### Current state — Calendar checklist
The seasonal calendar (`$build['calendar_table']`, weight 27) also has empty states via
`HivelogCalendarChecklistBuilder::emptyMessage()`. It already handles the "no records exist at all" vs "all done" distinction but needs tighter messaging.

## Desired configuration

### Inspection list date grouping

Group inspections by date (YYYY-MM-DD rows with an expanded detail row beneath each
date that lists individual inspections under that date). A single inspection on a day is
shown inline; multiple inspections on the same day get a small "N inspections" badge next
to the date and expandable cards below. This is especially useful during swarm season when
beekeepers might inspect the same hive twice in one day, or when reviewing what happened
on a specific past visit.

Implementation approach — render groups as **adjacent `<tr>` rows** in the existing table:

1. The date group row uses a `<td colspan="8">` carrying a small badge with the formatted
   date (e.g. "2026-07-15 · 3 inspections") styled as a left-aligned label inside the row.
   It can be a `<button>` that toggles `hidden` on the sub-rows, or just always shown
   expanded since the group header is visually light (a muted label + a count badge).
2. The individual inspection rows are rendered normally with all 8 columns but indented
   slightly (`padding-left: 1rem`) to show they belong to the group above.
3. The filter form still sits above; when filters collapse all inspections for a date into
   zero, that group row hides entirely (only render groups that have ≥1 matching row).

The grouped table replaces `HiveController::buildInspectionsColumn()`'s flat `$rows` loop:
the data query and pager remain unchanged — grouping happens in PHP after `$inspections` is
loaded. A filter or sort by inspection_date would also work with the group view since each
row still references its date value.

### Improved empty states (all surfaces)

Empty states should be **invitations to act** — single CTA + plain-language context:

| Surface | Current message | Problem | Desired approach |
|---|---|---|---|
| Inspections (unfiltered) | `'No inspections have been recorded for this hive yet.'` | Descriptive, no action | **Add a button**: "Record your first inspection" linking to the add form. Wrap in an empty-state container. |
| Inspections (filtered) | `'No inspections match the current filters.'` | No hint about how to proceed | Add "Clear filters" button or reword to "No inspections found for this hive in that date range — [Reset] your filters". |
| Queen Observations (unfiltered) | `'No queen observations have been recorded for this hive yet.'` | Same problem as above | "Add an observation" button linking to the active queen's add route. |
| Queen Observations (filtered) | `'No queen observations match the current filters.'` | Same problem | "Clear filters" link. |
| Calendar checklist | Handled by `HivelogCalendarChecklistBuilder::emptyMessage()` — see below. | Already good, just tighten wording. | |
| Dashboard welcome card | `'Welcome to HiveLog! You don't have any apiaries yet.'` (text) + "Add apiary" button | Works; keep the pattern. | No change needed here — it already demonstrates the desired pattern. |

The empty-state container should:
- Use `padding: 2rem 1rem` so it feels spacious, not cramped.
- Be centered text (`text-align: center`).
- Use a `<p>` for the description in body-size ink (`var(--hivelog-ink)`).
- The CTA goes below as a primary button (or plain link styled as one) inside a
  `.empty-state-actions` flex row.
- No icon needed — the beeswax theme adds an illustration if desired, but the module stays
  text-only. The bee-themed SVG scenes in `error-page.css` are reserved for error states, not empty data.

### Calendar checklist empty states tighten

Currently `HivelogCalendarChecklistBuilder::emptyMessage()` returns:
- `'No seasonal calendar actions have been configured for this apiary.'` (apiary-level, no actions exist)
- `'All enabled seasonal calendar actions have been completed — none to report here.'` (all done)
- `'There are no seasonal calendar actions for the selected year.'` (no actions in that year)

The "all done" message is overly long. Tighten it to: `'All actions reported — nothing left to do.'`.
The "no configured actions" message should add a link where `apiary` context allows:
`'No seasonal calendar actions have been configured for this apiary. [Add action]'.`

### Filter feedback row (optional, low priority)

When the filter form has active filters, show a thin pill above the table summarising them:
`'Showing inspections from 2026-01-15 → 2026-07-20 · Queen seen: Yes · [Reset]'`.
This is useful because the URL query string is not always visible on a phone. The row reuses
the existing `.hivelog-filter-form__notice` styling (already styled as a left-stripe notice)
but changes it from an amber warning into a blue/neutral informational chip. This is
lower priority than the empty states and date grouping above.

## Acceptance criteria
- [ ] Inspection list groups rows by inspection_date — same-day inspections get a shared
    date label row with count badge, individual rows indented beneath.
- [ ] Filtered results also group (a group that collapses to zero rows is omitted).
- [ ] Empty-state messages replaced with CTAs on: Inspections (unfiltered + filtered),
    Queen Observations (unfiltered + filtered). The CTA links to the correct scoped add route.
- [ ] Calendar checklist empty states tightened per table above — no new component needed,
    just shorter copy and a link where `apiary` context is available.
- [ ] Filter feedback row (info-style chip) shown above tables when filters are active —
    optional, but if implemented uses existing `.hivelog-filter-form__notice` styling
    with a neutral (not warning) color.
- [ ] No new CSS files or component classes added (empty states reuse `.hivelog-entity-table`
    responsive rules and the module's `--hivelog-*` tokens).
- [ ] WCAG AA contrast maintained on all empty-state text against proposed surface/ground colors
    from task 0250.
- [ ] Visual review at 390px and 1280px, light + dark modes on hivelog.ddev.site.

## Implementation notes
- **Key files (PHP):** `src/Controller/HiveController.php` (grouping logic in `buildInspectionsColumn()`; empty-state markup changes),
    `src/Controller/HiveInspectionController.php` (empty state for `/hivelog/inspections` list),
    `src/Controller/QueenController.php` (observations empty state),
    `src/HivelogCalendarChecklistBuilder.php` (tighten messages).
- **Key files (CSS):** none required unless the date-group row's badge style or empty-state
    container layout warrants a new rule — if needed, add to `css/hivelog.tables.css` under
    the entity-table section.
- The grouped table still uses the existing `<table>` structure — no need for a new component
    wrapper or Twig template change. The group rows are `<tr>` elements with `colspan` rendered
    directly in the PHP `$rows` array.
- Date grouping is purely presentational — it does not change the pager, query, or sort logic.

## Related
- Project:: [[hive-log-frontend-polish]]
- Depends on:: [[0250-beeswax-theme-warm-palette]] (palette shift)
- Follows:: Task 0240 (filter form collapsible panel), Task 0239 (collection page sorting)
