---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[collection-page-filter-coverage]]"
area: routing
created: 2026-09-28
branch: feature/0158-api-client-list-filters
release:
depends-on: ["[[0157-ai-provider-config-and-sensor-device-list-filters]]"]
blocked-by:
---
# Task: Filters on the API Clients list

## Context
An eighth [[collection-page-filter-coverage]] target added after the
original seven were already underway: `/hivelog/api-clients`,
submodule-owned by `collective` (task 0157 already covers `nexus` and
`nanoprobe`'s own pages the same way). `ApiClient` is "normally exactly
one row" per AGENTS.md's own description of the entity, so a filter
form here has little day-to-day value at typical scale — implemented
anyway for the same completeness/consistency reason every other
collection page got one, and because a site can and does provision
more than one client (`nanoprobe` + `nexus` + ad-hoc scripts each
plausibly holding their own).

## Acceptance criteria
- [ ] `collective`'s `ApiClientFilterForm` (new, in
      `modules/collective/src/Form/`): **Enabled** (select: Yes / No /
      - Any -, from the boolean field — same literal-options approach
      task 0157 used for the same field shape, no `allowed_values` to
      read) and **Label contains** (text, `LIKE`).
- [ ] `ApiClientListBuilder` overrides `getFilterForm()` /
      `applyFilters()` / `hasActiveFilters()` per the established
      pattern (already extends core's `HivelogListBuilder`).
- [ ] Kernel test in `modules/collective/tests/src/Kernel/`: filtering
      by `enabled` narrows the rows, Reset clears the query string,
      empty-state message distinguishes "no clients yet" from "no
      clients match."
- [ ] Verified live on `cms2`: `/hivelog/api-clients?enabled=1` narrows
      correctly, with a working Reset.
- [ ] phpcs clean; phpstan clean — run across the whole module tree
      (`modules/` included), not just core, per this repo's own CI
      parity rule (AGENTS.md "CI Pipeline").

## Implementation notes
- Key files: `modules/collective/src/Form/ApiClientFilterForm.php`
  (new), `modules/collective/src/ApiClientListBuilder.php`, one new
  kernel test file.
- No entity schema change → **no update hook required**.
- Sequenced after 0157 only so the boolean-field filter pattern is
  established once there first, not because of any real dependency.

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]],
  [[0155-apiary-and-queen-list-filters]],
  [[0157-ai-provider-config-and-sensor-device-list-filters]]
- Commits::
