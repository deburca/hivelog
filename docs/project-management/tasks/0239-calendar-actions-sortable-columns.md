---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-28
branch: ui/small-fixes
release: 3.1.2
depends-on: ["[[0237-ui-polish-under-beeswax]]", "[[0171-sortable-list-columns]]"]
blocked-by:
---
# Task: Calendar Actions page sorts like every other list

## Context
The Calendar Actions page is built by `CalendarActionController::collection()`, not a list builder, so it missed
task 0171's sortable headers: its table was the one list with plain, unclickable column headings.

## Acceptance criteria
- [x] Title, Apiary, Scope, Category, Week(s) and Enabled sort, with the same arrows and `aria-sort` as other lists; Operations does not
- [x] Only those keys are honoured from `sort` / `order`; an unknown or array value is the default (by week)
- [x] A chosen sort is followed by week, then id, so equal rows keep a stable order
- [x] The sort survives the week filter (hidden inputs) and the sort links keep the filter and drop `page`
- [x] Test: `ListSortTest::testCalendarActionsSortAndKeepFilters`

## Outcome (2026-10-28)
Same rules as `HivelogListBuilder` (the map, `currentCollectionSort()` and `buildCollectionColumnSorts()` in the
controller), duplicated rather than shared because the page is not a list builder; if a third controller-built list
appears, move them into `HivelogListPageTrait`.

Left open from the audit, both needing a decision: the header's "HiveLog" menu item against the strip's "Dashboard",
and the `Name (id)` text in autocomplete pickers.

## Related
- Follows:: [[0237-ui-polish-under-beeswax]]
