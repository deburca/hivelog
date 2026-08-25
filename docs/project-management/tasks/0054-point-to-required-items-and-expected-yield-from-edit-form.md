---
type: task
tags: [hivelog/task]
status: done
priority: high
project:
area: routing
created: 2026-08-25
branch: feature/0054-point-to-required-items-and-expected-yield-from-edit-form
release:
depends-on:
blocked-by:
---
# Task: Point to Required Items/Expected Yield from the Calendar Action edit form

## Context
A beekeeper using kbg (kragebaekgaard.dk) reported being unable to
"assign harvesting honey to a calendar item" (e.g. 25kg Spring Honey,
40kg Summer Honey) from `/hivelog/calendar-action/{id}/edit`. Required
Items and Expected Yield are separate entities
(`CalendarActionItemRequirement`, `CalendarActionProductYield`) managed
entirely from the calendar action's *canonical/view* page
(`CalendarActionController::buildRequirementsSection()` /
`buildYieldSection()`, via the "Add Required Item" / "Add Expected
Yield" buttons) — never on the edit form, since `CalendarActionForm`
only renders the calendar action's own base fields. Nothing on the edit
form hints that these sections exist elsewhere, so a user editing a
calendar action has no way to discover them.

Confirmed live on kbg: `HarvestYieldFormTrait::buildYieldFields()`
skips rendering the "Yield Produced" fieldset on the
`HiveActionLogForm`/`ApiaryActionLogForm` "done" report entirely when
no `CalendarActionProductYield` recipe row exists for the log's
calendar action — so without first finding and using "Add Expected
Yield" on the view page, there is nowhere in the UI to record an actual
harvest at all.

This task is the deliberately small interim fix: a pointer from the
edit form to the view page. The ideal fix — rendering Required
Items/Expected Yield inline under their own vertical tab on the edit
form itself — is deferred to
[[0055-embed-required-items-and-expected-yield-in-calendar-action-edit-form]]
since it needs a real design pass (those are separate entities keyed to
an existing calendar action id, so they can't be handled by the add
form the same way).

## Acceptance criteria
- [x] Editing an existing calendar action shows a new "Products &
      Yields" vertical tab, alongside Overview/Schedule/Description.
- [x] That tab explains that Required Items and Expected Yield are
      managed from the calendar action's own page, and links there
      (`entity.calendar_action.canonical`, via `hivelog:button`).
- [x] The new tab does **not** appear on the *add* form — the entity
      has no id yet, so there is nowhere to link to.
- [x] `phpcs` (Drupal + DrupalPractice) clean.
- [x] `phpstan` level 2: no new errors introduced (2 pre-existing
      `return.missing` errors on `save()` are unchanged, confirmed via
      `git stash` diff against the unmodified file).

## Implementation notes
- Key file: `src/Form/CalendarActionForm.php` — `form()` gains a
  fourth vertical-tab section (`calendar_action_yield`, weight 3),
  conditional on `!$this->entity->isNew()`, containing static
  explanatory text plus a `hivelog:button` component linking to
  `$this->entity->toUrl('canonical')`.
- No entity/schema change, no new route — purely a form-render addition
  in the existing `CalendarActionForm::form()` override, following the
  same `$sections` array + per-field `#group` assignment pattern
  already used for Overview/Schedule/Description.
- Not yet committed — applied directly to the working tree at the
  request that produced this task doc; no branch has actually been
  created yet under the `branch:` name above.

## Verification
- Verified against a real Drupal site (`cms2`'s local ddev, `kbg`
  sub-site), not just `php -l`: created a scratch apiary + calendar
  action via `drush php:eval`, confirmed the "Products & Yields" tab
  renders on `/hivelog/calendar-action/{id}/edit` with the expected
  text and a working link to `/hivelog/calendar-action/{id}`, and
  confirmed it is absent on `/hivelog/apiary/{id}/calendar-action/add`.
  Scratch entities deleted after verification.
- `phpcs --standard=Drupal,DrupalPractice --extensions=php,module,install
  src/Form/CalendarActionForm.php` — 0 errors, 0 warnings.
- `phpstan analyse -c phpstan.neon --memory-limit=512M
  src/Form/CalendarActionForm.php` — 2 errors, both pre-existing
  `return.missing` on `save()` (confirmed identical via `git stash`
  against the unmodified file, just at shifted line numbers).
- Root-cause fix (uploading the module's own code to production) is
  separate and out of scope here — this task only covers the module
  source change; deploying it to kragebaekgaard.dk still requires a
  `deburca/hivelog` release + `composer update` on the `cms2` site, per
  the module's own distribution model (see `AGENTS.md`).

## Related
- Project:: _(unassigned — candidate for a future "Calendar Action form
  UX" project, alongside
  [[0055-embed-required-items-and-expected-yield-in-calendar-action-edit-form]])_
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]],
  [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits:: _(pending — not yet committed)_
