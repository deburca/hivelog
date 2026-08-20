---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0052-merge-usage-and-yield-form-traits
release:
depends-on:
blocked-by:
---
# Task: Merge `InventoryUsageFormTrait`/`HarvestYieldFormTrait`?

## Context
An open question from [[honey-wax-propolis-yield-and-potential-income]]:
`HarvestYieldFormTrait` is a deliberate structural copy of
`InventoryUsageFormTrait` (item/quantity/unit_cost_snapshot renamed to
product/quantity/unit_price_snapshot), kept as parallel siblings rather
than unified into one generic "linked-quantity" trait — consistent with
how `HiveActionLog`/`ApiaryActionLog` are themselves parallel siblings
rather than a shared polymorphic base. This task exists to revisit that
choice, not to assume it should change.

## Acceptance criteria
- [ ] Revisit only if the duplication has caused actual maintenance pain
      — e.g. a bug fixed in one trait but forgotten in the other, or a
      third "linked-quantity" trait becoming needed for a new feature
      (at which point three near-identical traits would be a stronger
      signal than two). Absent that, closing this task as "confirmed:
      keep as parallel siblings" is a legitimate outcome.
- [ ] If merged: a single generic trait parameterised by entity type
      (`inventory_usage`/`item`/`unit_cost_snapshot` vs.
      `harvest_yield`/`product`/`unit_price_snapshot`), with both
      `HiveActionLogForm`/`ApiaryActionLogForm` calling it twice (once
      per linked entity type) instead of using two separate traits — the
      field-name-prefix and fieldset-#weight collision-avoidance already
      in place for both traits would need to keep working identically.
- [ ] Full regression pass required if merged — this touches the most
      heavily-tested code path in both parent projects
      (`InventoryUsageReportingIntegrationTest`,
      `HarvestYieldReportingIntegrationTest`, both end-to-end test files).

## Implementation notes
- Suggested to revisit alongside
  [[0041-scope-item-and-product-autocomplete-to-current-apiary]]/
  [[0043-hide-discontinued-items-and-products-from-selection]], both of
  which touch the same trait pair — a more natural moment to reconsider
  the duplication than doing it in isolation, per this project's own
  Open Questions note.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
