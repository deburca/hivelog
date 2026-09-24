---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-23
supersedes:
---
# ADR-0103: Delete policy for records with children

## Status
accepted, 2026-09-24. The policy comes from user direction during the
2026-09-23 gap analysis, refined twice the same day before anything was
committed:
1. "block the delete of a record while children exist"
2. "warn, with a link on how to delete the specific children; if the
   warning is ignored, the parent can be deleted"
3. (final) **"where possible block the delete of a record while children
   exist, and where this is not possible provide a warning with a link
   on how to delete the specific children, and if the warning is
   ignored, the parent record can be deleted"**, with an inventory of
   the cases and whether children should be deleted before their parent.

This ADR is that inventory. All three Open questions were confirmed as
recommended on 2026-09-24 — see their resolutions below — clearing the
way for [[0134-delete-dependency-framework]].

## Context
No HiveLog entity has any delete handling: no `preDelete()` /
`postDelete()`, no `hook_entity_predelete()`. Only two delete forms
mention dependent records: `InventoryItemDeleteForm` (counts purchases
and usages) and `ProductDeleteForm` (counts harvest yields). Both still
allow the delete and warn that the records will show as "Unknown
item". Every other delete silently leaves children pointing at a
missing ID. On `cms2` that has already left **1,024 calendar actions**
and **9 inventory items** whose apiary is gone. Orphans fail
apiary-membership access, so no beekeeper can see them; only
`administer hivelog` users can.

Two facts decide which cases can be blocked:
- **Some children have no delete page of their own.** InventoryUsage and
  HarvestYield are entered inline on, and only removable by editing,
  an action log. SensorReading, SensorReadingDaily and HiveInsight
  have no UI at all. Blocking on them would leave the user unable to
  unblock.
- **Some children are created by the system, not the user.**
  `Apiary::postSave()` calls `CalendarAction::seedDefaultsForApiary()`,
  seeding about 30 starter calendar actions into every new apiary (31–38
  per apiary on `cms2`). Blocking an apiary delete on those would
  force the beekeeper to hand-delete dozens of records they never
  created.

## Decision
Each parent → child relationship gets one of four treatments:

- **BLOCK**: delete is refused until the children are gone. Used when
  the child is a user-managed record with its own delete page, the
  reference is required, the child is meaningless or unreachable
  without the parent, **and** deleting the child first is the right
  thing to do (not destroying history the user wants to keep). The
  delete page explains what blocks it and links to where each child
  type is managed. Enforced in the access handler
  (`delete` → `forbidden` with reason), so it holds on every path: the
  form, list buttons, the Navigation top bar, API and drush.
- **WARN**: blocking is not possible or not appropriate: the child has
  no delete page of its own, or deleting it first would destroy
  history (financial records). The page warns, with a count and links
  to the pages where those children can be edited away. The Delete
  button stays; if the user proceeds, the children keep a dangling reference and
  display as "Unknown …", which is today's InventoryItem / Product
  behaviour, now generalised. Form-level only; not an access rule.
- **CASCADE** *(recommendation, needs confirming)*: the children are part
  of the parent itself (its content, plan or telemetry), have no
  independent meaning, and would be an orphan either way. They are
  deleted together with the parent. The confirmation page states what
  goes with it, with counts. Neither block nor warn-with-link works here:
  there is nothing for the user to act on first.
- **DETACH** *(recommendation, needs confirming)*: the reference is
  optional and the child is a valid record without it. Unassigned
  queens and apiary-scoped sensor devices are existing, supported
  states. The page warns and links to where the child can be
  reassigned. On delete, the reference is **cleared** (no dangling ID)
  and the child is kept. Children should *not* be deleted.

### Inventory
"Delete first?" answers whether the children should be deleted before
the parent can be deleted.

