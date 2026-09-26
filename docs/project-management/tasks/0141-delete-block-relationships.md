---
type: task
tags: [hivelog/task]
status: done
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
- [x] All 13 rows registered in the relationship registry from
      [[0134-delete-dependency-framework]] with treatment BLOCK and the
      link targets above. Row #7 is registered by nanoprobe.
- [x] The `delete` branch of the access control handlers for Apiary,
      Hive, Queen, CalendarAction, InventoryItem and Product returns
      `forbidden` with a reason while any of its BLOCK rows has
      children.
- [x] Delete buttons disappear wherever they already check
      `access('delete')`: detail pages, list Operations, embedded
      lists. Visiting the delete URL directly shows the blocked page
      (not a bare 403), with counts and links. **The Navigation
      top-bar tab is a documented exception** — see Implementation
      notes; it stays reachable for a blocked owner precisely so it
      can lead to that same explanatory page instead of a bare 403.
- [x] Children the user can't delete are counted and explained per the
      ADR's open-question outcome (recommended: "ask their owner or a
      site administrator").
- [x] Section anchors (`id="hives"`, `id="inventory"`, …) exist on the
      Apiary / Hive / Queen / CalendarAction pages for the link
      targets. CalendarAction itself needs none for this task's own
      rows — see Implementation notes.
- [x] Kernel test per row: delete access forbidden with one child
      present, allowed once it is deleted. The reason names the child
      type and count.
- [x] Verified live on `cms2`: try to delete an apiary with hives, see
      the blocked page and its links, delete the children, then the
      apiary deletes.
