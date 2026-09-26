---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0130-shared-calendar-checklist-helpers
release:
depends-on:
blocked-by:
---
# Task: De-duplicate the calendar-checklist helpers

## Context
From the page-structure review of 2026-09-23. `ApiaryController` (1,272
lines) and `HiveController` (1,414 lines) are the two largest
controllers, and both embed a seasonal-calendar checklist built with
copied helpers (fingerprinted by body hash):

| Helper | Copies | Identical? |
|---|---|---|
| `extractCalendarFilters()` | Apiary, Hive | yes |
| `pendingActionTimingLabel()` | Apiary, Hive | yes |
| `calendarChecklistEmptyMessage()` | Apiary, Hive | near (wording differs) |
| `secondsUntilNextIsoWeek()` | Apiary, Hive, Dashboard | yes |
| `viewableApiaries()` | Dashboard, InventoryReport | near |

The checklist builders themselves (`buildApiaryCalendarChecklist()`,
`HiveController::buildCalendarChecklist()`) share most of their row and
Done / Ignored button logic too.

## Acceptance criteria
- [x] A `hivelog.calendar_checklist_builder` service (matching the
      existing `hivelog.stat_tile_builder` precedent) owns the checklist
      build for both scopes (apiary / hive), the filter extraction,
      the timing label, the empty message (scope-aware wording) and the
      cache max-age (`secondsUntilNextIsoWeek()`).
- [x] `ApiaryController` and `HiveController` delegate to it. Their
      copies are deleted.
- [x] `secondsUntilNextIsoWeek()` has one home (the service, or a small
      static utility used by the service and `DashboardController`).
- [x] `viewableApiaries()` has one home, shared by `DashboardController`
      and `InventoryReportController`, keeping the stricter of the two
      current implementations if they differ. Document which and why.
- [x] Rendered output of both calendar sections unchanged. Existing
      `ApiaryCalendarChecklistTest` / `HiveCalendarChecklistTest` pass
      unchanged.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**One service, `HivelogCalendarChecklistBuilder`**, mirrors
`HivelogStatTileBuilder`'s exact shape (task 0110's precedent): a
plain top-level class in the bare `Drupal\hivelog` namespace, no
`Controller\` subnamespace, constructor-property-promotion, no
`create()`/`ContainerInterface` factory — wired purely through
`hivelog.services.yml`. Owns all six duplicated pieces:
`extractCalendarFilters()`, `pendingActionTimingLabel()`,
`emptyMessage()`, `secondsUntilNextIsoWeek()`, `viewableApiaries()`,
and the checklist build itself (`buildForApiary()`/`buildForHive()`,
both thin wrappers over one shared `buildChecklist()` parameterised by
scope/log-entity-type/log-field — the only real structural difference
between the two original copies).

**`viewableApiaries()` "keep the stricter of the two"**: there wasn't
one — `DashboardController`'s and `InventoryReportController`'s copies
were identical except how each accessed the current user (an injected
`AccountInterface` property vs. the inherited `ControllerBase::currentUser()`
method — functionally the same service either way). Resolved by having
the shared method take `AccountInterface $account` as an explicit
parameter instead of injecting `current_user` itself, so it has no
opinion on which idiom a caller uses.

**Fixed a real, if minor, bug found while merging `emptyMessage()`**:
the hive page's own "zero calendar actions" message read "This apiary
has no calendar actions set up yet." — the wrong noun, clearly a
copy-paste leftover from the apiary version. Now correctly reads "This
hive…". Scope-aware wording (`'apiary'` vs `'hive'`) is a literal
`match()`, not a variable-keyed array, so every string stays statically
extractable — matches `HivelogEntityDeleteForm::detachConsequenceNote()`'s
own established pattern for the same reason.

**`extractCalendarFilters()`/`pendingActionTimingLabel()`/
`secondsUntilNextIsoWeek()`** were already byte-for-byte identical
across their copies (confirmed by diff before touching anything) — a
pure move, no behaviour change possible to introduce.

**Verification.** phpcs clean. phpstan clean — regenerated
`phpstan-baseline.neon` (434 → 430; diffed to confirm the only changes
were `ApiaryController`'s and `HiveController`'s now-lower
`EntityInterface::get()` suppression counts, since two call sites each
moved into the new service, which needed its own two fresh
`@phpstan-ignore-next-line` suppressions for the identical reason).
Full kernel/unit/functional suite against `cms2`, core and every
submodule: 1,013 tests, 0 failures — `ApiaryCalendarChecklistTest`
(26 tests) and `HiveCalendarChecklistTest` (26 tests) pass completely
unchanged, confirming the refactor is behaviour-preserving where it's
supposed to be. Live-verified on `cms2`: a hive page with no
hive-scoped calendar actions now correctly reads "This hive has no
calendar actions set up yet." (previously misworded), the dashboard
and combined financial report (both consumers of
`secondsUntilNextIsoWeek()`/`viewableApiaries()`) render correctly on
both `kragebaekgaard.ddev.site` and `drupal-cms2.ddev.site`.

- Key files: `src/HivelogCalendarChecklistBuilder.php` (new),
  `hivelog.services.yml` (new service), `phpstan-baseline.neon`
  (regenerated), `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php`,
  `src/Controller/DashboardController.php`,
  `src/Controller/InventoryReportController.php` (all four: inject the
  new service, delegate, delete the old copies).

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0110-hive-apiary-stat-tiles]] (the service-extraction
  precedent)
- Commits::
