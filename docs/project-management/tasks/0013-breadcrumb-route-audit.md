---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-06-17
branch: feature/0013-breadcrumb-route-audit
release: 1.4.0
---
# Task: Breadcrumb route audit

## Context
`src/Breadcrumb/HivelogBreadcrumbBuilder.php` already builds trails for apiary,
hive, hive_inspection, queen, and queen_observation routes (plus a `hivelog.`
catch-all in `applies()`). Before changing anything we need a route-by-route
audit of expected vs actual breadcrumbs, and to record any remaining gaps after
closing the older [[0002-breadcrumb-queen-canonical]] task as already
implemented. Foundation task for [[breadcrumb-consistency]].

## Acceptance criteria
- [x] A matrix in this note: every route in `hivelog.routing.yml`
      (canonical / add_form / edit_form / delete_form / collection + scoped add
      routes) → expected trail → observed trail → gap (Y/N).
- [x] Record that [[0002-breadcrumb-queen-canonical]] was already satisfied by
      the current codebase and closed during vault reconciliation.
- [x] Flag routes where a parameter is **not** upcast to an entity object (the
      `is_object()` guards), which silently drop ancestry.
- [x] Confirm whether any non-page `hivelog.*` routes need to be excluded from
      `applies()` as new routes are added.
- [x] Confirm the current implementation against [[0013-breadcrumb-policy]].

## applies() coverage

`applies()` matches six prefixes:

| Prefix | Routes matched |
|---|---|
| `entity.apiary.` | collection, add_form, canonical, edit_form, delete_form |
| `entity.hive.` | collection, canonical, edit_form, delete_form |
| `entity.hive_inspection.` | collection, canonical, edit_form, delete_form |
| `entity.queen.` | collection, add_form, canonical, edit_form, delete_form |
| `entity.queen_observation.` | collection, canonical, edit_form, delete_form |
| `hivelog.` | hivelog.hive.add, hivelog.inspection.add, hivelog.queen.add, hivelog.queen_observation.add |

All 28 routes in `hivelog.routing.yml` are covered. No routes are missed.

## Parameter upcast check

All entity routes in `hivelog.routing.yml` declare `type: entity:X` under
`options.parameters`. Drupal therefore upcasts all parameters to entity objects
before `build()` is called. The `is_object()` guards in `build()` are
protective but will always pass for the current route set — **no routes silently
drop ancestry due to missing upcast declarations**.

## Route audit matrix

Legend: ✓ = correct, no change needed. GAP = diverges from expected.

### Apiary routes

| Route | applies()? | Expected trail | Actual trail | Gap? |
|---|---|---|---|---|
| `entity.apiary.collection` | ✓ | Home › HiveLog | Home › HiveLog | ✓ |
| `entity.apiary.add_form` | ✓ | Home › HiveLog | Home › HiveLog | ✓ acceptable — no parent context available |
| `entity.apiary.canonical` | ✓ | Home › HiveLog | Home › HiveLog | ✓ (self omitted per ADR-0013) |
| `entity.apiary.edit_form` | ✓ | Home › HiveLog › Apiary | Home › HiveLog › Apiary | ✓ |
| `entity.apiary.delete_form` | ✓ | Home › HiveLog › Apiary | Home › HiveLog › Apiary | ✓ |

### Hive routes

| Route | applies()? | Expected trail | Actual trail | Gap? |
|---|---|---|---|---|
| `entity.hive.collection` | ✓ | Home › HiveLog | Home › HiveLog | ✓ acceptable — flat list, no parent |
| `hivelog.hive.add` | ✓ | Home › HiveLog › Apiary | Home › HiveLog › Apiary | ✓ (`{apiary}` upcast, apiary link added) |
| `entity.hive.canonical` | ✓ | Home › HiveLog › Apiary | Home › HiveLog › Apiary | ✓ (self omitted) |
| `entity.hive.edit_form` | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ |
| `entity.hive.delete_form` | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ |

### Inspection routes

| Route | applies()? | Expected trail | Actual trail | Gap? |
|---|---|---|---|---|
| `entity.hive_inspection.collection` | ✓ | Home › HiveLog | Home › HiveLog | ✓ acceptable |
| `hivelog.inspection.add` | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ (`{hive}` upcast, full ancestry resolved) |
| `entity.hive_inspection.canonical` | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ (self omitted) |
| `entity.hive_inspection.edit_form` | ✓ | Home › HiveLog › Apiary › Hive › Inspection | Home › HiveLog › Apiary › Hive › Inspection | ✓ |
| `entity.hive_inspection.delete_form` | ✓ | Home › HiveLog › Apiary › Hive › Inspection | Home › HiveLog › Apiary › Hive › Inspection | ✓ |

