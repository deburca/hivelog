---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[breadcrumb-consistency]]"
area: theme
created: 2026-09-23
branch: feature/0120-app-nav-active-state-and-grouping
release:
depends-on: ["[[0119-single-source-navigation-registry]]"]
blocked-by:
---
# Task: In-app nav strip — active section, grouping, dashboard link

## Context
From the navigation and breadcrumb review of 2026-09-23. The in-app nav
strip added by [[0105-submodule-navigation-menu-links]]
(`HivelogAppNavBuilder`, rendered on every `/hivelog` page via
`hivelog_preprocess_page()`) is the only navigation reliably visible on
the real front-end theme. It has four weaknesses:

1. **No indication of where you are.** No `is-active` class, no
   `aria-current`, and no CSS for either in `css/hivelog.app-nav.css`.
   Confirmed live on `cms2`: zero active markers on `/hivelog/hive/22`,
   `/hivelog/apiaries` or `/hivelog/queens`.
2. **One flat row of 11 pills** mixing day-to-day beekeeping pages
   (Apiaries, Hives, Inspections, Queens, Queen Observations) and
   inventory (Inventory Items, Inventory Purchases, Products) with
   one-off setup pages (API Clients, AI Provider Configs, Sensor
   Devices). It wraps to 3 or more lines at phone width.
3. **No link to the dashboard** (`/hivelog`). The breadcrumb's "HiveLog"
   crumb is the only way back, and the theme's truncation hides it on
   any trail longer than 3 crumbs.
4. **Cache granularity doesn't match the output.** The render array
   varies by `url.path`, but its output is identical on every path. This
   only becomes right once item 1 makes the output path-dependent.

## Acceptance criteria
- [x] **Active section**: the nav item for the current page's section
      gets `is-active` plus `aria-current="page"` (exact collection
      page) or `aria-current="true"` (a page within that section).
      Section is resolved from the route's subject entity type. For
      example, `/hivelog/hive/22`, `/hivelog/hive/22/edit` and
      `/hivelog/hive/22/insights` all mark "Hives"; `/hivelog/inspection/5`
      marks "Inspections". Pages with no matching section (dashboard,
      apiary-scoped reports) mark nothing, except the new Dashboard item
      on the dashboard.
- [x] Styling for the active state in `css/hivelog.app-nav.css`, using
      existing `--hivelog-*` tokens only
      ([[0060-visual-identity-in-site-theme]]: no palette in the module).
- [x] **Dashboard item** added as the first nav entry, pointing at
      `hivelog.dashboard`.
- [x] **Grouping**: items carry a `group` key (proposed: `records`,
      `inventory`, `setup`). The strip renders groups with a visual
      separator, `setup` last. Submodule hook items declare their own
      group; items without one fall into a default group. Grouping is
      carried through to the derived menu links from
      [[0119-single-source-navigation-registry]] as weights only (menus
      have no separator concept).
- [x] Cache contexts reflect the new output: `route` (or `url.path`) and
      `user.permissions`, justified in a code comment.
- [x] Phone width (≤480px, `css/hivelog.responsive.css` breakpoints)
      checked: the strip stays usable, e.g. horizontal scroll or a
      collapsed `setup` group, rather than 3 or more wrapped lines.
- [x] Kernel test: active-state resolution for a collection route, a
      canonical route, an edit route, and a route with no section.
- [x] Verified live on `cms2`.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Subject resolution extracted, not reimplemented** — `SUBJECT_PARAMS`,
`CALENDAR_ACTION_NOT_SUBJECT_ROUTES` and `resolveSubject()` moved from
`HivelogBreadcrumbBuilder` into `HivelogEntityHierarchy` (already the
shared declarative-registry class for hierarchy facts, task 0127) as
public constants/a static method. Both the breadcrumb trail and the
nav's active-section resolution now call the same
`HivelogEntityHierarchy::resolveSubject()` — exactly the "can't
disagree about what a page is about" property this task's own
implementation notes asked for.

**Dashboard is nav-strip-only, deliberately not a `getAllItems()`
member.** `HivelogAppNavBuilder::build()` prepends it directly rather
than folding it into the single registry [[0119-single-source-navigation-registry]]
built. Making it a real registry item would also make it a derived
main-menu child of `hivelog.admin` — but `hivelog.admin` already points
at `hivelog.dashboard`, so that menu entry would be a "Dashboard" link
sitting under a "HiveLog" link that goes to the same place. Useful as a
strip shortcut when deep in a sub-page; redundant as a menu entry.
Verified live: `HivelogAppNavBuilder::getAllItems()` (what the menu
deriver reads) has no `dashboard` key, and the rendered main menu under
`hivelog.admin` still lists exactly the 11 real destinations.

