---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: install
created: 2026-11-14
branch: feature/0270-starter-product-catalogue-and-harvest-yields
release:
depends-on:
blocked-by:
---
# Task: Starter products and harvest yields for a new apiary

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A new apiary is seeded with the starter calendar (`CalendarAction::DEFAULT_STARTER_CALENDAR`,
including "Harvest Spring Honey" and "Harvest Summer Honey") but with no products: the definitions
carry no `yields` or `requirements`, and nothing creates Honey, Beeswax or Propolis. The yield
fields on a log form appear only for a `CalendarActionProductYield` on the action
(`HarvestYieldFormTrait::buildYieldFields()`), so reporting a starter harvest as done records no
honey at all until the beekeeper has found Products, created one, opened the calendar action and
added an expected yield. Bees make honey, wax, propolis, royal jelly and pollen; the application
should know that on day one.

## Acceptance criteria
- [x] The `default` calendar source defines yields for its harvest actions, so a new apiary gets Honey (kg) and Beeswax (kg) products with an expected yield of 0 on the harvest actions
- [x] A short list of other common products (propolis, royal jelly, pollen, nucs, queens) is offered when adding a product, without being created unasked (datalists on Name and Unit)
- [x] `HivelogCalendarCopier::apply()` stays additive and repeatable; existing apiaries can get the same rows through "Copy a calendar"
- [x] A harvest log on a starter action shows a yield field with no further setup (follows from the yield rows; the log form itself is not tested here)
- [x] Kernel tests (`CalendarCopierTest`, `ProductTest`)
- [ ] Demo seed checked (`demo/`): not run
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/CalendarAction.php`, `src/HivelogCalendarCopier.php`, `src/Form/ProductForm.php`
- Update hook needed? No (data seeded on create only). Relates to [[0053-product-category-field]].
- Behaviour change: copying a calendar now also adds a requirement or yield to an action the target already has, if it lacks it. Before, a skipped action got nothing.
- The products are created with no price, so potential income stays at 0 until the beekeeper sets one.
- No JavaScript: choosing a suggested name does not fill in the unit.
- **Delete policy.** An apiary is blocked from deletion by its products (ADR-0103 row 6), so the seeded products made every new apiary undeletable. Decision of 2026-11-14: a product with no recorded harvest is deleted with its apiary; one with harvest history still blocks. Recorded as an amendment to [[0103-delete-policy-for-records-with-children]]; in code, `cascade_unused` on the registry row (`src/Delete/`).

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Commits:: 
