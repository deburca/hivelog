---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: navigation
created: 2026-11-14
branch: feature/0276-navigation-by-beekeeper-job
release:
depends-on: ["[[0275-plain-language-vocabulary]]"]
blocked-by:
---
# Task: Navigation grouped by what a beekeeper does

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

The nav has two primary items. "Apiaries" carries eight entity lists in one dropdown (Hives,
Inspections, Queens, Queen Observations, Calendar Actions, Inventory Items, Inventory Purchases,
Products), while the two action-log lists and the financial report are reachable only by
cross-links. The structure mirrors the tables, not the work: look after bees, do the season's
jobs, keep supplies, harvest and sell.

## Acceptance criteria
- [ ] An ADR amending ADR-0104 fixes the primary items; proposal: Bees (apiaries, hives, queens), Work (inspections, seasonal tasks, task records), Supplies and harvest (supplies, purchases, products, financial report), Setup
- [ ] Queen Observations stops being a destination of its own if [[0277-queen-observation-and-inspection-overlap-adr]] folds it away
- [ ] The financial report and the task records get nav entries, replacing cross-links where they only made up for the missing ones
- [ ] Breadcrumb collection ancestry and the derived menu links follow the same registry; `hivelog_api` is unaffected
- [ ] Unit and kernel tests for the builder; phone layout checked on `hivelog.ddev.site`
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogAppNavBuilder.php`, `src/HivelogEntityHierarchy.php`, `css/hivelog.app-nav.css`
- Update hook needed? Possibly, to re-key menu-link overrides as `hivelog_update_10029` did.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