- [x] Release note: inventory items / products referenced by calendar
      action requirements / expected yields (#24, #25) now block. Any
      other delete that works today for items / products is unchanged.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **The registry's `manage` sentinel gained a `#fragment` suffix**
  (`HivelogDeleteDependencyRegistry::manageUrl()`), e.g.
  `'parent-canonical#hives'` — split off and applied to the resolved
  URL via `Url::setOption('fragment', …)`. All 7 rows whose target is
  the parent's own page (#1, #3, #4, #6, #9, #10, #14, plus nanoprobe's
  #7) got one; #5 (a global collection) didn't need one. This was a
  small, backwards-compatible extension of 0134's own registry, not a
  new mechanism — every existing NULL/route-name row is untouched.
- **Rows #17, #18, #24, #25 deliberately keep `manage: NULL`** — not an
  oversight. Their real management page belongs to a *different*
  entity per occurrence: #17/#18's blocking hive/apiary action logs
  live on whichever hive's/apiary's own page logged them (a calendar
  action can have logs from several hives), and #24/#25's blocking
  item requirements/expected yields live on whichever calendar
  action(s) reference that item/product (again, potentially several).
  The registry's one-row-one-link design has no way to express "one
  link per child instance," so these stay a bare count — confirmed
  correct against the real ADR-0103 table, not a gap to close later.
  This is also why **CalendarAction's own page needs no new anchor**
  for this task: none of its 13 rows point back at it.
- **The route/button access split, exactly as the ADR text
  recommended**: `checkAccess()` in all six access control handlers now
  has both a `'delete'` case (wraps the ownership/permission result
  through `HivelogDeleteBlockingAccessTrait::blockDelete()`, which
  forbids it with a reason while any BLOCK row has children) and a
  `'delete_route'` case (the same ownership/permission result, with no
  BLOCK check). Only the six `entity.<type>.delete_form` routes'
  `_entity_access` requirement was repointed from `'<type>.delete'` to
  `'<type>.delete_route'` — every other caller (list Operations,
  embedded-list buttons, the apiary/hive/queen page's own action
  buttons) still calls `access('delete')` unchanged and picks up the
  block automatically, since it's now baked into that op's own result.
  See `testDeleteRouteAccessStaysAllowedWhileDeleteOpIsBlocked()`.
- **The Navigation top-bar tab is the one place this split doesn't
  fully hide Delete.** Drupal core's local-task (tab) visibility is
  computed from the *target route's own access*, with no supported
  per-task override — so the "Delete" tab/toolbar link is governed by
  the same `delete_route` (unblocked) check the route itself uses, not
  the blocked `delete` op. In practice this only matters for a user who
  passes the ownership/permission gate and is specifically blocked by
  children: the tab stays visible and, when clicked, lands on the
  explanatory "Can't delete yet" page rather than disappearing outright
  — arguably a better outcome than a silently vanishing action, and
  exactly the trade-off the task's own "recommend the former" text
  accepted. A non-owner (the vast majority of "Delete should be
  hidden") still loses the tab entirely, since they fail the
  ownership/permission check both ops share. No known way to close this
  without a custom `LocalTaskManager` decorator, which felt like a
  disproportionate change for a cosmetic edge case — recorded here
  rather than attempted.
- **BLOCK is not exempted by `administer hivelog`.** All six access
  control handlers restructured their top-of-function admin shortcut
  so it only bypasses the *ownership* check, not the BLOCK check — an
  admin still has to delete the blocking children first. ADR-0103's
  "an administer hivelog user can always resolve it" is about an
  admin's blanket `delete any X` letting them resolve *someone else's*
  blocking children, not an exemption on the parent delete itself; see
  `testAdminIsNotExemptFromBlock()`.
- **A same-parent-type contamination gotcha in the kernel tests**:
  Apiary has five BLOCK rows and CalendarAction has two, so a shared
  `setUp()` fixture (as `HivelogDeleteDependencyFrameworkTest` and
  `ApiaryScopedAccessTest` both use) would leave every row's test
  fixture sitting alongside every other row's for the same parent,
  making "allowed once *the* one child is deleted" false whenever a
  sibling BLOCK row's child was still present from a shared setup.
  `HivelogDeleteBlockRelationshipsTest` instead gives every test method
  its own freshly-created parent. One instance of the same problem
  surfaced *within* a single test too: row #5's InventoryPurchase
  fixture requires an InventoryItem, which is independently BLOCK row
  #4's own child — `testApiaryBlockedByInventoryPurchase` has to delete
  both before the apiary is genuinely unblocked.
- **Two per-request memoizations, not one, had to be reset between a
  test's "blocked" and "allowed" halves**: the entity access control
  handler's own `$accessCache` (core, reset the usual way — see
  `ApiaryScopedAccessTest`'s own precedent) and (task 0134, by design)
  `HivelogDeleteDependencyCounter::countsFor()`'s per-entity cache,
  which has no reset method since nothing in real production code ever
  needed one — a fresh HTTP request naturally gets a fresh counter
  instance. `HivelogDeleteBlockRelationshipsTest::resetDeleteCaches()`
  replaces the container's counter singleton and clears the entity type
  manager's handler cache, simulating what two separate real requests
  already give you for free.
- **A broad, unanticipated regression, found and fixed before
  committing**: baking `blockDelete()` into the base `'delete'` op
  meant *every* existing caller of `access('delete')` on these six
  entity types — not just this task's own new tests — now runs
  `HivelogDeleteDependencyCounter::countsFor()`, which queries every
  row registered against that parent type regardless of which schemas
  a given test happens to have installed. The first full-suite run
  after this task's own code was written turned up 28 errors and 1
  failure across more than a dozen unrelated pre-existing kernel test
  files (cancel-URL tests, pagination tests, report-integration tests,
  …) — each one calling `access('delete')` somewhere in passing,
  without installing schemas for entity types it had no other reason
  to care about. Fixed at the root rather than by patching each test:
  `HivelogDeleteDependencyCounter::countsFor()` now skips a row whose
  child type has no installed schema (checked via
  `EntityLastInstalledSchemaRepositoryInterface::getLastInstalledDefinition()`,
  injected as a new constructor argument — `hivelog.services.yml`
  updated) instead of letting the query fail. This is safe in
  production (a registered row's child type is always installed
  alongside the module that registers it — `rows()` only returns rows
  from currently-enabled modules) and correct in a kernel test (an
  uninstalled child type genuinely has no children to count). This
  also simplified `HivelogDeleteBlockRelationshipsTest`'s and
  `SensorDeviceDeleteBlockTest`'s own `setUp()`, which could drop the
  "install every possible row's schema" installs 0134's own framework
  test still carries (left unchanged there — harmless, and not worth
  touching an already-shipped, already-passing file for this).
  A second, narrower fallout remained after that fix: three genuinely
  pre-existing tests in `ApiaryScopedAccessTest` — `testOwnerCanDeletePrivateApiary`,
  `testOwnerCanDeleteHive`, `testOwnerCanDeleteCalendarAction` — assert
  permission-based delete access using fixtures that (by that test
  class's own design) always have a real BLOCK-row child. Switched
  those three (and only those three) to check `access('delete_route')`
  instead of `access('delete')`, since that's exactly what they always
  meant to test — apiary-scoped ownership/permission access, not the
  delete-dependency graph, which is `HivelogDeleteBlockRelationshipsTest`'s
  job now. Final full-suite run: 701 tests, 11,893 assertions, only the
  3 pre-existing, already-documented, unrelated `DashboardTest`
  Functional cache-redirect errors.
- **Row #7 (nanoprobe) has its own kernel test**
  (`modules/nanoprobe/tests/src/Kernel/SensorDeviceDeleteBlockTest.php`),
  not a 13th method in the core suite — it's the one row this module
  can't declare itself, and a passing test there is also the
  strongest possible proof that `hook_hivelog_delete_dependencies()`
  actually merges at runtime, not just against the test-only row 0134
  already covered.
- **Verified live on `cms2`**: all 13 rows exercised via curl against
  a logged-in admin session — every blocked parent (`/hivelog/apiary/1
  /delete`, `/hivelog/hive/1/delete`, `/hivelog/queen/2/delete`,
  `/hivelog/inventory-item/4/delete`, `/hivelog/product/2/delete`,
  `/hivelog/calendar-action/1049/delete`, `/hivelog/apiary/43/delete`
  for the sensor-device row) rendered "Can't delete yet" with no
  submit button and Cancel still present; every `parent-canonical#…`
  link resolved to the apiary/hive/queen page and matched a real
  `id="…"` anchor there (`#hives`, `#calendar`, `#inventory`,
  `#products`, `#inspections`, `#observations`, `#sensors`); the
  "N you cannot delete yourself" wording appeared for rows with
  another member's records. No blocking child was actually deleted
  against the shared `cms2` database (that would be a real, live data
  change) — the forbidden→allowed transition itself is covered by the
  kernel suite instead, including the two-cache reset above.
- **Release note draft**, for whichever release next bundles this
  task (0116/0117/0125/0126/0127/0129/0134/0141 have all landed on
  `main` since `1.8.9` without a version bump yet):
  > **Delete now blocks while related records still exist (task
  > 0141).** Deleting an apiary, hive, queen, calendar action,
  > inventory item or product is refused — with a page explaining what
  > still references it and a link to go manage those records — while
  > any of the 13 BLOCK relationships in ADR-0103 still has children.
  > **Behaviour change:** inventory items referenced by a calendar
  > action's Required Items, and products referenced by a calendar
  > action's Expected Yield, now block deletion (#24, #25) — previously
  > unblocked. Every other delete that worked before for items/products
  > (including their historical purchases/usages/harvest yields, which
  > stay WARN, not BLOCK) is unchanged.
- Key files: `src/HivelogDeleteBlockingAccessTrait.php` (new),
  `src/{Apiary,Hive,Queen,CalendarAction,InventoryItem,Product}AccessControlHandler.php`,
  `src/Delete/HivelogDeleteDependencyRegistry.php` (`manageUrl()`'s
  `#fragment` support, 7 rows' `manage` values),
  `src/Delete/HivelogDeleteDependencyCounter.php` (skips a row whose
  child type isn't installed), `hivelog.services.yml` (the counter's
  new `EntityLastInstalledSchemaRepositoryInterface` argument),
  `hivelog.routing.yml` (6 `delete_form` routes' `_entity_access`),
  `src/Controller/{Apiary,Hive,Queen}Controller.php` (section anchor
  `id`s), `modules/nanoprobe/nanoprobe.module` +
  `src/SensorPanelBuilder.php` (row #7's anchor), new `tests/src/Kernel/
  HivelogDeleteBlockRelationshipsTest.php`, new `modules/nanoprobe/
  tests/src/Kernel/SensorDeviceDeleteBlockTest.php`,
  `tests/src/Kernel/ApiaryScopedAccessTest.php` (3 tests switched to
  `delete_route`).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0133-route-level-entity-access]],
  [[0142-delete-cascade-owned-records]]
- Commits::
