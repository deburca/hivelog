---
type: task
tags: [hivelog/task]
status: todo
priority: high
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0141-delete-block-relationships
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Block deletes on the 13 BLOCK relationships

## Context
Implements the BLOCK rows of
[[0103-delete-policy-for-records-with-children]]. In each, the child is
a user-managed record with its own delete page, the reference is
required, and the child should be **deleted before** the parent can be:

| # | Parent | Blocking child | Where the child is managed (link target) |
|---|---|---|---|
| 1 | Apiary | Hive | Apiary page, Hives section |
| 3 | Apiary | ApiaryActionLog | Apiary page, calendar section |
| 4 | Apiary | InventoryItem | Apiary page, Inventory section |
| 5 | Apiary | InventoryPurchase | Inventory Purchases list |
| 6 | Apiary | Product | Apiary page, Products section |
| 7 | Apiary | SensorDevice (nanoprobe) | Sensor Devices list (delete, or move to another apiary) |
| 9 | Hive | HiveInspection | Hive page, Inspections section |
| 10 | Hive | HiveActionLog | Hive page, calendar section |
| 14 | Queen | QueenObservation | Queen page, Observations section |
| 17 | CalendarAction | HiveActionLog | the hive page calendar sections |
| 18 | CalendarAction | ApiaryActionLog | the apiary page calendar section |
| 24 | InventoryItem | CalendarActionItemRequirement | each calendar action's Required Items section |
| 25 | Product | CalendarActionProductYield | each calendar action's Expected Yield section |

Row #22 (InventoryItem → InventoryPurchase) is deliberately **not**
here. It's a WARN row ([[0144-delete-warn-historical-references]]),
keeping [[0045-warn-before-deleting-referenced-items-and-products]]'s
rationale that catalog cleanup mustn't force deleting financial history.

## Acceptance criteria
- [ ] All 13 rows registered in the relationship registry from
      [[0134-delete-dependency-framework]] with treatment BLOCK and the
      link targets above. Row #7 is registered by nanoprobe.
- [ ] The `delete` branch of the access control handlers for Apiary,
      Hive, Queen, CalendarAction, InventoryItem and Product returns
      `forbidden` with a reason while any of its BLOCK rows has
      children.
- [ ] Delete buttons disappear wherever they already check
      `access('delete')`: detail pages, list Operations, embedded
      lists, the Navigation top-bar tab. Visiting the delete URL
      directly shows the blocked page (not a bare 403), with counts
      and links.
- [ ] Children the user can't delete are counted and explained per the
      ADR's open-question outcome (recommended: "ask their owner or a
      site administrator").
- [ ] Section anchors (`id="hives"`, `id="inventory"`, …) exist on the
      Apiary / Hive / Queen / CalendarAction pages for the link targets.
- [ ] Kernel test per row: delete access forbidden with one child
      present, allowed once it is deleted. The reason names the child
      type and count.
- [ ] Verified live on `cms2`: try to delete an apiary with hives, see
      the blocked page and its links, delete the children, then the
      apiary deletes.
- [ ] Release note: inventory items / products referenced by calendar
      action requirements / expected yields (#24, #25) now block. Any
      other delete that works today for items / products is unchanged.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Deleting an apiary also meets its CASCADE rows (#2 calendar actions,
  #8 insights) from [[0142-delete-cascade-owned-records]]. Those don't
  block, but the blocked page should still list them as "will be
  deleted too" so the user sees the full picture.
- Returning a helpful page (not a bare 403) on a blocked delete form:
  the route's `_entity_access: '<type>.delete'` (from
  [[0133-route-level-entity-access]]) would 403 first. Options: check
  `delete` access *without* the dependency part on the route, and
  render the blocked state in the form. Or keep the full check and use
  a 403 page that explains. Recommend the former. Record the choice.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0133-route-level-entity-access]],
  [[0142-delete-cascade-owned-records]]
- Commits::
