---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-07-03
supersedes:
---
# ADR-0025: Seasonal calendar & per-hive action tracking

## Status
accepted

## Context
Beekeepers follow a recurring annual cycle of duties — varroa treatment,
spring buildup, swarm prevention, summer honey harvest, winter preparation,
and everything in between. Today Hivelog has no concept of *planned* work at
all; `HiveInspection` and `QueenObservation` only record what already
happened. There is a real need for a forward-looking schedule of what
*should* happen and when, plus a way to mark off whether a given hive has
actually had that work done.

Several scoping questions have to be settled before any schema is written:

1. **Where does the schedule live?** A single beekeeper often runs several
   apiaries in different micro-climates (a rooftop apiary vs. a woodland
   apiary a few weeks behind it in bloom), so a single site-wide calendar
   would force one schedule on every apiary regardless of local conditions.
   Conversely, defining the schedule per *hive* would mean re-entering the
   same ~10–15 seasonal actions for every hive in an apiary, and hives in the
   same apiary experience essentially the same climate — there is no
   meaningful reason for two hives standing next to each other to run
   different treatment windows. The user's own framing — "the calendar
   should be global to the apiary, but each hive should have its own
   tracking" — matches this: the *plan* is shared per apiary, the
   *execution record* is per hive. This ADR adopts that scoping explicitly
   so it is reviewable rather than assumed.
2. **How precise does the schedule need to be?** Beekeeping timing is
   climate- and colony-driven, not calendar-driven — "the third week of
   April" is meaningful, "17 April" is not. Entries are recorded as **ISO
   week numbers**, not specific dates, with an optional start/end window
   (e.g. weeks 16–18 for "spring inspection begins"), validated against
   real ISO-8601 week numbering rather than a naive fixed range.
3. **How does a beekeeper hide an item without losing it?** Calendar items
   need to be authored ahead of when they're relevant (e.g. build the whole
   year's plan in January) and some may be retired or paused without
   deleting the history that already references them. The user asked for a
   plain enabled/disabled toggle rather than a status vocabulary.
4. **How does "reporting" work per hive?** Per hive, per year, an action
   starts out unreported. The beekeeper later reports it as **done** or
   **ignored** (e.g. "this hive didn't need treatment this round"). The
   default view for a hive must show only unreported items — that's the
   point of the checklist — with the ability to look ahead: at the start of
   a new year, before anything has been reported, viewing "next year" should
   surface the full set of pending items as a forward plan.
5. **Does a new apiary start with an empty calendar?** Requiring every
   beekeeper to type out ~10 seasonal actions from scratch before the
   calendar is useful is poor onboarding. The user asked for new apiaries to
   be seeded with a default starter calendar.
6. **Should "done" connect back to the existing inspection record?**
   `HiveInspection` already has an `action_taken` field for "what was done
   during this visit" — the user asked whether reporting a calendar action
   as done could optionally create one of these, with the report as its
   comment, bridging the "planned" world (this ADR) and the "already
   happened" world (`HiveInspection`).

This follows the same parent → child pattern already established by
`Hive → HiveInspection` and `Queen → QueenObservation`
([[0003-code-defined-entity-schema]], [[0004-custom-controllers-over-view-builders]]),
must plug into the existing apiary-scoped access model
([[0019-authorisation-model]], [[0020-access-parity-custom-routes]],
[[0021-field-level-access]]) via `ApiaryAccessTrait::resolveApiary()`, and
must keep any one-click "report" actions compliant with
[[0018-csrf-and-safe-http-methods]] (no state change from a bare `GET` link).

## Decision (recommended)
Introduce **two** new content entities rather than one, to keep the "plan"
and the "log" independently editable and independently permissioned:

### 1. `CalendarAction` (apiary-scoped — the plan)
The seasonal schedule itself. One `CalendarAction` row is one recurring duty
for one apiary.

- `apiary` (`entity_reference` → `apiary`, required) — ties the entry to a
  single apiary; there is no site-wide/global row.
- `title` (`string`, required) — the short name, e.g. "Harvest Spring Honey"
  or "Varroa treatment (thymol)". Shown everywhere: apiary calendar table,
  hive checklist rows, list builder.
