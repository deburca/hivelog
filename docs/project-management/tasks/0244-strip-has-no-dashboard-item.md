---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-29
branch: ui/nav-no-dashboard
release: 3.5.0
depends-on: ["[[0237-ui-polish-under-beeswax]]"]
blocked-by:
---
# Task: The nav strip has no Dashboard item

## Context
The header menu has a "HiveLog" item and the in-page strip had a "Dashboard" item, and both went to the dashboard. The
owner chose to keep the header's item and drop the strip's.

## Acceptance criteria
- [x] `HivelogAppNavBuilder::build()` no longer prepends a Dashboard entry; the strip starts at Apiaries (then Insights where a submodule gives it children)
- [x] The dashboard stays reachable from the header's "HiveLog" menu item and the breadcrumb's "HiveLog" crumb
- [x] No dangling `dashboard` group: removed from `GROUP_ORDER`
- [x] `HivelogAppNavBuilderTest` updated; the other nav, menu-link and submodule nav tests pass

## Outcome (2026-10-29)
- On the dashboard no strip item is marked active (it has no item of its own).
- The childless-item test now uses the `hivelog_app_nav_test` orphan item, since Dashboard was the only childless primary item.

## Related
- Follows:: [[0237-ui-polish-under-beeswax]]
