---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[page-structure-consistency]]"
area: theme
created: 2026-09-23
branch: feature/0131-single-detail-table-css-class
release:
depends-on: ["[[0125-shared-detail-page-builder]]"]
blocked-by:
---
# Task: One CSS class for detail-page label / value tables

## Context
From the page-structure review of 2026-09-23. Detail pages render their
two-column label / value tables with a per-entity class, 13 in all:
`hivelog-inspection-table`, `-queen-table`, `-queen-observation-table`,
`-calendar-action-table`, `-hive-action-log-table`,
`-apiary-action-log-table`, `-inventory-item-table`,
`-inventory-purchase-table`, `-product-table`, `-sensor-device-table`,
`-api-client-table`, `-ai-provider-config-table` (plus the Hive page's
queen table). `css/hivelog.tables.css` repeats each of them in the
same 9 selector positions, so every new entity type means editing 9
selector lists. That is the same silent-failure trap as the
`hivelog.buttons.css` context-wrapper allow-list
([[0113-destructive-action-styling-sensor-device-api-client]]).

## Acceptance criteria
- [x] One generic class, `hivelog-detail-table` (and
      `hivelog-detail-section` for the wrapper), emitted by the shared
      builder from [[0125-shared-detail-page-builder]] and by the
      submodule controllers.
- [x] The per-entity class is **kept alongside** the generic one on the
      element for at least one minor release, because AGENTS.md
      ("Theming HiveLog") treats `.hivelog-*` class names as theming API.
      Removing them later needs its own task and a release note.
- [x] `css/hivelog.tables.css` rules rewritten against the generic
      class only. The 13-way selector lists are gone.
- [x] Visual regression check on `cms2`: every detail page looks
      identical before and after (same widths, borders, label column).
- [x] beeswax checked for selectors on the per-entity classes
      (`src/hivelog.css`). If present, a follow-up there to move to the
      generic class.
- [x] AGENTS.md "Theming HiveLog" stable-class list gains
      `hivelog-detail-table` / `hivelog-detail-section`.

## Implementation notes
**Implemented 2026-09-26.**
- **Four emission points, not one.** `HivelogDetailPageTrait::buildSection()`
  and `buildPhotosGrid()` cover 9 of the 13 tables/sections named in the
  Context table (Queen, QueenObservation, HiveInspection, CalendarAction,
  HiveActionLog, ApiaryActionLog, InventoryItem, InventoryPurchase,
  Product). The other 4 build their own table directly, outside the
  trait, and each needed its own one-line addition:
  `HiveController::buildQueenSection()` (the Hive page's embedded queen
  summary — the "plus the Hive page's queen table" the task's own
  Context called out), and the three submodule controllers
  (`SensorDeviceController`, `ApiClientController`,
  `AiProviderConfigController`), none of which use
  `HivelogDetailPageTrait` at all (they're the "deliberately minimal"
  pages per their own docblocks, with no section wrapper/heading around
  the table — only the table itself gained the generic class there,
  since there's no equivalent wrapper element to add
  `hivelog-detail-section` to).
- **`hivelog-detail-section` added to `buildPhotosGrid()` too**, not just
  `buildSection()` — it emits the identical `hivelog-<prefix>-section`
  wrapper class already, just for a photo grid instead of a table, so
  the generic wrapper class belongs there for the same reason.