- `description` (`string_long`, required) — the full description of the
  activity. Plain text, but lines beginning with `- ` (or `* `) render as an
  actual bulleted `<ul><li>` list on the apiary/hive view pages instead of
  a flat paragraph, so a beekeeper can write:
  ```
  Apply thymol treatment per manufacturer instructions.
  - Check for existing supers and remove before treating.
  - Leave in place for the full course.
  - Recheck mite drop after 2 weeks.
  ```
  and get a properly formatted list. This is a small, deliberate extension
  of the existing `nl2br(Html::escape(...))` rendering pattern already used
  by `HiveInspectionController`, `QueenController` and
  `QueenObservationController` for other `string_long` fields — text is
  still escaped with `Html::escape()` before any markup is added, per the
  sanitisation contract in [[0017-output-sanitisation-policy]]. This
  intentionally avoids introducing a text-format/WYSIWYG dependency the
  module doesn't otherwise have.
- `category` (`list_string`, optional) — `varroa_treatment`, `feeding`,
  `spring_buildup`, `swarm_prevention`, `harvest_spring`, `harvest_summer`,
  `winter_prep`, `requeening`, `other`. Hard-coded allowed values in
  `baseFieldDefinitions()`, per [[0003-code-defined-entity-schema]]. Not
  required by the user's brief directly, but kept as a lightweight
  classification/filter aid alongside the free-text title.
- `week_start` (`integer`, required) — ISO-8601 week the action is due.
- `week_end` (`integer`, optional) — end of window if the action spans
  multiple weeks; treated as equal to `week_start` when empty.
- `recurring` (`boolean`, default `TRUE`) — recurs at the same week(s) every
  year. `FALSE` is reserved for a future one-off/ad-hoc entry (e.g. "requeen
  hive 4 this year only"); the field is added now so the data model doesn't
  need a schema change later.
- `enabled` (`boolean`, default `TRUE`) — a plain toggle per the user's
  request: "allowing an item to be created, but not appear."
  `enabled = FALSE` hides the item from the apiary's "Seasonal Calendar"
  table and from every hive's checklist, but it still appears (visibly
  marked disabled) in the entity's own admin list builder
  (`entity.calendar_action.collection`), so a beekeeper can find and
  re-enable it later. Any `HiveActionLog` rows already referencing a
  disabled action are left untouched — disabling only affects forward
  visibility, not history.
- Standard `uid` / `created` / `changed` fields, per existing entities.

**`week_start`/`week_end` validation.** Rather than a loose numeric range,
both fields carry an explicit `Range` constraint of **1–53** (ISO-8601's
upper bound — some years have a 53rd week; a fixed 1–52 range would wrongly
reject those), added via `->addConstraint('Range', ['min' => 1, 'max' =>
53])` in `baseFieldDefinitions()`, plus a same-entity check that `week_end`
(when set) is `>= week_start`. `CalendarAction` itself is year-agnostic
(it's the recurring plan, not one year's instance), so whether week 53
*actually exists* in any specific year is intentionally **not** validated
here — that would require knowing which year is being checked against,
which this entity doesn't carry. In a year without a 53rd ISO week, a
`CalendarAction` pinned to week 53 simply has no exact match; treat this as
an accepted edge case for beekeepers who use week 53 (rename/move it to 52
or 1 if it becomes a nuisance) rather than a validation error.

### Default starter calendar on apiary creation
To avoid a new apiary starting from a blank, unhelpful calendar, `Apiary`
gains a `postSave()` override that — only on **insert** (`!$update`) —
calls a new `CalendarAction::seedDefaultsForApiary(Apiary $apiary)` helper,
which creates one `CalendarAction` row per entry in a
`CalendarAction::DEFAULT_STARTER_CALENDAR` constant (the same
"constant lookup table" pattern already used for
`Queen::QUEEN_COLOUR_MAP`). Every seeded row is a perfectly normal, fully
editable `CalendarAction` (`enabled = TRUE`, `recurring = TRUE`) — no
"is default" flag is introduced, so a beekeeper edits, disables, or deletes
any of them exactly like one they authored themselves.

