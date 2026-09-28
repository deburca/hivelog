---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-28
---
# Project: Collection Page Filter Coverage

## Goal
Every full-page entity collection under `/hivelog/` gets a filter form
matching the shape [[page-structure-consistency]]'s own
[[0132-filters-on-hive-inspection-observation-lists]] already
established for Hives, Inspections and Queen Observations — narrow the
table, keep the filter state in the URL query string, Reset back to
the bare collection route. Extends the same coverage to the seven
collection pages that still have none: Apiaries, Queens, Inventory
Items, Inventory Purchases, Products, AI Provider Configs, Sensor
Devices.

## Scope
- In scope: a new `HivelogXFilterForm` per entity type (static
  `extract()`/`apply()`, GET method, matching
  `css/hivelog.filter-form.css` styling), wired into that entity's own
  `HivelogListBuilder` subclass via the three hook methods task 0132
  already added to the shared base class (`getFilterForm()`,
  `applyFilters()`, `hasActiveFilters()` — both are already generic;
  no shared-base-class changes needed here, only new subclass
  overrides).
- Out of scope: any *new* filter field not already on the entity
  itself (no new schema); a cross-apiary "Apiary" filter on pages that
  don't already scope by one (task 0132 made the identical call for
  Hives/Inspections/Queen Observations); Calendar Actions' own filter
  form (`HivelogCalendarActionsFilterForm`) — its collection page is
  controller-built, not a `HivelogListBuilder` subclass, same
  exclusion task 0132 already recorded.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

## Open questions
- None currently.

## Related decisions
- None — this applies an already-decided pattern
  ([[0132-filters-on-hive-inspection-observation-lists]]) to more
  collection pages; no new architectural decision is needed.