| # | Parent | Child (reference) | Ref | Child has own delete page | Delete first? | Treatment | Today |
|---|---|---|---|---|---|---|---|
| 1 | Apiary | Hive (`apiary`) | required | yes | **yes** | BLOCK | orphans |
| 2 | Apiary | CalendarAction (`apiary`) | required | yes, but ~30 are system-seeded | no (the apiary's plan) | CASCADE, with its requirements / yields (#15, #16) | orphans (1,024 on `cms2`) |
| 3 | Apiary | ApiaryActionLog (`apiary`) | required | yes | **yes** | BLOCK | orphans |
| 4 | Apiary | InventoryItem (`apiary`) | required | yes | **yes** | BLOCK | orphans (9 on `cms2`) |
| 5 | Apiary | InventoryPurchase (`apiary`) | required | yes | **yes** | BLOCK | orphans |
| 6 | Apiary | Product (`apiary`) | required | yes | **yes** | BLOCK | orphans |
| 7 | Apiary | SensorDevice (`apiary`) | required | yes | **yes** (or move to another apiary) | BLOCK | orphans |
| 8 | Apiary | HiveInsight (`apiary`) | required | no UI | no (derived) | CASCADE | orphans |
| 9 | Hive | HiveInspection (`hive`) | required | yes | **yes** | BLOCK | orphans |
| 10 | Hive | HiveActionLog (`hive`) | required | yes | **yes** | BLOCK | orphans |
| 11 | Hive | Queen (`hive`) | optional | yes | no, reassign or leave unassigned | DETACH: clear `hive`, set `status` inactive | dangling ref |
| 12 | Hive | SensorDevice (`hive`) | optional | yes | no, hardware is reusable | DETACH: clear `hive`, device becomes apiary-scoped | dangling ref |
| 13 | Hive | HiveInsight (`hive`) | optional | no UI | no (derived) | CASCADE | dangling ref |
| 14 | Queen | QueenObservation (`queen`) | required | yes | **yes** | BLOCK | orphans |
| 15 | CalendarAction | CalendarActionItemRequirement (`calendar_action`) | required | yes | no (part of the action) | CASCADE | orphans |
| 16 | CalendarAction | CalendarActionProductYield (`calendar_action`) | required | yes | no (part of the action) | CASCADE | orphans |
| 17 | CalendarAction | HiveActionLog (`calendar_action`) | required | yes | **yes** (history of doing it) | BLOCK | orphans |
| 18 | CalendarAction | ApiaryActionLog (`calendar_action`) | required | yes | **yes** | BLOCK | orphans |
| 19 | Hive/ApiaryActionLog | InventoryUsage (`*_action_log`) | optional | no (inline on the log) | no (part of the log) | CASCADE | orphans |
| 20 | Hive/ApiaryActionLog | HarvestYield (`*_action_log`) | optional | no (inline on the log) | no (part of the log) | CASCADE | orphans |
| 21 | HiveInspection | HiveActionLog (`inspection`) | optional | yes | no (the log stands alone) | DETACH: clear `inspection` | dangling ref |
| 22 | InventoryItem | InventoryPurchase (`item`) | required | yes | **no**: deleting purchases rewrites past cost reports | WARN, links to the purchases | warns, then allows (links added) |
| 23 | InventoryItem | InventoryUsage (`item`) | required | no (inline on a log) | cannot, independently | WARN, links to each log | warns, then allows (links added) |
| 24 | InventoryItem | CalendarActionItemRequirement (`item`) | required | yes | **yes** | BLOCK | orphans |
| 25 | Product | CalendarActionProductYield (`product`) | required | yes | **yes** | BLOCK | orphans |
| 26 | Product | HarvestYield (`product`) | required | no (inline on a log) | cannot, independently | WARN, links to each log | warns, then allows (links added) |
| 27 | SensorDevice | SensorReading (`sensor_device`) | required | no UI | no (telemetry) | CASCADE (batched) | orphans |
| 28 | SensorDevice | SensorReadingDaily (`sensor_device`) | required | no UI | no (telemetry) | CASCADE | orphans |

Totals: 13 BLOCK, 3 WARN, 9 CASCADE, 3 DETACH.

Row #22 is WARN even though purchases have a delete page. Blocking
would force the user to delete financial records just to retire a
catalog entry, which silently changes past cost reports. Task
[[0045-warn-before-deleting-referenced-items-and-products]] chose
warn-not-block for exactly this reason ("durable catalog cleanup is a
legitimate need"), and the "Delete first?" answer is no. BLOCK is
reserved for rows where deleting the children first is the right
thing to do. Row #5 (Apiary → InventoryPurchase) stays BLOCK: retiring a
whole apiary makes its financial report unreachable anyway, and
leaving its purchases would only create invisible orphans.

Rules that follow from the table:
- **CASCADE never deletes a BLOCK-protected record.** For example, an
  apiary's calendar actions (#2) cascade only once the apiary is
  deletable. At that point #3 / #10 / #17 / #18 have already forced
  every action log to be removed, so no history is lost through the
  cascade.
- A cascaded child's own children follow their own row: deleting a
  calendar action cascades #15 / #16, a sensor device cascades #27 /
  #28, an action log cascades #19 / #20.
- The relationship table is declared **once** in code, extending the
  parent map from [[0116-breadcrumb-builder-parent-map-refactor]].
  Submodule rows (#7, #8, #12, #13, #27, #28) are registered by the
  submodules, since `hivelog` doesn't depend on them.

## Consequences
- Positive:
  - No new orphans from any BLOCK / CASCADE / DETACH row.
  - History (inspections, logs, observations, purchases) can't be lost
    as a side effect.
  - The only remaining source of dangling references is the two WARN
    rows, and only when the user explicitly proceeds.
  - Seeded and derived data doesn't get in the user's way.
- Negative / trade-offs:
  - Retiring a long-used apiary means deleting its hives, logs,
    inventory and products bottom-up first. If that proves too heavy,
    the answer is a status / archive flag, not loosening the blocks.
    Out of scope here.
  - The existing InventoryItem / Product warnings (task
    [[0045-warn-before-deleting-referenced-items-and-products]]) are
    kept as WARN rows #22, #23 and #26, gaining links. No existing
    delete that works today becomes blocked for items or products,
    except where calendar-action requirements / expected yields
    reference them (#24, #25), which are plan records and should go
    first.
  - CASCADE is irreversible and can be large (sensor readings). The
    confirmation page must state counts, and telemetry deletes must be
    batched.
  - Existing orphans (1,024 / 9 on `cms2`) are not fixed by the policy.
    They need a separate report / cleanup tool.

## Open questions
All three confirmed 2026-09-24, as recommended:
- **Confirm CASCADE and DETACH.** They go beyond the "block, else warn"
  direction, and are recommended because neither block nor
  warn-with-link can work for those rows (see Decision). **Confirmed as
  written** — all 9 CASCADE rows and all 3 DETACH rows in the inventory
  stand as listed.
- **Children the current user can't delete.** On a BLOCK row, the
  blocking children may belong to another apiary member, or the user
  may lack `delete` access to them. **Confirmed: stay blocked**, and the
  message says "N records you can't delete, so ask their owner or a site
  administrator". An `administer hivelog` user can always resolve it.
  The alternative (fall back to WARN) would let one member orphan
  another's records.
- **User accounts.** Deleting a Drupal user who owns HiveLog records is
  core's cancel-account flow. **Confirmed out of scope** for this ADR
  and task 0134.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0020-access-parity-custom-routes]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0141-delete-block-relationships]],
  [[0142-delete-cascade-owned-records]],
  [[0143-delete-detach-optional-references]],
  [[0144-delete-warn-historical-references]],
  [[0145-orphan-report-and-cleanup-command]],
  [[0127-shared-delete-form-base]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