The starter set is deliberately **as exhaustive as practical** rather than
a light illustrative sample — per explicit direction, it is trivial for a
beekeeper to disable/delete an entry that doesn't apply, but far more
effort to think of and add every seasonal duty themselves from a blank
calendar. As implemented in
[[0022-seed-default-calendar-on-apiary-creation]], the constant holds
**31 entries** covering the full annual cycle at roughly
biweekly-to-monthly granularity, grouped here by category (exact
titles/weeks live in `CalendarAction::DEFAULT_STARTER_CALENDAR` — the code
is the source of truth, not this table, to avoid two copies drifting out
of sync):

| Category | Entries |
|---|---|
| `varroa_treatment` | 4 — spring, late-summer, a late-summer follow-up check, and a midwinter broodless-period treatment |
| `spring_buildup` | 3 — spring inspection/clean-up, equalising/reversing brood boxes, adding supers ahead of the flow |
| `swarm_prevention` | 3 — weekly swarm checks, bait hives/traps, splits/making increase |
| `requeening` | 3 — queen rearing, a mid-season introduction window, late-summer requeening of failing colonies |
| `harvest_spring` / `harvest_summer` | 3 — spring harvest, summer harvest, a mid-season stores/supering check |
| `feeding` | 3 — fondant/emergency winter feed check, spring stimulative feeding, autumn feeding |
| `winter_prep` | 5 — midwinter cluster check, mouse guards/woodpecker protection, winter preparation, final stores check, ventilation/moisture check |
| `other` | 7 — CBR registration renewal, equipment prep, apiary hygiene, assessing winter losses, a disease-focused mid-season check, combining weak colonies, end-of-season record-keeping |

The CBR (Central Beehive Registration) entry is specific to this module's
Irish beekeeping context — it already carries a dedicated `cbr_number`
field (see `hivelog_entity_base_field_info()`), so a renewal reminder in
the starter calendar is a natural, well-integrated addition rather than a
generic one.

These week numbers assume the Northern Hemisphere and a generic temperate
climate; a beekeeper anywhere else edits or disables them like any other
row. **Hemisphere-aware seeding** (using the apiary's existing `geofield`
latitude sign to shift the whole set by ~26 weeks for the Southern
Hemisphere) is a natural follow-up but explicitly out of scope for the
initial seeding task — it adds real complexity (parsing the geofield WKT
point) for a problem a beekeeper can otherwise solve in a few minutes of
editing. Seeding failures must not block apiary creation itself: catch and
log rather than letting an exception from `postSave()` fail the apiary
save.

### 2. `HiveActionLog` (hive-scoped — the record)
The per-hive execution record against a `CalendarAction`, for a given year.
Absence of a row (or a row with `status = pending`) means "unreported."

- `hive` (`entity_reference` → `hive`, required).
- `calendar_action` (`entity_reference` → `calendar_action`, required) —
  which plan entry this fulfils. The parent apiary is resolved transitively
  (`hive_action_log` → `hive` → `apiary`), matching the existing
  `ApiaryAccessTrait::resolveApiary()` chain — no separate `apiary` field is
  stored on the log.
- `year` (`integer`, required, defaults to the current year) — disambiguates
  which annual occurrence of a recurring `CalendarAction` this log is for.
- `week_completed` (`integer`, optional, 1–53, same `Range` constraint as
  `CalendarAction`) — the week the action was actually carried out; may
  differ from `calendar_action.week_start`. Relevant for `done`, generally
  left empty for `ignored`.
