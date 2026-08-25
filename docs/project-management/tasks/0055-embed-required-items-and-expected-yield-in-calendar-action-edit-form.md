---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project:
area: routing
created: 2026-08-25
branch:
release:
depends-on:
blocked-by:
---
# Task: Embed Required Items/Expected Yield inline in the Calendar Action edit form

## Context
[[0054-point-to-required-items-and-expected-yield-from-edit-form]]
shipped the small interim fix — a "Products & Yields" tab on the
Calendar Action edit form that just links out to the view page where
Required Items/Expected Yield actually live. The user's original ask
was for the real version of this: those sections rendered inline,
under their own vertical tab, directly on the edit form — no navigating
away needed.

## Acceptance criteria
- [ ] Confirm real need first, per this project's usual bar — the
      pointer from 0054 may be enough in practice; only pick this up if
      it keeps coming up as friction.
- [ ] If confirmed: render the existing `buildRequirementsSection()` /
      `buildYieldSection()` tables (currently built by
      `CalendarActionController::view()`) inside the edit form's new
      vertical tab, with their own "Add Required Item"/"Add Expected
      Yield" links preserved.
- [ ] Decide how add/edit/delete on those embedded rows should behave
      from inside the edit form — full-page navigation to the existing
      `hivelog.calendar_action_item_requirement.add` /
      `hivelog.calendar_action_product_yield.add` routes (simplest,
      matches how every other hivelog list-plus-add pattern already
      works) vs. some in-form/AJAX interaction (bigger, unproven
      elsewhere in this module — don't introduce it just for this).
- [ ] Must not regress the *add* form: these are still separate
      entities keyed to an existing calendar action id, so whatever
      renders here on edit still can't render on add.

## Implementation notes
- Deliberately left unspecified until real demand exists, and until the
  add/edit/delete interaction question above is actually decided —
  matches this project's own precedent at
  [[0053-product-category-field]] for "recorded as a placeholder,
  confirm real need first."

## Related
- Project:: _(unassigned — candidate for a future "Calendar Action form
  UX" project, alongside
  [[0054-point-to-required-items-and-expected-yield-from-edit-form]])_
- Decisions:: [[0025-seasonal-calendar-and-hive-action-tracking]],
  [[0034-honey-wax-propolis-yield-and-potential-income]]
- Commits::
