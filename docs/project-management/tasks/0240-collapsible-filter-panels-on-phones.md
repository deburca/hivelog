---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-28
branch: ui/phone-filters
release: 3.3.0
depends-on: ["[[0237-ui-polish-under-beeswax]]", "[[0229-app-nav-menu-button]]"]
blocked-by:
---
# Task: Filter panels collapse on a phone

## Context
On a phone a collection's filter form is a column of four or five fields taller than the screen, so the table it filters
starts below the fold. The audit also named the breadcrumb: it was already cut to "Home … parent › page" by beeswax, but a
long record title wrapped and pushed the last crumb to the far edge (beeswax, same day).

## Acceptance criteria
- [x] Every `hivelog-filter-form` sits in a panel whose "Filters" bar opens and closes it, closed by default up to 768px
- [x] From 769px up the bar is hidden and the form shows as before
- [x] The bar says how many filters are set ("1 active"), counted from the form's own defaults, never from the buttons or the hidden sort state
- [x] CSS only: a hidden checkbox and its label, the app nav's Menu pattern (task 0229); no script, no flash of an open panel
- [x] Added in `hivelog_form_alter()` by `HivelogFilterPanel::wrap()`, so none of the twelve filter forms repeats it
- [x] Kernel test `FilterPanelTest`; the existing filter and list tests still pass

## Outcome (2026-10-28)
- `HivelogFilterPanel::wrap()` and `activeCount()`; the prefix and suffix are `Markup` objects because a plain `#prefix` goes
  through the admin XSS filter, which drops `<input>` and `<label>`.
- Closed even with filters set: the badge says why the list is short, and the panel opens with a tap. Reset sits inside it.
- Checked on hivelog.ddev.site at 390px (closed, open, with and without a filter) and at 1280px (bar hidden). Real iOS
  Safari not checked.
- beeswax: the breadcrumb keeps one line, the parent link shrinking to an ellipsis first and the page you are on after it.

## Related
- Follows:: [[0237-ui-polish-under-beeswax]]