- `status` (`list_string`: `pending` / `done` / `ignored`, default
  `pending`) — `pending` means "not yet reported"; `done` and `ignored` are
  the two reporting outcomes the user asked for ("report as done, or report
  as ignored — once reported").
- `notes` (`string_long`) — free text (product used, dose, why an item was
  ignored, observations). Doubles as the seed comment when linking to an
  inspection (see below).
- `inspection` (`entity_reference` → `hive_inspection`, optional) — set only
  when the beekeeper chose to create a linked inspection record while
  reporting `done` (see next section). `NULL` for logs with no linked
  inspection, which will be the common case.
- Standard `uid` / `created` / `changed` fields.

No uniqueness constraint is enforced between `(hive, calendar_action, year)`:
some actions legitimately happen more than once a year (e.g. two varroa
treatments against two separate `CalendarAction` rows, or a repeat visit
against the same one), so multiple logs per hive/action/year are allowed
by design rather than treated as a data integrity violation.

### Reporting "done" can create a linked hive inspection
Answering the user's question directly: **yes** — reporting an action as
`done` can optionally create a `HiveInspection` record, using the report as
its comment. Concretely:

- The "Report Done" flow (`HiveActionLogForm` when `status = done`) exposes
  an extra, non-persisted checkbox: "Also create a hive inspection record
  for this." It only appears for users who also hold `add hive inspection`
  (or `administer hivelog`) — silently omitted otherwise, it's a convenience,
  not a required step.
- On submit, if checked: save the `HiveActionLog` first, then create a
  `HiveInspection` with `hive` = the same hive, `inspection_date` = today,
  and `action_taken` synthesised from the calendar action and the log, e.g.
  `"{$calendar_action->title}: {$log->notes}"`. Save it, set
  `HiveActionLog.inspection` to its ID, save the log again, and redirect the
  beekeeper to the **new inspection's edit form** (not back to the hive
  page) so they can immediately fill in the rest of the inspection detail
  (weight, brood pattern, queen seen, varroa count, etc.) while it's fresh —
  `HiveInspection` only strictly requires `hive` + `inspection_date`, so the
  stub is valid as-is even if they don't add more.
- The hive checklist shows a "View Inspection" link next to any `done` row
  that has a linked inspection.
- This is additive and non-breaking: `HiveActionLog` rows created before
  this capability existed, or where the checkbox was left unticked, simply
  have `inspection = NULL`.

**Does this change the checklist's year-selector span?** No — kept as
originally proposed: default to the current year, with the previous and
next year selectable, open to widening later if beekeepers ask for more
history. The linked inspection is reachable at any time through the hive's
existing Inspections table (already unfiltered by year, newest-first,
paginated) regardless of which year the *calendar* checklist is currently
showing — so there's no information loss that would force a wider year
range now. If cross-referencing "which inspections came from a reported
calendar action" turns out to matter across many years in practice, that's
a reason to revisit later, not a reason to pre-emptively widen the selector
today.

### Computing a hive's checklist (no pre-materialised rows)
A hive's checklist for a given year is computed on read, not stored: for
every `enabled` `CalendarAction` belonging to the hive's apiary, look for a
`HiveActionLog` matching `(hive, calendar_action, year)`. If none exists (or
it exists with `status = pending`), the row is **unreported**; otherwise it
shows the log's `status`, `week_completed`, and any linked inspection. This
is why "viewing all pending items for the coming year" needs no
special-casing: switching the checklist's `year` to next year, before any
logs exist for it, naturally shows every enabled recurring action as
pending — a full forward plan.

### Filtering on the hive checklist
- Default view: `status = pending` only (i.e. unreported), for the current
  year — this is the primary "what do I still need to do" view.
- A filter control (GET-submitted form, following the existing
  `HivelogInspectionFilterForm` pattern) lets the beekeeper switch `status`
  to `done`, `ignored`, or "all", and switch `year` between a small range
  (previous year through next year) to review history or preview an
  upcoming year.
- Reporting itself ("Report Done" / "Report Ignored") is a link to the
  existing `hivelog.hive_action_log.add` scoped-add route with a query
  default that pre-fills the form's `status` field (e.g. `?status=done`).
  The link is a safe, side-effect-free `GET` navigation to a form; the
  actual state change happens through that form's normal `POST` submission,
  which carries Drupal core's CSRF protection automatically — satisfying
  [[0018-csrf-and-safe-http-methods]] without introducing a new one-click
  mutation endpoint.

### Current-week visibility (added post-launch, [[0026-post-testing-refinements]])
Real hands-on testing surfaced a gap the automated test suite didn't
catch: the tables show each item's planned week(s), but nowhere was the
*current* week shown, so it wasn't obvious whether an item was due,
overdue, or still upcoming without doing the comparison by hand. Fixed by:
- Both the apiary and hive pages show "Seasonal Calendar (current week:
  @week)" in the section heading (`(int) date('W')`).
