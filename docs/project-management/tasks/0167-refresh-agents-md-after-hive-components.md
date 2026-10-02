---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: docs
created: 2026-10-02
branch: feature/0167-refresh-agents-md-after-hive-components
release:
depends-on:
blocked-by:
---
# Task: Refresh AGENTS.md after the hive component weight work

## Context
Found in the 2026-10-02 review of [[page-structure-consistency]] (done).
Task [[0138-refresh-agents-md]] existed to stop AGENTS.md drifting, but tasks
[[0162-inventory-item-weight-field]], [[0163-hive-component-entity-and-empty-weight]]
and [[0164-net-colony-honey-weight-stat-tile]] (release 2.2.0) did not update
it. `grep -c "HiveComponent\|hive_component" AGENTS.md` returns 0, and it
still states "15 content entity types" at lines 14 and 153. Gaps:
- `HiveComponent` is missing from "Content entities" (hive + item +
  quantity, same-apiary and stock-availability guards in `preSave()` and the
  form, no canonical route, no collection route).
- `Hive::getEmptyWeightKg()` (NULL-on-incomplete) and the three new
  `InventoryItem` methods (`getTotalPurchasedQuantity()`,
  `getAssignedToHivesQuantity()`, `getAvailableForHiveAssignmentQuantity()`)
  aren't mentioned; `InventoryItem` doesn't list `weight_kg`.
- The `default:hivelog_hive_component_item` selection plugin and why it is
  separate from `ApiaryScopedSelection` isn't documented.
- Delete-dependency registry: two rows (`0106-1`, `0106-2`) outside ADR-0103's
  numbering aren't mentioned.
- The nanoprobe `nanoprobe_net_weight` stat tile isn't mentioned under
  Submodules.
- The Hive page's new Hive Components section (`#components` anchor) isn't
  mentioned in the Routing section.
- Entity counts ("defines 15", "add 6 more") need rechecking against the
  actual entity classes.

## Acceptance criteria
- [x] Every item in the list above is reflected in AGENTS.md, in the section
      it belongs to (no new top-level section).
- [x] Entity counts verified against `src/Entity/` and each submodule's
      entity classes, not copied from the old number.
- [x] The CI section's baseline figure (stated as 440) is rechecked against
      `phpstan-baseline.neon` (currently 453 per task 0163's notes).
- [x] Decide whether the "update AGENTS.md" step belongs in the task
      template's acceptance criteria so it can't be skipped again; if so, add
      it to `docs/project-management/templates/task.md`.
- [x] Docs-only: no version bump or release needed.

## Implementation notes
- Docs-only change; no code, no tests. Files: `AGENTS.md`,
  `docs/project-management/templates/task.md`.
- Counts verified against the code: 16 core entity classes in
  `src/Entity/`; submodules add 6 (nanoprobe 3, collective 1, nexus 2) and
  `assimilate` adds none. The old intro line said "6 more (5 real, 1
  development-only)", which was wrong on its face — the same file says
  `assimilate` adds no entity types — so it now says three of four
  submodules add the 6.
- The phpstan baseline figure is now counts-free ("sum its `count:` values")
  rather than a number: the baseline totals 453 today, up from the 440 the
  file stated, and a hard-coded number goes stale on every baseline
  change. The "defines 15 entity types" line under "Content entities" was
  made counts-free too; only the intro line states a count.
- New AGENTS.md content: a "Hive composition and weight" group (the
  `HiveComponent` entity, its two guards and the two-layer convention,
  apiary-wide availability, the dedicated
  `default:hivelog_hive_component_item` selection plugin and why it isn't
  folded into `ApiaryScopedSelection`, the `0106-N` registry row style);
  `Hive::getEmptyWeightKg()` on the Hive bullet; `weight_kg` on the
  InventoryItem bullet; the `nanoprobe_net_weight` tile under nanoprobe;
  the `hivelog.hive_component.add` route; the Components table in the hive
  controller description; the two ADR-0106 rows in the delete-dependency
  service bullet.
- Decision: added "`AGENTS.md` updated if entity types, routes, services,
  hooks or conventions changed" to the task template's acceptance criteria,
  so the step is a visible checkbox on every task. The release template
  already had a similar line, but it runs after the work is done.
- Docs-only: no version bump or release, per the standing rule.

## Related
- Project:: [[page-structure-consistency]] (done; follow-up)
- Tasks:: [[0138-refresh-agents-md]], [[0163-hive-component-entity-and-empty-weight]]
- Decisions:: [[0106-hive-component-weight-tracking]]
- Commits::
