---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Every item in the list above is reflected in AGENTS.md, in the section
      it belongs to (no new top-level section).
- [ ] Entity counts verified against `src/Entity/` and each submodule's
      entity classes, not copied from the old number.
- [ ] The CI section's baseline figure (stated as 440) is rechecked against
      `phpstan-baseline.neon` (currently 453 per task 0163's notes).
- [ ] Decide whether the "update AGENTS.md" step belongs in the task
      template's acceptance criteria so it can't be skipped again; if so, add
      it to `docs/project-management/templates/task.md`.
- [ ] Docs-only: no version bump or release needed.

## Implementation notes
- Docs-only change; no code, no tests.
- Prefer counts-free wording where the number would go stale again (as the
  Services section already does).

## Related
- Project:: [[page-structure-consistency]] (done; follow-up)
- Tasks:: [[0138-refresh-agents-md]], [[0163-hive-component-entity-and-empty-weight]]
- Decisions:: [[0106-hive-component-weight-tracking]]
- Commits::