- The apiary's calendar table gains a `Timing` column
  (`Upcoming`/`Due now`/`Past`), shown for every row — the apiary table
  has no per-hive reporting status, so timing is the only relevant signal.
- The hive checklist merges the equivalent signal into the existing
  `Status` column for still-`pending` rows only (`Unreported (Due now)` /
  `Unreported (Overdue)` / `Unreported (Upcoming)`) — "Overdue" rather
  than "Past", since it only ever applies to something still actionable.
  `done`/`ignored` rows are untouched.
- The hive checklist's timing suffix only appears when the selected year
  filter equals the real current year — previewing a future year would
  otherwise trivially label every row "Upcoming".
- Both controllers cap their render's cache `max-age` to the number of
  seconds until the next ISO week boundary, since the timing computation
  depends on "now" and must not be served stale across a week change.

### Routing, controllers, access
Follow the existing scoped-add pattern exactly:
- `hivelog.calendar_action.add` → `/hivelog/apiary/{apiary}/calendar-action/add`
- `hivelog.hive_action_log.add` → `/hivelog/hive/{hive}/calendar-action/{calendar_action}/log/add`
- Canonical pages via custom controllers (`CalendarActionController`,
  `HiveActionLogController`), per [[0004-custom-controllers-over-view-builders]].
- `ApiaryController::view()` gains a "Seasonal Calendar" table (entity-table
  SDC, sorted by `week_start`, `enabled` items only) with an "Add Calendar
  Action" button.
- `HiveController::view()` gains the per-hive checklist described above,
  with its filter form and "Report Done" / "Report Ignored" actions.
- Access control handlers reuse `ApiaryAccessTrait`; `resolveApiary()` gains
  two new branches (`calendar_action` → `apiary` directly,
  `hive_action_log` → `hive` → `apiary`).
- New permissions follow the established `view own/any`, `add`,
  `edit own/any`, `delete own/any` × 2 entities pattern
  ([[0019-authorisation-model]]).
- Both entities need `hivelog_update_10014` / `_10015` install hooks
  ([[0003-code-defined-entity-schema]]) — no migration of existing data,
  purely new tables. The `inspection` field on `HiveActionLog` needs its own
  update hook (`hivelog_update_10016`) if it lands after `_10015` has
  already shipped; if both land in the same release, it can simply be
  folded into `_10015` instead — a sequencing detail for whoever picks up
  the corresponding tasks.

## Consequences
- Positive: reuses every established pattern (scoped-add routes, custom
  controllers, `ApiaryAccessTrait`, code-defined schema, entity-table SDC,
  GET-based filter forms, safe-link-to-form reporting, constant-lookup-table
  seeding à la `QUEEN_COLOUR_MAP`) — no new architectural surface area, just
  two more entities in the existing hierarchy. Week-number granularity
  avoids the false precision of exact dates while still supporting
  year-over-year history via `year` + `recurring`. Computing the checklist
  on read (rather than pre-creating a log row per hive/action/year) means
  enabling/disabling an action or adding a new one takes effect immediately
  for every hive with no backfill job required, and "view next year's
  pending items" falls out for free. Seeding a starter calendar gives new
  apiaries a usable, fully-editable baseline instead of an empty page.
  Optionally linking a `done` report to a real `HiveInspection` connects the
  "planned" and "already happened" halves of the module without forcing
  every report through the full inspection form.
- Negative / trade-offs: two entities (rather than one) means two list
  builders, two access handlers, two sets of permissions, and a slightly
  more complex hive-page render (cross-referencing calendar actions against
  logs in the controller rather than a single table/query). No
  reminder/notification mechanism is included and none is planned at the
  moment — a beekeeper must visit the hive page to see what's due. The
  bulleted-list rendering of `description` is a plain-text convention
  (lines starting with `- `/`* `), not real rich text. The starter calendar
  is Northern-Hemisphere-flavoured and generic; Southern-Hemisphere or
  unusually-climated apiaries will want to edit it (hemisphere-aware
  seeding is a deferred follow-up, not solved here). `CalendarAction`'s
  week validation can't check whether week 53 exists in any particular
  year, since the entity is year-agnostic by design.
- Follow-up tasks: tracked under the
  [[seasonal-calendar-and-hive-action-tracking]] project.
