---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0143-delete-detach-optional-references
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Detach optional references when their target is deleted

## Context
Implements the DETACH rows of
[[0103-delete-policy-for-records-with-children]]. In each, the
reference is **optional** and the child is a complete, valid record
without it. The children should **not** be deleted before the parent,
and not with it either. Today they're left holding a dangling ID.
*DETACH is a recommendation in the ADR. Confirm it before starting.*

| # | Parent deleted | Child kept | On delete |
|---|---|---|---|
| 11 | Hive | Queen (`hive`) | clear `hive`, set `status` = inactive. An unassigned queen is an existing, supported state (AGENTS.md "Content entities"; breadcrumb "unassigned queen" handling). Her observations stay with her. |
| 12 | Hive | SensorDevice (`hive`) | clear `hive`. The device stays registered to its apiary as an apiary-scoped device, and the physical hardware is reusable. |
| 21 | HiveInspection | HiveActionLog (`inspection`) | clear `inspection`. The log keeps its own record of the action. |

## Acceptance criteria
- [ ] The three rows are registered with treatment DETACH, each with a
      "reassign" link target: the queen's edit form, the sensor
      device's edit form, and the log's edit form respectively. Row #12
      is registered by nanoprobe.
- [ ] The delete confirmation page warns: "Will be kept but unlinked:
      2 queens (they will become unassigned and inactive), 1 sensor
      device (it will become apiary-scoped)", with links to reassign
      them first if wanted.
- [ ] On delete by **any** path, the framework's execution hook clears
      the reference (and, for #11, sets `status` inactive), saving each
      child through the entity API so derived logic runs: `Queen::preSave()`
      and the sensor device's own validation.
- [ ] #12: confirm a hive-scoped device's metrics still make sense
      apiary-scoped (e.g. a hive weight sensor). If not, the warning
      text says the device should be reassigned to another hive. Record
      the outcome in Implementation notes.
- [ ] Kernel test per row: after deleting the parent, the child still
      exists with the reference empty (and, for #11, `status`
      inactive), and no other field changed.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Queen #11 interacts with `Queen::preSave()`'s one-active-queen
  invariant. Clearing `hive` and setting inactive in the same save
  avoids a transient "active queen with no hive" state.
- Key files: the framework's execution hook, `src/Entity/Queen.php`
  (if helper logic is needed), nanoprobe registration for #12.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]]
- Commits::
