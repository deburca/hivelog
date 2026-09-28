---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[collection-page-filter-coverage]]"
area: routing
created: 2026-09-28
branch: feature/0155-apiary-and-queen-list-filters
release:
depends-on:
blocked-by:
---
# Task: Filters on the Apiaries and Queens lists

## Context
`/hivelog/apiaries` and `/hivelog/queens` are the first two of seven
collection pages [[collection-page-filter-coverage]] is adding a
filter to, following the exact shape
[[0132-filters-on-hive-inspection-observation-lists]] already
established. Neither entity has an existing embedded filtered table
elsewhere to mirror (unlike Hive/Inspection/QueenObservation, which
matched their apiary/hive-page embedded versions) — both filter forms
are new, single-purpose, full-page-only designs.

## Acceptance criteria
- [x] `HivelogApiaryFilterForm` (new): fields **Name contains** (text,
      `LIKE` on `name`) and **Visibility** (select, from `apiary`'s own
      `visibility` field `allowed_values` — Private/Public). GET
      method, `hivelog-filter-form` styling, Reset via
      `Url::fromRoute('<current>')` — same shape as
      `HivelogHiveFilterForm` minus the optional-parent complexity
      (Apiaries has no embedded counterpart to support).
- [x] `HivelogQueenFilterForm` (new): fields **Status**, **Breed**,
      **Temperament** (selects, from `queen`'s own field
      `allowed_values`) and **Name contains** (text — an `OR` condition
      group across `name` **and** `origin`, since both are meaningful
      free-text identifiers on a queen record).
- [x] `ApiaryListBuilder` / `QueenListBuilder` each override
      `getFilterForm()` / `applyFilters()` / `hasActiveFilters()` per
      the established pattern (a `currentFilters()` helper reading the
      current request via `$this->requestStack`, same as
      `HiveListBuilder`'s own).
- [x] Table cache context / empty-state message need no new code —
      `HivelogListBuilder::render()` already adds `url.query_args` and
      switches the empty-state message generically whenever
      `getFilterForm()` is non-empty (task 0132's own generic hooks).
- [x] Kernel tests: for each list, at least one filter narrows the
      rows, Reset clears the query string back to the bare collection
      route, and the empty-state message distinguishes "no records
      yet" from "no records match these filters."
- [x] Verified live on `cms2`: `/hivelog/apiaries?visibility=public`
      and `/hivelog/queens?breed=buckfast` both narrow correctly, with
      a working Reset.
- [x] phpcs clean; phpstan clean (baseline `431 → 433` — the two new
      form classes' own `create()` methods hit the same already-known
      `new.static` pattern `HivelogHiveFilterForm` itself is baselined
      for; diff-confirmed only those two new entries).

## Implementation notes
- **Confirmed no existing test breaks from adding a filter form**:
  `QueenTest.php` renders `QueenListBuilder::render()` with no routed
  request pushed at all (unlike this task's own tests, which push one
  via `pushRoutedRequest()`) — worth checking explicitly, since
  `HivelogQueenFilterForm::buildForm()` resolves `Url::fromRoute('<current>')`
  for its Reset button, and task 0132's own notes flagged `<current>`
  as needing a *real* route match to resolve correctly in a kernel
  test. Ran `QueenTest.php`/`CbrFieldTest.php`/
  `HivelogListBuilderPaginationTest.php`/`ListBuilderAccessFilterTest.php`
  together first, before writing this task's own new test file: all
  73 pre-existing tests passed unchanged.
- **`new static()` in both new form classes' `create()` is correct as
  written, not a bug to fix** — unlike the plain (non-`FormBase`)
  classes task 0146/0150 changed to `new self()`, a `FormBase`
  subclass's `create()` conventionally keeps `new static()` for real
  late-static-binding reasons (a themed override could legitimately
  subclass a form). `HivelogHiveFilterForm.php` was already baselined
  for the identical phpstan finding; these two new files just needed
  the same baseline treatment, not a different `create()` shape.
- **Live-verified on `cms2`**: `/hivelog/apiaries?visibility=public`
  correctly showed "No apiaries match the current filters." (every
  demo apiary is `private`); `/hivelog/queens?breed=buckfast` narrowed
  3 queens to 2, with Reset linking to the bare `/hivelog/queens`.
- Key files: `src/Form/HivelogApiaryFilterForm.php` (new),
  `src/Form/HivelogQueenFilterForm.php` (new),
  `src/ApiaryListBuilder.php`, `src/QueenListBuilder.php`,
  `tests/src/Kernel/ApiaryQueenFilterTest.php` (new, 4 tests),
  `phpstan-baseline.neon`.
- No entity schema change → **no update hook required**.
- Template followed exactly: `src/Form/HivelogHiveFilterForm.php`
  (task 0132) — `buildForm()` shape, `filter_actions` container naming
  (not `actions`, to avoid Gin's sticky-top-bar relocation), hidden
  `form_build_id`/`form_token`/`form_id`, static `extract()`/`apply()`,
  an `escapeLike()` helper for the text field(s).
- phpcs clean; phpstan clean. Full kernel/unit/functional suite against
  `cms2`, core and every submodule: **1,080 tests, 0 failures** (up
  from 1,076 — the 4 new tests).

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]]
- Commits::
