---
type: project
tags: [hivelog/project]
status: done
target:
created: 2026-07-03
---
# Project: Seasonal calendar & per-hive action tracking

## Goal
Give beekeepers a recurring, week-number-based schedule of seasonal duties
(varroa treatment, spring buildup, swarm prevention, summer/spring honey
harvest, winter prep, and everything in between) that lives on the **apiary**
— shared by every hive in it — while each **hive** independently tracks
whether/when it actually had a given action done. See
[[0025-seasonal-calendar-and-hive-action-tracking]] (accepted) for the full
data model and architecture.

## Scope
- In scope:
  - `CalendarAction` entity (apiary-scoped): short `title`, full
    `description` (plain text, with `- ` bullet lines rendered as a proper
    list), optional `category`, ISO-8601 week-number window (`week_start` /
    `week_end`, validated 1–53 with `week_end >= week_start`), `recurring`
    flag, and an `enabled` boolean so an item can be authored ahead of time
    without appearing anywhere until switched on.
  - New apiaries are seeded with a default starter calendar (a fixed set of
    common seasonal actions) on creation, via `Apiary::postSave()` +
    `CalendarAction::seedDefaultsForApiary()`. Seeded rows are ordinary,
    fully editable/deletable `CalendarAction`s — no "is default" flag.
  - `HiveActionLog` entity (hive-scoped): links a hive to a calendar action
    for a given `year`; starts **unreported** (`status = pending`, or simply
    no row yet); the beekeeper reports it as `done` or `ignored`, optionally
    recording the `week_completed` and free-text notes.
  - Reporting `done` can optionally create a linked `HiveInspection` stub
    (`hive` + today's date + `action_taken` synthesised from the report),
    via an "Also create a hive inspection record" checkbox, then hands the
    beekeeper off to that inspection's edit form to flesh out further.
  - Scoped-add routes/controllers/list builders mirroring the existing
    `Hive`/`Queen`/`QueenObservation` patterns.
  - Apiary canonical page: "Seasonal Calendar" table (enabled items only,
    sorted by week).
  - Hive canonical page: per-hive checklist cross-referencing the apiary's
    enabled calendar actions against the hive's own logs for a selected
    year, **defaulting to unreported items only**.
  - A filter control on the hive checklist to switch between
    unreported / done / ignored / all, and to switch the year between
    previous/current/next (so, at the start of a new year, a beekeeper can
    preview all pending items for the coming year before anything has been
    reported).
  - One-click "Report Done" / "Report Ignored" actions from the checklist,
    implemented as safe links to the existing scoped add-form (pre-filling
    `status` via a query default) so the real state change stays a
    CSRF-protected form `POST`, per [[0018-csrf-and-safe-http-methods]].
  - Permissions + access control via `ApiaryAccessTrait` (two new branches in
    `resolveApiary()`).
  - Kernel test coverage: entity CRUD, apiary-scoped access parity, list
    builder rendering, default-filter behaviour, enabled/disabled visibility,
    year switching, description bullet rendering, starter-calendar seeding,
    ISO week validation, done→inspection linking.
- Out of scope (for now):
  - Automated reminders/notifications (email, digest, dashboard alerts) for
    upcoming or overdue actions — not needed at the moment; can be revisited
    as a future project if it comes up again.
  - A rule engine beyond a fixed annual week-number window (no
    weather/climate-driven auto-scheduling).
  - Per-hive calendar overrides — all hives in an apiary follow the same
    plan; only the completion record is per hive.
  - Hemisphere-aware starter calendars (shifting the default seed set for
    Southern Hemisphere apiaries using the apiary's `geofield` latitude) —
    the starter set is generic Northern-Hemisphere guidance a beekeeper can
    edit; automatic adjustment is a deferred enhancement.
  - Real rich text / WYSIWYG for `description` — bullet lines are a
    plain-text convention (`- `/`* ` prefix), not a text-format field.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
_(Set each task's `project:` to `[[seasonal-calendar-and-hive-action-tracking]]`.)_

Currently:
- [[0017-calendar-action-entity-and-schema]] — done (verified end-to-end
  against a real Drupal site; kernel test coverage still tracked under
  [[0024-calendar-test-coverage]])
- [[0018-hive-action-log-entity-and-schema]] — done (verified end-to-end,
  including the "no uniqueness constraint" invariant and the dynamic
  `year` default; kernel test coverage still tracked under
  [[0024-calendar-test-coverage]])
- [[0019-calendar-routing-controllers-and-access]] — done (routes,
  controllers, access control, and breadcrumbs all verified end-to-end,
  including apiary-membership-scoped access with a real non-admin user and
  the dual-parameter breadcrumb edge case on the log add-form; full
  existing unit + kernel suite re-run with no regressions)
- [[0020-apiary-and-hive-calendar-ui]] — done ("Seasonal Calendar" table on
  the apiary page and the base checklist on the hive page both verified
  end-to-end, including a real regression caught and fixed — 4 existing
  kernel tests needed the two new entity schemas added to their `setUp()`)
- [[0021-hive-calendar-filtering-and-report-actions]] — done (status/year
  filter form + "Report Done"/"Report Ignored" actions verified end-to-end,
  including the literal "preview next year's pending items" requirement
  with a real recurring action; another real regression caught and fixed —
  a typed property redeclaration that fatalled the hive page)
- [[0022-seed-default-calendar-on-apiary-creation]] — done (starter
  calendar expanded to 31 entries per explicit direction to be as
  exhaustive as practical; verified with a real fault-injection test
  confirming apiary creation survives a seeding failure, not just a
  try/catch read from source)
- [[0023-link-hive-action-log-to-inspection]] — done ("Also create a hive
  inspection record" checkbox + `inspection` field verified end-to-end,
  including the access-denial path and all four checkbox-visibility
  branches; kernel test coverage remains tracked under
  [[0024-calendar-test-coverage]])
- [[0024-calendar-test-coverage]] — done (all 5 planned new test files
  written, 2 existing test files extended, 178 kernel + 70 unit tests
  passing with zero errors/notices — up from a 124/54 baseline; found and
  fixed one genuine new bug of its own along the way)

**All tasks complete.** Every requirement from the original brief has
shipped and been verified end-to-end against a real Drupal site:
a global-per-apiary, week-number-based seasonal calendar; per-hive
tracking that defaults to unreported items; a year selector that makes
"preview next year's pending items" work for free; a 31-entry starter
calendar seeded on apiary creation; and an optional link from a "done"
report to a full hive inspection record. No target release has been
assigned yet — see the open question below and [[roadmap]] for
sequencing into an upcoming version.

## Open questions
- ~~Should the "Also create a hive inspection record" checkbox default to
  checked or unchecked?~~ Resolved in
  [[0023-link-hive-action-log-to-inspection]]: defaults **unchecked** — not
  every reported action warrants a full inspection, and it's only ever
  offered for `done` reports in the first place. Worth a real usability
  check with actual beekeepers once this ships, but not blocking.
- Should the starter calendar's exact week numbers/wording (now 31 entries,
  implemented in [[0022-seed-default-calendar-on-apiary-creation]]) be
  reviewed by someone with real seasonal beekeeping experience before
  release? All 31 pass entity validation and are internally consistent,
  but the specific weeks/guidance are still illustrative rather than
  locally verified — worth a sanity check before 1.0 of this feature
  ships, especially since the set is now large enough that a subtly wrong
  week for e.g. a treatment could matter more than it did for the original
  9-item sample.

## Related decisions
- [[0025-seasonal-calendar-and-hive-action-tracking]] (accepted — core
  architecture for this project)
- [[0003-code-defined-entity-schema]] (baseFieldDefinitions + update hooks)
- [[0004-custom-controllers-over-view-builders]] (canonical pages)
- [[0017-output-sanitisation-policy]] (escaping rules for the bulleted
  `description` rendering)
- [[0018-csrf-and-safe-http-methods]] (Report Done/Ignored must stay
  form-`POST`-backed, not a bare `GET` mutation)
- [[0019-authorisation-model]] / [[0020-access-parity-custom-routes]] /
  [[0021-field-level-access]] (permission and access-control parity)