### Queen routes

| Route | applies()? | Expected trail | Actual trail | Gap? |
|---|---|---|---|---|
| `entity.queen.collection` | ✓ | Home › HiveLog | Home › HiveLog | ✓ acceptable |
| `entity.queen.add_form` | ✓ | Home › HiveLog | Home › HiveLog | **GAP (acceptable)** — generic add form has no `{hive}` or `{queen}` param; trail is correct but lacks hive context. The scoped `hivelog.queen.add` is the intended UI entry point and provides full ancestry. Not worth fixing — `entity.queen.add_form` is not surfaced in the module UI. |
| `hivelog.queen.add` | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ (`{hive}` upcast, full ancestry resolved) |
| `entity.queen.canonical` (assigned) | ✓ | Home › HiveLog › Apiary › Hive | Home › HiveLog › Apiary › Hive | ✓ (self omitted) |
| `entity.queen.canonical` (unassigned) | ✓ | Home › HiveLog | Home › HiveLog | ✓ graceful short trail — queen has no hive, ancestry stops at HiveLog |
| `entity.queen.edit_form` | ✓ | Home › HiveLog › Apiary › Hive › Queen | Home › HiveLog › Apiary › Hive › Queen | ✓ |
| `entity.queen.delete_form` | ✓ | Home › HiveLog › Apiary › Hive › Queen | Home › HiveLog › Apiary › Hive › Queen | ✓ |

### Queen observation routes

| Route | applies()? | Expected trail | Actual trail | Gap? |
|---|---|---|---|---|
| `entity.queen_observation.collection` | ✓ | Home › HiveLog | Home › HiveLog | ✓ acceptable |
| `hivelog.queen_observation.add` | ✓ | Home › HiveLog › Apiary › Hive › Queen | Home › HiveLog › Apiary › Hive › Queen | ✓ (`{queen}` upcast, full ancestry resolved) |
| `entity.queen_observation.canonical` | ✓ | Home › HiveLog › Apiary › Hive › Queen | Home › HiveLog › Apiary › Hive › Queen | ✓ (self omitted) |
| `entity.queen_observation.edit_form` | ✓ | Home › HiveLog › Apiary › Hive › Queen › Observation | Home › HiveLog › Apiary › Hive › Queen › Observation | ✓ |
| `entity.queen_observation.delete_form` | ✓ | Home › HiveLog › Apiary › Hive › Queen › Observation | Home › HiveLog › Apiary › Hive › Queen › Observation | ✓ |

## Summary of findings

**No code changes required by this audit.** The breadcrumb builder is correct
for all routes currently in `hivelog.routing.yml`.

**One acceptable gap:** `entity.queen.add_form` produces `Home › HiveLog` with
no hive ancestry — not fixable without a parameter that doesn't exist on that
route. The scoped `hivelog.queen.add` is the intended UI entry point and is
unaffected. No action for Task 0014.

**Future risk — `hivelog.` catch-all:** When Task 0001 (CSV export) is
implemented, its route (`hivelog.queen.observations_csv`) will match `applies()`
via the `hivelog.` prefix. `build()` will extract the `{queen}` parameter
(upcast) and produce a queen ancestry breadcrumb — which is **incorrect** for a
file-download response. Task 0014 must exclude non-page `hivelog.*` routes from
`applies()` when Task 0001 is implemented. At minimum, add an explicit exclusion
for routes ending in a file extension or matching a known download pattern.

**ADR-0013 compliance:** Confirmed. All four policy rules are satisfied:
1. Trails are Home → HiveLog → Apiary → Hive → … built from upcast ancestors. ✓
2. Canonical pages omit the self crumb. ✓
3. `applies()` currently contains no non-page routes — safe today. ✓
4. `applies()` is documented in `AGENTS.md` as a maintenance touch-point. ✓

**Task 0002 reconciliation:** `entity.queen.canonical` correctly receives a full
ancestry trail including apiary and hive. Confirmed implemented and closed.

## Findings for Task 0014

Task 0014 has **no code fixes** to implement from this audit. Its scope should
be:
1. Document the `entity.queen.add_form` gap as an accepted limitation.
2. Add the `hivelog.queen.observations_csv` exclusion to `applies()` **at the
   same time Task 0001 is implemented** — not before.
3. Extend test coverage per Task 0015.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]]
- Reconciles:: [[0002-breadcrumb-queen-canonical]]
- Feeds into:: [[0014-implement-breadcrumb-consistency-fixes]], [[0015-breadcrumb-test-coverage]]
