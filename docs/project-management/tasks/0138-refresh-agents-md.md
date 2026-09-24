---
type: task
tags: [hivelog/task]
status: review
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
- [x] "Project Overview" and "Content entities": all 21 entity types,
      grouped (hierarchy; calendar / action logs; inventory / products /
      yields; submodule entities), each with its parent reference. The
      ASCII hierarchy diagram extended accordingly.
- [x] A new "Submodules" section: what each of `nanoprobe`,
      `collective`, `nexus`, `assimilate` does, its dependencies, the
      hooks `hivelog` exposes to them (`hook_hivelog_app_nav_items()`,
      `hook_hivelog_dashboard_sections()`, the canonical-page panel hook
      of [[0099-submodule-canonical-page-panel-hook]]), and the
      warning that `assimilate` is development / demo only.
- [x] Counts replaced by descriptions that don't go stale ("every list
      builder extends `HivelogListBuilder`", not "all eleven"). Where a
      number is useful, point at the source of truth instead.
- [x] "Entity schema changes": reference the latest hook generically
      ("see the highest-numbered `hivelog_update_N` in
      `hivelog.install`") instead of a number.
- [x] Services, CSS / library chain and components sections match the
      code. Coordinate with [[0119-single-source-navigation-registry]]
      (nav strip / services) and
      [[0123-refresh-navigation-reference-docs]] (breadcrumb section) so
      the three tasks don't rewrite the same paragraphs. Whichever
      lands first owns the shared text.
- [x] Fix `css/hivelog.buttons.css`'s header ("seven context wrappers")
      the same way.
- [x] Docs only. No release needed on its own.

## Implementation notes
**Implemented 2026-09-24.**
- **Verified every number against the actual code**, not against the
  task's own gap-analysis table (which was itself already a day-old
  snapshot by the time this task started — `hivelog.services.yml` had
  gained a 4th service, `hivelog.delete_dependency_counter`, since the
  2026-09-23 review that wrote the table): grepped every
  `#[ContentEntityType(` for the 21 entity types and their
  `target_type` references, every `extends HivelogListBuilder`, every
  `.libraries.yml`, every `.component.yml`, the `:is()` selector list
  in `hivelog.buttons.css`, and `hivelog_update_N`'s highest number
  (`10028`) directly, rather than trusting the table's already-stale
  figures.
- **This task lands first among the three the AC flagged as
  coordinating on shared text** (0119 nav-strip/services, 0123
  breadcrumb section — both still `backlog`/`todo`, neither started) —
  per the AC's own "whichever lands first owns the shared text" rule,
  wrote the Services section's `app_nav_builder` paragraph and left the
  existing breadcrumb subsection essentially as-is (it was already
  accurate, just needed reflowing around the new services list above
  it). A future 0119/0123 should edit these in place rather than
  duplicate them.
- **Chose prose groups over one bigger diagram** for the 10
  non-hierarchy core entities (calendar/action-log group, inventory/
  product/yield group) — a single ASCII tree covering all 15 core types
  would need to show `InventoryUsage`/`HarvestYield` each pointing at
  *either* of two log types, which doesn't render sensibly as a tree.
  Kept the original 5-entity hierarchy diagram exactly as-is (still
  fully accurate) and added a sentence pointing from it to the grouped
  prose below for everything else — matches the AC's own "grouped"
  wording more literally than forcing a diagram.
- **The library dependency chain diagram now shows `dashboard` as a
  leaf under both `buttons` and `tables`** (with a parenthetical
  callout) rather than picking one parent to hide the other declared
  dependency — `hivelog.libraries.yml` lists `dashboard`'s own
  `dependencies:` as all three of `buttons`, `responsive` and `tables`
  directly, not `tables` alone, so collapsing it under just one parent
  would have been its own small inaccuracy.
- **`css/hivelog.buttons.css`'s stale "seven"/"eighth" counts** (the
  file's own `:is()` selector list actually has 12 entries now) were
  two inline comments, not the itemized header list itself — the
  header list was already accurate and complete, just described
  inconsistently by the prose around the CSS rule blocks below it.
  Reworded both to describe the mechanism without restating a count.
- **Did not touch**: the "Routing, controllers and forms", "Tests",
  "Theming HiveLog" or "Patches" sections — none were named as stale in
  the task's own gap-analysis table, and a targeted check against the
  real routing/test/theme/patch state didn't turn up anything else
  wrong. Kept the diff scoped to what the table actually flagged plus
  the two spots (services, list-builder/library counts) that
  necessarily follow from documenting the same facts consistently.
- Key files: `AGENTS.md`, `css/hivelog.buttons.css` (two comments).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0099-submodule-canonical-page-panel-hook]]
- Tasks:: [[0119-single-source-navigation-registry]],
  [[0123-refresh-navigation-reference-docs]]
- Commits::
