---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: ui
created: 2026-11-14
branch: feature/0283-calendar-weeks-shown-as-dates
release:
depends-on:
blocked-by:
---
# Task: Show calendar weeks as dates

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

The seasonal calendar is scheduled in ISO week numbers (`week_start`, `week_end`) and shown as
"W14–W18". Few beekeepers know which week it is; they think "early April to early May". Week
numbers are a good storage choice and a poor label.

## Acceptance criteria
- [x] Wherever a window is shown (calendar tables, the Window tile, alerts, the full calendar) the dates for the viewed year accompany the week numbers, e.g. "30 Mar – 3 May (W14–W18)". The hive and apiary checklists use the year in view; the full calendar, the Calendar Actions collection page and the Window tile use this year. The collection page was missed by #221 and is done in the follow-up
- [x] The calendar action form shows the resulting dates beside the two week inputs, without JavaScript being required: a description on the Schedule tab, for the saved weeks
- [x] Date formatting follows the interface language; storage and the API are unchanged. Month names reuse core's abbreviated-month strings; the order and punctuation of a range are fixed
- [x] Unit test for the week-to-date helper, including a window that wraps the year end (`HivelogWeekDatesTest`)
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`): see the note on CI below

## Implementation notes
- Key files: `src/HivelogAlertCollector.php` (`weekWindow()`), `src/HivelogCalendarChecklistBuilder.php`, `src/Controller/CalendarActionController.php`, `src/Form/CalendarActionForm.php`
- Update hook needed? No.

- Merged in #221 (2026-11-16), which carried the helper, the checklists, the Window tile, the alert rows and the form description. The Calendar Actions collection page (`CalendarActionController::collection()`) still listed plain week numbers; the follow-up gives it the same label and the header "When", and `CalendarActionListBuilder` and `ListSortTest` follow.
- The Window tile shows the weeks in its sub-line only when the action recurs ("W14–W18 · every year"); a one-off action shows the dates alone.
- Not done: a week window is shown as one fixed pattern ("30 Mar – 3 May"). A site that wants "3/30" or another order would need the helper to read a date format.
- Main's CI has not been green since 2026-11-17 because of an unrelated failure (the `HiveReminder` class is declared in `src/Entity/` under the namespace `Drupal\hivelog`, and the CI installs a duplicated autoloader), so this task's tests have not been confirmed by CI.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
