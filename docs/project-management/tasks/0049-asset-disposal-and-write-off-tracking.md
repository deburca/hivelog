---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0049-asset-disposal-and-write-off-tracking
release:
depends-on:
blocked-by:
---
# Task: Asset disposal & write-off tracking

## Context
A durable `InventoryItem`, once purchased, is currently assumed usable
through (at least) its full `useful_life_years` depreciation window —
there's no "this frame set broke/was retired early" workflow. Explicitly
listed as out of scope in [[inventory-tracking-and-depreciation]].

## Acceptance criteria
- [ ] A disposal event (date + optional reason) recordable against a
      durable `InventoryPurchase` — the natural attachment point, since
      depreciation is computed per-purchase, not per-item.
- [ ] `InventoryItem::getAnnualDepreciation()` stops counting a disposed
      purchase's contribution for years after its disposal date, even if
      still inside the original `useful_life_years` window — the actual
      point of this task; without it, disposal tracking would be
      informational only with no effect on the cost report.
- [ ] Kernel test: a durable purchase disposed of mid-way through its
      useful-life window shows correct depreciation before disposal and
      zero after, contrasted with `InventoryEndToEndTest`'s existing
      full-window boundary test.
- [ ] `ddev drush updb -y && ddev drush cr` clean.

## Implementation notes
- Key files: `src/Entity/InventoryPurchase.php` (new `disposal_date`
  field, optional), `src/Entity/InventoryItem.php`
  (`getAnnualDepreciation()` needs the disposal-date check added to its
  existing purchase-window loop).
- Consider whether a disposed purchase should also disappear from
  `CalendarActionItemRequirement`-style "you'll need this" checklist
  reminders — likely yes, but not the primary point of this task.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