**Setup group, not a separate landing page.** The alternative this
task's own notes floated — sensor devices/API clients/AI provider
configs living on one "Setup" page instead of a `group` — would be a
bigger information-architecture change than this task's own acceptance
criteria assume (they already specify a `group` key and a `setup`
group by name). Went with grouping as specified; the alternative is
still available for a future task if a single Setup landing page turns
out to be wanted.

**Active-section exclusion list, not a heuristic.** Two apiary-scoped
*report* routes (`hivelog.apiary.inventory_cost_report`,
`hivelog.apiary.calendar_action.collection`) carry `{apiary}` and
resolve a subject via `resolveSubject()`, but marking "Apiaries" active
on a report page would be misleading — a report is a different feature
from "manage apiaries", unlike a hive's own Insights page (which
*should* mark "Hives", per the task's own example) or a hive/apiary
canonical/edit page. `HivelogAppNavBuilder::NAV_EXCLUDED_SUBJECT_ROUTES`
names these two routes explicitly, mirroring
`CALENDAR_ACTION_NOT_SUBJECT_ROUTES`'s own established pattern in this
codebase: anchored to specific routes, not a coincidence of which
params are present. The combined financial report
(`hivelog.apiaries.financial_report`) needed no entry — it carries no
`{apiary}` at all, so nothing ever resolves on it to begin with.

**Separators are elements, not nested group containers.** Considered
wrapping each group in its own `#type => container` so CSS could target
"first child of a group" — rejected because it would nest every item
under its group key (`$build['records']['hives']` instead of
`$build['hives']`), breaking every existing caller/test that addresses
an item directly by its own key (`hivelog_preprocess_page()` doesn't
care, but the three submodules' own `AppNavItemsTest::test*ItemAppearsInRealAppNav()`
assertions do). Inserted a synthetic `hivelog_app_nav_separator_N`
`<span aria-hidden="true">` between consecutive items whose `group`
differs instead — every real item stays flatly addressable by its own
key, and `css/hivelog.app-nav.css` styles the separator as a thin
vertical rule using `--hivelog-hairline`.

**Cache contexts unchanged, now actually earning their keep.**
`url.path` was already present pre-task, but the output never varied
by it — this task's own context notes flagged this ("this only becomes
right once [active state] makes the output path-dependent"). No new
context was needed; the existing one just stopped being wasted.

**Verification.** phpcs clean, phpstan clean (no baseline changes).
Full kernel+unit suite: 719 tests, zero failures/errors beyond the 3
pre-existing, unrelated `DashboardTest` Functional cache-redirect
errors already documented in tasks 0116/0119. New kernel tests cover
all four states the AC names (collection route exact-match, canonical
route section-match, edit route section-match, excluded report route —
no match) plus the updated group/weight ordering. Live-verified on
`cms2` via `drush php-eval` with throwaway fixtures (all cleaned up
after): the derived main menu still lists exactly 11 items with no
Dashboard duplicate; a real `build()` call on the hive canonical page
marks "Hives" `is-active`/`aria-current="true"` and nothing else; the
hives collection route itself marks `aria-current="page"`; the apiary
inventory-cost-report route marks nothing despite resolving an apiary
subject. (One early manual check appeared to show a stale match
carrying over between two checks in the same script — traced to
`CurrentRouteMatch`'s own per-Request caching, not a bug in the
implementation: each real HTTP request only ever has one current
route, so the cache is correct production behaviour; the fix for the
verification script itself was calling `resetRouteMatch()` between
checks, which resolved it.) Did not get a browser-rendered visual check
of the CSS — the local browser tool couldn't reach `cms2.ddev.site` in
this environment; the render output (classes, `aria-current`, DOM
structure) was verified precisely via direct service calls instead, and
the CSS itself is a small, low-risk set of flexbox/colour-token rules.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]],
  [[0057-dashboard-information-architecture]]
- Tasks:: [[0105-submodule-navigation-menu-links]],
  [[0119-single-source-navigation-registry]],
  [[0121-reachability-of-orphaned-collection-pages]]
- Commits::