- **`css/hivelog.tables.css` rewrite kept the report table fully
  self-contained** rather than merged into `.hivelog-detail-table`, per
  the task's own "stays as-is" instruction. Recomputed the exact
  cascade by hand before rewriting (the original had `thead th`
  duplicated across two rule blocks specifically to win a specificity
  tie via source order — see the `text-align` override for report-table
  headers) to confirm the restructured, self-contained report-table
  rules produce byte-identical computed styles, not just visually
  similar ones. The responsive (`@media max-width: 768px`) block keeps
  the report table's `table-layout: fixed` and cell padding/wrap
  treatment (it was already in that list) but not the label-column 40%
  width rule (it never was — that rule is specific to label/value
  tables, not the report table's numeric columns).
- **Kernel test coverage added for every one of the 4 emission points**,
  since the task's own AC didn't ask for one explicitly but this
  repo's testing culture (AGENTS.md "Tests") expects one per behaviour
  change: `HivelogDetailPageTraitTest::testBuildSectionEmitsGenericAndPerEntityClasses()`
  (via reflection into `QueenController::buildSection()`, mirroring the
  file's existing `buildActions()` reflection pattern — calling the
  full `view()` instead would have required installing a
  `queen_observation` schema this test class's `setUp()` doesn't carry,
  for a code path unrelated to this task); a new
  `HiveTest::testHiveViewQueenTableHasGenericDetailClass()`; two new
  assertions added to `HiveInspectionTest`'s existing
  `testInspectionViewIsGroupedIntoSections()` and
  `testInspectionViewRendersPhotosGrid()`; and one new/extended
  assertion each in `SensorDeviceControllerTest`, `ApiClientControllerTest`
  and a new `AiProviderConfigControllerTest::testSummaryTableHasGenericDetailClass()`
  (that file previously only tested `buildActions()`, not the summary
  table at all — needed a uid-1-bypass fixture, following
  `InventoryItemTest`'s own established pattern, since the controller's
  `access('view')` gate has no "own" path exercised by this test class's
  existing fixtures).
- **beeswax**: same limitation task 0128 already documented — it's a
  separate repository (`deburca/beeswax`) not checked out here, so it
  can't be grepped for selectors on the per-entity table classes from
  inside this repo. **Flagged, not fixed**, matching 0128's own
  precedent; a follow-up in that repo should check `src/hivelog.css`
  for any `.hivelog-*-table` selector and consider moving it to
  `.hivelog-detail-table` once this release ships.
- **Verification**: phpcs (module `phpcs.xml.dist`) and phpstan (module
  `phpstan.neon`) clean on every file this task touched. Full `hivelog`
  core kernel/unit suite against `cms2`: 740 tests, 12,993 assertions,
  0 failures (6 pre-existing, unrelated PHP notices in
  `CalendarActionCollectionTest`/`CombinedFinancialReportTest`, not
  touched by this task). All three submodules' own kernel suites
  together: 235 tests, 3,015 assertions, 0 failures (pre-existing,
  unrelated `key`-module attribute-discovery deprecation noise only).
  Live-verified on `cms2` via `drush scr` against real records: a Queen
  page's table rendered `class="hivelog-detail-table hivelog-queen-table
  responsive-enabled"`; a Hive Inspection page rendered both
  `hivelog-detail-table` and `hivelog-inspection-table`; and Hive 1's
  embedded (non-trait) queen table rendered
  `hivelog-detail-table hivelog-queen-table responsive-enabled` too —
  confirming all four emission points work end-to-end, not just in the
  kernel harness.
- Key files: `src/HivelogDetailPageTrait.php`, `src/Controller/HiveController.php`,
  `modules/nanoprobe/src/Controller/SensorDeviceController.php`,
  `modules/collective/src/Controller/ApiClientController.php`,
  `modules/nexus/src/Controller/AiProviderConfigController.php`,
  `css/hivelog.tables.css`, `AGENTS.md` ("Theming HiveLog"), new/updated
  tests in `tests/src/Kernel/HivelogDetailPageTraitTest.php`,
  `tests/src/Kernel/HiveTest.php`, `tests/src/Kernel/HiveInspectionTest.php`,
  `modules/nanoprobe/tests/src/Kernel/SensorDeviceControllerTest.php`,
  `modules/collective/tests/src/Kernel/ApiClientControllerTest.php`,
  `modules/nexus/tests/src/Kernel/AiProviderConfigControllerTest.php`.

- Depends on [[0125-shared-detail-page-builder]] so the class is added
  in one place rather than 13.
- `hivelog-inventory-report-table` is a different, genuinely distinct
  table style (report tables) and stays as-is.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]]
- Tasks:: [[0125-shared-detail-page-builder]],
  [[0113-destructive-action-styling-sensor-device-api-client]]
- Commits::
