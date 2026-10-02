---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: routing
created: 2026-10-02
branch: feature/0169-filter-form-date-range-validation
release:
depends-on:
blocked-by:
---
# Task: Validate "From" and "To" date ranges in filter forms

## Context
Found in the 2026-10-02 review of [[collection-page-filter-coverage]] (done).
`HivelogInspectionFilterForm` applies `date_from` (`>=`) and `date_to`
(`<=`) as independent conditions with no check between them. A "From" later
than "To" silently produces an empty table and the "no rows match your
filters" message, with nothing telling the beekeeper the range is the
problem. The Queen Observation and Inventory Purchase filters and the
calendar filters probably behave the same way — to be confirmed in this
task, since only the inspection form was inspected.

## Acceptance criteria
- [ ] Audit every filter form with a from/to pair (inspection, queen
      observation, inventory purchase, plus any others found) and list them
      in the implementation notes.
- [ ] A "From" later than "To" produces a visible message on the form and
      does not silently return zero rows. Decide: reject (show an error and
      apply no date filter) or swap the two values.
- [ ] Because these are GET forms with a static `extract()`/`apply()` split,
      the check must work from the query string without a form-submit
      round-trip, and must not break Reset.
- [ ] Shared helper for the check, not a copy per form.
- [ ] Kernel tests: reversed range, equal dates, one side empty, both empty.
- [ ] Verified live on `cms2`.
- [ ] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/Form/HivelogInspectionFilterForm.php`,
  `HivelogQueenObservationFilterForm.php`,
  `HivelogInventoryPurchaseFilterForm.php`.
- No update hook.

## Related
- Project:: [[collection-page-filter-coverage]] (done; follow-up)
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]], [[0156-inventory-and-product-list-filters]]
- Commits::
