---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0124-list-page-row-access-filter
release:
depends-on:
blocked-by:
---
# Task: List pages must only show rows the user can view

## Context
**Security / privacy bug**, found in the page-structure review of
2026-09-23 ([[page-structure-consistency]]). HiveLog is multi-tenant:
apiary access is by ownership plus the apiary's beekeepers list, and
everything below an apiary inherits it. The collection routes are gated
by `view own X+view any X+administer hivelog`. But the list builders
never check per-row `access('view')`. Core
`EntityListBuilder::getEntityIds()` does call `accessCheck(TRUE)`, but
for an entity type with no query-access handler that filters nothing.

**Reproduced** with a throwaway kernel test against `cms2` (deleted
afterwards): Alice owns an apiary and a hive, and Bob has only `view own
apiary` + `view own hive`. `ApiaryListBuilder::load()` and
`HiveListBuilder::load()` each returned Alice's entity (listed=1,
viewable by Bob=0). The rendered `/hivelog/apiaries` and `/hivelog/hives`
HTML contained "Alice apiary" / "Alice hive" as links. Names, apiary
locations, owners, hive breeds and statuses are exposed in the list
columns.

> **Correction, 2026-09-23 gap analysis.** This note originally said
> "the canonical pages behind those links correctly return 403". That
> was assumed, not tested, and it is **wrong**. Core routes check only
> `_permission`, never per-entity access, and the core controllers and
> forms don't check either. Bob can open, edit and delete Alice's
> apiary and hive by URL. That is a separate, more severe gap than this
> list leak, and it needs its own task. Fixing this list leak alone
> doesn't close it.

Only `SensorDeviceListBuilder` already overrides `load()` to filter.
Its docblock assumed `ApiClient` / `AiProviderConfig` "never needed
this". Their "own" permissions are owner-based, so a `view own api
client` user would see other owners' clients too. Verify as part of
this task. `CalendarActionController::collection()` already filters by
`access('view')`.

Affected builders (12): `ApiaryListBuilder`, `HiveListBuilder`,
`HiveInspectionListBuilder`, `QueenListBuilder`,
`QueenObservationListBuilder`, `HiveActionLogListBuilder`,
`ApiaryActionLogListBuilder`, `InventoryItemListBuilder`,
`InventoryPurchaseListBuilder`, `ProductListBuilder`, `ApiClientListBuilder`
(collective), `AiProviderConfigListBuilder` (nexus).

## Acceptance criteria
- [x] `HivelogListBuilder::load()` filters loaded entities by
      `access('view')` for the current user. The per-class override in
      `SensorDeviceListBuilder` is removed as redundant, and its
      docblock rationale moved to the base class.
- [x] Every affected builder inherits it. None overrides `load()`
      without calling the parent.
- [x] New kernel test (one data-provider-driven class is fine): for each
      of the 12 entity types, a second user with only the `view own …`
      permission does **not** see the first user's row in `load()` or in
      the rendered `render()` output. The owner, and a user with
      `view any …` / `administer hivelog`, still do. Apiary-descended
      types also cover a **beekeeper member** of the apiary seeing the
      row (membership, not just ownership).
- [x] Collective / nexus: the same test for `ApiClient` /
      `AiProviderConfig`, placed in each submodule's own
      `tests/src/Kernel/`.
- [x] Verified live on `cms2` with a non-admin test user.
- [x] phpcs clean; kernel + unit suite green against `cms2`.
- [ ] Release: this is a security-relevant fix. Ship it in its own patch
      release rather than bundled with the refactors in this project,
      and say so in the release note.

## Implementation notes
- Filtering after loading shortens pages. With the default `$limit` of
  50, a user may see a page of 3 rows while 47 were hidden. Acceptable
  as the immediate fix (correctness over pagination).
  [[0126-unified-list-page-base]] owns the proper pagination story. A
  query-level fix (per-type query conditions on owner / apiary
  membership, or a `query_access` handler) is the long-term option.
  Note it there rather than doing it here.
- Filter with `$entity->access('view', $this->currentUser)`; inject
  `current_user` in `HivelogListBuilder::createInstance()` so subclasses
  don't each need it.
- Check the Apiary / Hive *embedded* child lists
  (`ApiaryController`, `HiveController`) and the dashboard use the same
  access filtering. If any lists children without an `access('view')`
  check, fix it here too and add it to the test.
- Key files: `src/HivelogListBuilder.php`, the 12 builders above,
  `modules/nanoprobe/src/SensorDeviceListBuilder.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0020-access-parity-custom-routes]]
- Tasks:: [[0126-unified-list-page-base]],
  [[0106-sensor-device-management-ui]] (where the one existing filter
  was added)
- Commits::
