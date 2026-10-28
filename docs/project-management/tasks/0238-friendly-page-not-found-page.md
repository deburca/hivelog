---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-28
branch:
release: 3.2.0, 3.2.1
depends-on: ["[[0237-ui-polish-under-beeswax]]"]
blocked-by:
---
# Task: Friendly page-not-found and access-denied pages

## Context
A missing page showed the theme's plain 404. HiveLog gets its own: a confused bee beside an empty hive with a
"Vacant" sign, the headline "Oops! This hive is empty." and "These bees may have fled. Head back to the HiveLog
home page and pick up their trail from there." The copy and the picture were chosen in a design conversation on
2026-10-28 Access-denied, same day: "Hold on, this hive isn't yours." and "Only authorised bees are allowed in.", two bees flanking a "Residents only" sign, with a sign-in line for signed-out visitors. (404 alternatives considered: a swarm leaving the hive, "Out foraging" and "For sale" signs).

## Acceptance criteria
- [x] `hivelog.page_not_found` renders the page for anyone, anonymous included
- [x] The scene is an inline SVG, hidden from screen readers; the headline is the page's H1
- [x] The link goes to the site's front page
- [x] No site setting is changed by the module; `system.site:page.404` names the route
- [x] Kernel test; phpcs (CI's coder) and phpstan clean
- [x] Access-denied page (a second scene in the same component), with a sign-in link for anonymous visitors only
- [ ] `system.site:page.404` and `page.403` set on the live hivelog.eu

## Outcome (2026-10-28)
- Component `components/error-page/`, `ErrorPageController`, `tests/src/Kernel/ErrorPageTest.php`.
- `RouteEntityAccessTest` exempts both routes (`EXEMPT_ROUTES`) and asserts they allow anonymous (`OPEN_ROUTES`); the first CI
  run had failed on both of its route checks.
- Checked on hivelog.ddev.site with a real 404 (status 404, title, link). The pane blocked the site's stylesheets,
  so the scene was checked with the component's CSS injected by hand, not the theme's; phone width and dark mode
  not checked.

- 3.2.1: `noUi: true` on the component. Canvas had registered it (config `canvas.component.sdc.hivelog.error-page`, plus a changed `canvas.folder.*`), listing the error scene in its library and drifting from the sync directory.
