---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] **Active section**: the nav item for the current page's section
      gets `is-active` plus `aria-current="page"` (exact collection
      page) or `aria-current="true"` (a page within that section).
      Section is resolved from the route's subject entity type. For
      example, `/hivelog/hive/22`, `/hivelog/hive/22/edit` and
      `/hivelog/hive/22/insights` all mark "Hives"; `/hivelog/inspection/5`
      marks "Inspections". Pages with no matching section (dashboard,
      apiary-scoped reports) mark nothing, except the new Dashboard item
      on the dashboard.
- [ ] Styling for the active state in `css/hivelog.app-nav.css`, using
      existing `--hivelog-*` tokens only
      ([[0060-visual-identity-in-site-theme]]: no palette in the module).
- [ ] **Dashboard item** added as the first nav entry, pointing at
      `hivelog.dashboard`.
- [ ] **Grouping**: items carry a `group` key (proposed: `records`,
      `inventory`, `setup`). The strip renders groups with a visual
      separator, `setup` last. Submodule hook items declare their own
      group; items without one fall into a default group. Grouping is
      carried through to the derived menu links from
      [[0119-single-source-navigation-registry]] as weights only (menus
      have no separator concept).
- [ ] Cache contexts reflect the new output: `route` (or `url.path`) and
      `user.permissions`, justified in a code comment.
- [ ] Phone width (≤480px, `css/hivelog.responsive.css` breakpoints)
      checked: the strip stays usable, e.g. horizontal scroll or a
      collapsed `setup` group, rather than 3 or more wrapped lines.
- [ ] Kernel test: active-state resolution for a collection route, a
      canonical route, an edit route, and a route with no section.
- [ ] Verified live on `cms2`.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Reuse the subject-entity resolution from
  [[0116-breadcrumb-builder-parent-map-refactor]] rather than a second
  route → section mapping. If it's extracted to a small shared helper
  or service, the nav and breadcrumb can't disagree about what a page
  is "about".
- Whether sensor devices, API clients and AI provider configs belong in
  the strip at all, or on a single "Setup" landing page, is a
  reasonable alternative to a `setup` group. Decide at implementation
  time and record the choice here.
- Key files: `src/HivelogAppNavBuilder.php`, `css/hivelog.app-nav.css`,
  `hivelog.api.php` (hook item shape gains `group`), the three
  submodule `.module` files.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]],
  [[0057-dashboard-information-architecture]]
- Tasks:: [[0105-submodule-navigation-menu-links]],
  [[0119-single-source-navigation-registry]],
  [[0121-reachability-of-orphaned-collection-pages]]
- Commits::
