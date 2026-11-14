---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Wherever a window is shown (calendar tables, the Window tile, alerts, the full calendar) the dates for the viewed year accompany the week numbers, e.g. "30 Mar – 3 May (W14–W18)"
- [ ] The calendar action form shows the resulting dates beside the two week inputs, without JavaScript being required
- [ ] Date formatting follows the interface language; storage and the API are unchanged
- [ ] Unit test for the week-to-date helper, including a window that wraps the year end
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogAlertCollector.php` (`weekWindow()`), `src/HivelogCalendarChecklistBuilder.php`, `src/Controller/CalendarActionController.php`, `src/Form/CalendarActionForm.php`
- Update hook needed? No.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
