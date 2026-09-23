---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[page-structure-consistency]]"
area: docs
created: 2026-09-23
branch: feature/0138-refresh-agents-md
release:
depends-on:
blocked-by:
---
# Task: Bring AGENTS.md up to date with the actual module

## Context
From the 2026-09-23 gap analysis. AGENTS.md is the first thing an agent
(or a new contributor) reads, and large parts of it no longer describe
the code:

| AGENTS.md says | Actual (2026-09-23) |
|---|---|
| "five custom content entities" (Apiary, Hive, HiveInspection, Queen, QueenObservation) | 15 core + 6 submodule = 21 |
| no mention of submodules | `nanoprobe`, `collective`, `nexus`, `assimilate` exist, with their own entities, routes, hooks and tests |
| "The latest hook is `hivelog_update_10013`" | `hivelog_update_10028` |
| "Only one service is registered" | `hivelog.breadcrumb`, `hivelog.app_nav_builder`, `hivelog.stat_tile_builder` |
| "All eleven list builders" | 14 (11 core + 3 submodule) |
| buttons "scoped to eight named context wrappers" | 12 in `css/hivelog.buttons.css` (and the file's own header says "seven") |
| library dependency chain (7 libraries) | 14 libraries incl. `dashboard`, `app_nav`, `notices`, `activity_columns`, submodule libraries |
| SDC components `button/`, `button-group/`, `entity-table/` | plus `stat-tile` (core) and `metric-tabs` (nanoprobe) |

## Acceptance criteria
- [ ] "Project Overview" and "Content entities": all 21 entity types,
      grouped (hierarchy; calendar / action logs; inventory / products /
      yields; submodule entities), each with its parent reference. The
      ASCII hierarchy diagram extended accordingly.
- [ ] A new "Submodules" section: what each of `nanoprobe`,
      `collective`, `nexus`, `assimilate` does, its dependencies, the
      hooks `hivelog` exposes to them (`hook_hivelog_app_nav_items()`,
      `hook_hivelog_dashboard_sections()`, the canonical-page panel hook
      of [[0099-submodule-canonical-page-panel-hook]]), and the
      warning that `assimilate` is development / demo only.
- [ ] Counts replaced by descriptions that don't go stale ("every list
      builder extends `HivelogListBuilder`", not "all eleven"). Where a
      number is useful, point at the source of truth instead.
- [ ] "Entity schema changes": reference the latest hook generically
      ("see the highest-numbered `hivelog_update_N` in
      `hivelog.install`") instead of a number.
- [ ] Services, CSS / library chain and components sections match the
      code. Coordinate with [[0119-single-source-navigation-registry]]
      (nav strip / services) and
      [[0123-refresh-navigation-reference-docs]] (breadcrumb section) so
      the three tasks don't rewrite the same paragraphs. Whichever
      lands first owns the shared text.
- [ ] Fix `css/hivelog.buttons.css`'s header ("seven context wrappers")
      the same way.
- [ ] Docs only. No release needed on its own.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0099-submodule-canonical-page-panel-hook]]
- Tasks:: [[0119-single-source-navigation-registry]],
  [[0123-refresh-navigation-reference-docs]]
- Commits::
