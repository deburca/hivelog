---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] The `default` calendar source defines yields for its harvest actions, so a new apiary gets Honey (kg) and Beeswax (kg) products with an expected yield of 0 on the harvest actions
- [ ] A short list of other common products (propolis, royal jelly, pollen, nucs, queens) is offered when adding a product, without being created unasked
- [ ] `HivelogCalendarCopier::apply()` stays additive and repeatable; existing apiaries can get the same rows through "Copy a calendar"
- [ ] A harvest log on a starter action shows a yield field with no further setup
- [ ] Kernel tests; demo seed checked (`demo/`)
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/CalendarAction.php`, `src/HivelogCalendarCopier.php`, `src/Form/ProductForm.php`
- Update hook needed? No (data seeded on create only). Relates to [[0053-product-category-field]].

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
