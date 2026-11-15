---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-15
branch: feature/0284-harvest-totals-per-product-not-across-units
release:
depends-on: ["[[0272-hive-harvest-history-and-totals]]"]
blocked-by:
---
# Task: Harvest totals are per product, never summed across units

## Context
Found while repairing CI for [[0272-hive-harvest-history-and-totals]]
(2026-11-15). Part of [[beekeeper-centred-simplification-and-gaps]].

Task 0272 adds two totals to the hive page, and both add quantities of
different products together as if they shared a unit:

- **The "Harvest this year" stat tile**
  (`HivelogStatTileBuilder::harvestThisYearTile()`) sums the quantity of every
  direct harvest of the year and labels the sum with the unit of the first
  product it meets. 10 kg of honey and 4 jars of cut comb read "14 kg".
- **The Harvest History table** (`HiveController::buildHarvestHistory()`) has
  one column per product, which is right, and then a **Total** column per
  year and a grand total that add those columns together, with no unit.
  12 kg of honey and 0.3 kg of propolis read "12.3"; with a product counted
  in jars or each the figure means nothing.

A product's unit is free text (`Product.unit`: kg, g, jar, each) and there is
no unit conversion ([[0048-unit-conversion]] is a backlog placeholder), so a
sum across products is only meaningful when every product involved has the
same unit, and even then honey plus wax is rarely a figure a beekeeper wants.

## Acceptance criteria
- [ ] The "Harvest this year" tile never adds quantities of different
      products. Decide and record which it shows: the hive's main product
      (the one with the largest quantity) with "+ N more" as the sub-line, or
      one line per product
- [ ] The tile's unit is always the unit of the product whose quantity it
      shows
- [ ] The Harvest History table has no cross-product Total column and no
      cross-product grand total; the per-product "Grand total" row stays,
      since each cell there is one product
- [ ] Two products with the same name and unit in the table read as one
      column; two with the same unit but different names stay separate
- [ ] If potential income is wanted as the one comparable figure across
      products, it is a separate, labelled money column (quantity ×
      `unit_price_snapshot`), not a quantity
- [ ] Kernel tests with two products in different units (kg and jar) for the
      tile and the table; `HiveHarvestHistoryTest` updated
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogStatTileBuilder.php` (`harvestThisYearTile()`),
  `src/Controller/HiveController.php` (`buildHarvestHistory()`),
  `tests/src/Kernel/HiveHarvestHistoryTest.php`
- Update hook needed? No.
- `HarvestYield::trimQuantity()` already formats a quantity without trailing
  zeros; both places repeat `rtrim(rtrim(number_format(...)))` and could use
  it.
- Not in scope, but adjacent: both totals count harvests recorded directly
  only. A yield reported through a calendar action log for the same hive is
  left out, so the tile can read lower than the apiary's report for that
  hive. 0272's note says so; whether to include logged yields is its own
  decision.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits:: 
