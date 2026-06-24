---
type: project
tags: [hivelog/project]
status: active
target: 1.4.0
created: 2026-06-17
---
# Project: Mobile UX Improvements

## Goal
Make every Hivelog page comfortably usable on phones and small tablets. The
module currently ships **no responsive CSS at all** — a repo-wide search for
`@media` / breakpoint rules returns nothing — so layouts are implicitly
desktop-only. This project introduces a deliberate responsive strategy and
applies it across tables, forms, the apiary map, and image grids.

## Progress (updated 2026-06-18)
Foundation + all build tasks are **done** and merged: `0004` (PR #81) and
`0005`–`0008` (PR #82), verified on the test site (stacked list tables,
shrink-and-wrap detail tables, stacked filter form/heading, 2-column photo grid,
taller `40vh` apiary map). The only remaining task is the
[[0009-mobile-qa-and-tap-targets]] QA gate, which is gated on
[[0011-unify-button-group-sizing]] (tap-target sizing) in
[[action-button-consistency]].

## Scope
- In scope: a shared breakpoint/responsive strategy; responsive treatment for
  entity-list tables, detail tables, the inspection filter form + list heading,
  the apiary Leaflet map, the hive image grid; mobile QA and touch-target
  sizing.
- Out of scope: visual redesign / re-theming, new features, and the button
  colour/size system (tracked separately in [[action-button-consistency]],
  though [[0009-mobile-qa-and-tap-targets]] touches tap-target sizing and
  coordinates with it).

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT priority asc, file.name asc
```
Static index (in suggested execution order):
- [[0004-responsive-foundation-and-breakpoints]] — foundation (do first)
- [[0005-responsive-entity-list-tables]]
- [[0006-responsive-detail-tables]]
- [[0007-responsive-filter-form-and-heading]]
- [[0008-responsive-map-and-image-grid]]
- [[0009-mobile-qa-and-tap-targets]] — QA gate (do last)

## Key findings (from code scan, 2026-06-17)
- No `@media` rules anywhere in `css/` or `components/`.
- Embedded lists render through the `hivelog:entity-table` SDC
  (`components/entity-table/entity-table.twig`), whose only mobile affordance is
  a `overflow-x-auto` wrapper.
- The hive inspection list is **eight columns** wide
  (`src/Controller/HiveController.php` ~line 170) — the worst-case table for
  narrow screens.
- The filter form (`css/hivelog.filter-form.css`) uses `display: contents` to
  force all filters + actions onto a single desktop row.
- Buttons grouped via `components/button-group/button-group.twig` use
  `!px-1 !py-0`, producing very small touch targets.

## Open questions
- Breakpoint set: adopt Drupal core/Olivero-aligned breakpoints, or define a
  small Hivelog-specific set? (Decided in [[0004-responsive-foundation-and-breakpoints]].)
- For wide list tables, do we prefer a stacked "card" pattern or an enhanced
  horizontal-scroll with a sticky first column? (Decided in
  [[0005-responsive-entity-list-tables]].)
- Should responsiveness live in plain CSS `@media` (the `css/*.css` files) or in
  Tailwind responsive utilities inside the SDC `.twig` files? The module mixes
  both today; [[0004-responsive-foundation-and-breakpoints]] must pick one.

## Related decisions
- [[0011-responsive-design-strategy]] (accepted) — implemented by [[0004-responsive-foundation-and-breakpoints]].
- [[0001-geofield-over-geolocation]] / [[0002-no-geocoder-dependency]] constrain
  the apiary map work in [[0008-responsive-map-and-image-grid]].
