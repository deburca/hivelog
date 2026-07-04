---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[seasonal-calendar-and-hive-action-tracking]]"
area: entity
created: 2026-07-03
branch: feature/0022-seed-default-calendar-on-apiary-creation
release:
depends-on: ["[[0017-calendar-action-entity-and-schema]]"]
blocked-by:
---
# Task: Seed a default starter calendar when a new apiary is created

## Context
Resolves the project's "does a new apiary start empty?" question — no: per
[[0025-seasonal-calendar-and-hive-action-tracking]], a new apiary should get
a usable, fully-editable starter calendar rather than a blank page. Per
explicit user direction, the starter set should be **as exhaustive as
practical** — it is trivial to disable/delete an entry that doesn't apply,
but far more effort for a beekeeper to think of and add every seasonal duty
themselves from a blank calendar.

## Acceptance criteria
- [x] `CalendarAction::DEFAULT_STARTER_CALENDAR` — a `const` array of **31**
      starter definitions (`title`, `description`, `category`, `week_start`,
      `week_end`), following the same constant-lookup-table pattern as
      `Queen::QUEEN_COLOUR_MAP`. Expanded well beyond the original
      9-item illustrative set in ADR-0025 to cover the full annual cycle at
      roughly biweekly-to-monthly granularity: two varroa treatments plus a
      broodless-period midwinter treatment plus a late-summer follow-up
      check (4 varroa-related entries total), spring buildup (equalising,
      reversing brood boxes, supering), swarm prevention (weekly checks,
      bait hives, splits/increase), queen rearing and two separate
      requeening windows, both honey harvests plus a mid-season stores
      check, autumn/winter prep (feeding, mouse guards, woodpecker
      protection, ventilation, final stores check), a mid-season
      disease-focused check, colony consolidation (assessing winter losses,
      combining weak colonies), general apiary hygiene/equipment prep, an
      end-of-season record-keeping review, and — specific to this module's
      Irish beekeeping context (it already has a dedicated `cbr_number`
      field) — a Central Beehive Registration renewal reminder.
- [x] `CalendarAction::seedDefaultsForApiary(Apiary $apiary): void` — static
      helper that creates and saves one `CalendarAction` per entry in the
      constant, all `enabled = TRUE`, `recurring = TRUE`, referencing the
      given apiary. No "is default" flag — seeded rows are ordinary,
      fully-editable/deletable entities once created.
- [x] `Apiary::postSave($storage, $update)` override: calls
      `parent::postSave($storage, $update)`, then
      `CalendarAction::seedDefaultsForApiary($this)` only when `!$update`.
- [x] Seeding failures do not block apiary creation — wrapped in a
      try/catch logging via `\Drupal::logger('hivelog')->error()`.
- [x] Verified (not just by inspection — see below): creating a new
      `Apiary` results in exactly 31 `CalendarAction` rows; re-saving
      (updating) an existing apiary does not duplicate them.
- [x] `ddev drush cr` clean (no schema change in this task, as anticipated
      — no update hook was added).

## Verification (against `/Users/paddy/Development/cms2`)
- No regression this time — the full existing suite (124 kernel / 2186
  assertions, 54 unit / 196 assertions) still passes even though
  `postSave()` now seeds 31 rows on **every** apiary creation across the
  whole existing test suite; nothing asserts an exact/absent
  `calendar_action` count, so this was a non-event rather than a fix.
- Creating a real `Apiary` via the entity API seeds **exactly 31**
  `CalendarAction` rows; spot-checked 8 titles across the year (Midwinter
  Cluster Check, CBR renewal, both harvests, both main varroa treatments,
  the midwinter broodless-period treatment, the record-keeping review) —
  all present. All 31 rows are `enabled = TRUE` and `recurring = TRUE`.
- **Every one of the 31 seeded rows passes full entity `validate()`** with
  zero violations — confirms the whole constant is schema-valid (required
  fields present, `week_start`/`week_end` within the 1–53 range, category
  values all real enum members), not just spot-checked.
- Re-saving (updating) the same apiary leaves the count at exactly 31 — no
  duplication.
- `SimpleBulletText::render()` correctly turns the CBR entry's `- ` lines
  into `<ul><li>` markup — confirms the new descriptions are compatible
  with the existing bullet-rendering convention, not just plain text.
- **Genuine failure-injection test** (not just reading the source for a
  try/catch): renamed the live `hivelog_calendar_action` table out from
  under a running site, then created a new apiary. The seeding `INSERT`
  genuinely failed with a real `DatabaseExceptionWrapper`; watchdog logged
  it (both Drupal's own exception logging and the explicit `hivelog`
  logger message written by the `catch` block); **the apiary itself still
  saved successfully** — confirming the resilience guarantee holds under
  a real fault, not just a hypothetical one. Table renamed back and the
  test apiary cleaned up afterwards.
- All test data cleaned up; `hivelog_calendar_action` and `hivelog_apiary`
  back to their pre-test state; `drush cr` clean.

## Implementation notes
- Key files changed: `src/Entity/CalendarAction.php` (constant + static
  helper), `src/Entity/Apiary.php` (`postSave()` override).
- The starter week numbers assume Northern Hemisphere / generic temperate
  climate — documented as a known limitation, not a bug, both in the
  constant's own docblock and in ADR-0025. Hemisphere-aware seeding using
  the apiary's `geofield` latitude remains explicitly out of scope.
- Deliberately did **not** add an "is default"/"seeded" flag to
  `CalendarAction` — the schema from
  [[0017-calendar-action-entity-and-schema]] is untouched; seeded rows are
  indistinguishable from manually-created ones once saved.
- The 9 entries originally drafted in ADR-0025 were kept verbatim
  (title/description/weeks unchanged) and 22 new entries were added around
  them, rather than restructuring what was already reviewed and accepted.

## Related
- Project:: [[seasonal-calendar-and-hive-action-tracking]]
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]]
- Commits::
