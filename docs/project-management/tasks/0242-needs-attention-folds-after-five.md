---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-29
branch: ui/error-scenes-and-lists
release: 3.4.0
depends-on: ["[[0237-ui-polish-under-beeswax]]"]
blocked-by:
---
# Task: "Needs attention" folds after five rows

## Context
With several apiaries the "Needs attention" queue runs to dozens of rows (52 on the test copy of real data) and pushes
the rest of the dashboard out of sight.

## Acceptance criteria
- [x] The first five rows show; the rest sit behind a "Show N more" row inside the panel
- [x] The rows are already ordered overdue first, so what stays visible is what matters most; the header count still says how many there are in all
- [x] A native `<details>`, no script; open it and the row reads "Show fewer"
- [x] Five rows or fewer: nothing is folded
- [x] Kernel tests in `DashboardTest` for seven rows (5 + 2) and for five

## Outcome (2026-10-29)
- `DashboardController::ATTENTION_VISIBLE_ROWS`, the `hivelog-attention__more*` classes in `css/hivelog.dashboard.css`.
- The mobile API's `computed/alerts` is untouched: the app gets every row.
- Checked at 390px on hivelog.ddev.site with 52 rows: five, then "Show 47 more".

## Related
- Follows:: [[0237-ui-polish-under-beeswax]]
