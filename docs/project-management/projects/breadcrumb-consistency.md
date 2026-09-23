---
type: project
tags: [hivelog/project]
status: active
target: 1.4.0
created: 2026-06-17
---
# Project: Breadcrumb Consistency

## Goal
Guarantee that every Hivelog route renders a predictable, correct, and tested
breadcrumb trail (Home → HiveLog → Apiary → Hive → …), and that the rules are
documented and covered by tests so future routes stay consistent.

## Scope
- In scope: auditing all routes against the current breadcrumb builder,
  reconciling the older [[0002-breadcrumb-queen-canonical]] task, fixing any
  remaining gaps, and adding test coverage across route types.
- In scope since 2026-09-23: the module's other navigation surfaces, meaning
  the in-app nav strip (`HivelogAppNavBuilder`), the main-menu links, and the
  local tasks / actions. They share the breadcrumb's list of destinations and
  its notion of which section a page belongs to, so they drift together. See
  the Key findings (2026-09-23 navigation review) section below.
- Out of scope: visual styling of the breadcrumb itself (that is theme
  territory) and non-Hivelog routes. The nav strip's own CSS is in scope; it
  ships with the module.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT priority asc, file.name asc
```
Static index (in suggested execution order):
- [[0013-breadcrumb-route-audit]] — audit + reconcile (do first)
- [[0014-implement-breadcrumb-consistency-fixes]]
- [[0015-breadcrumb-test-coverage]]
- [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]] —
  follow-up gap found after collective/nexus/nanoprobe submodules added
  new CRUD pages post-audit (ApiClient, AiProviderConfig, SensorDevice)

Navigation review follow-ups (2026-09-23), in suggested order:
- [[0116-breadcrumb-builder-parent-map-refactor]] — no-behaviour-change
  refactor; makes 0117 a single rule
- [[0117-breadcrumb-terminal-crumb-on-form-pages]] — **bug**: edit /
  delete / add pages have no clickable way back (high)
- [[0118-page-owned-edit-delete-then-retire-local-tasks]] — page-owned
  Edit / Delete everywhere, then drop the local tasks / actions
- [[0119-single-source-navigation-registry]] — one list of destinations,
  menu links derived from it
- [[0120-app-nav-active-state-and-grouping]] — active section, grouping,
  dashboard link
- [[0121-reachability-of-orphaned-collection-pages]] — needs a decision
- [[0122-top-level-entity-breadcrumb-threading]] — needs a decision
- [[0123-refresh-navigation-reference-docs]] — last, once the code has settled

## Key findings (from code scan, 2026-06-17; reconciled 2026-06-22)
- `src/Breadcrumb/HivelogBreadcrumbBuilder.php` already builds trails for
  apiary, hive, hive_inspection, queen, **and** queen_observation routes, plus a
  catch-all for the `hivelog.` route prefix (`applies()`, lines 41–49).
- The queen branch already threads Apiary → Hive → Queen ancestry and handles
  unassigned queens. That matches the older
  [[0002-breadcrumb-queen-canonical]] task, which is now closed in the vault.
- Ancestry is only added when a route parameter is upcast to an object
  (`is_object(...)` guards). Any custom route that passes a raw ID instead of an
  upcast entity will silently lose its trail — a prime audit target.
- `applies()` is prefix-based; `AGENTS.md` warns that it must stay in sync when
  routes are added or renamed.

## Key findings (2026-09-23)
- Submodules (`collective`, `nexus`, `nanoprobe`) each added real CRUD
  UIs for their own entity types after this project's original audit
  closed, and none of them updated `HivelogBreadcrumbBuilder` — the
  builder's `applies()` still matched their routes (path-based, so
  nothing was silently excluded), but `build()` had no ancestry block
  for `api_client`, `ai_provider_config`, or `sensor_device`, so those
  pages' breadcrumbs stopped dead at "Home › HiveLog". Fixed in
  [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]].
  Confirms the original audit's own risk note above ("only add a
  `$non_page_routes` entry..." / drift over time as routes are added)
  was correct to flag — worth a periodic re-audit whenever a submodule
  ships a new entity type's own management UI, not just a one-time scan.

## Key findings (2026-09-23 navigation review)
A review of menus, routes, local tasks, the in-app nav strip and the
breadcrumb builder, with trails checked live on `cms2` (`quick_silver`):
- **Form pages lose their way back.** The theme renders the last crumb as
  plain text. Edit / delete / add pages end on the entity (or parent, or
  collection) crumb, so that crumb is never clickable: `/hivelog/hive/22/edit`
  renders `[Home] › [Apiary] › Hive`. Rule 2 of [[0013-breadcrumb-policy]]
  assumed the entity is always the page. Amended by
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]; fixed in
  [[0117-breadcrumb-terminal-crumb-on-form-pages]].
- **Theme truncation.** Trails longer than 3 crumbs render as
  `Home … <last two>`, so on deep pages the last two crumbs are the only
  visible navigation. This makes the terminal crumb matter even more.
- **The builder repeats itself.** About 10 near-identical ancestor walks, three
  per-entity sub-page maps, and an injected `entityTypeManager` that is never
  used → [[0116-breadcrumb-builder-parent-map-refactor]].
- **Three hand-synced destination lists**: menu links, nav strip items and
  breadcrumb collection names, plus route titles and `label_collection`.
  They have already drifted ("Hive Inspections" vs "Inspections") →
  [[0119-single-source-navigation-registry]].
- **The nav strip doesn't show where you are.** No active state, 11 flat
  pills mixing daily and setup pages, no dashboard link →
  [[0120-app-nav-active-state-and-grouping]].
- **Edit / Delete comes from three places.** `hivelog.links.task.yml` (15
  tabs, 5 of 13 entity types) renders in the Navigation module's top bar, not
  as page tabs. For Apiary and Hive it is the *only* Edit / Delete. Inspection,
  Queen and Queen observation get it twice (tabs and page buttons). Sensor
  device, API client and AI provider config get neither. (Corrected the same
  day: first recorded as "unused".) `hivelog.links.action.yml` (2 actions)
  really is unused on `cms2` →
  [[0118-page-owned-edit-delete-then-retire-local-tasks]].
- **Unreachable pages.** The hive / apiary action-log collections have no
  inbound link at all; Calendar Actions and the all-apiaries Financial Report
  are dashboard-tile-only → [[0121-reachability-of-orphaned-collection-pages]].
- **Top-level threading is inconsistent.** Submodule entities go through their
  collection; Apiary and unassigned Queen don't (Apiary's skip is left over
  from before [[0057-dashboard-information-architecture]]) →
  [[0122-top-level-entity-breadcrumb-threading]].
- **Stale docs.** AGENTS.md says only one service is registered (there are
  three) and doesn't mention the nav strip. `navigation-and-page-layout.md`
  predates the dashboard → [[0119-single-source-navigation-registry]] (AGENTS.md
  services) and [[0123-refresh-navigation-reference-docs]].

## Open questions
- Does the current implementation fully match [[0013-breadcrumb-policy]] on
  excluding non-page `hivelog.*` routes once such routes exist?
- Beyond the already-closed queen canonical case, are there any real route gaps
  left after the audit matrix is completed?

## Related projects
- [[page-structure-consistency]] (sibling review, 2026-09-23; shares
  [[0118-page-owned-edit-delete-then-retire-local-tasks]])

## Related decisions
- [[0013-breadcrumb-policy]]
- [[0020-access-parity-custom-routes]]
- [[0057-dashboard-information-architecture]] (amends 0013's root crumb)
- [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]] (proposed;
  amends 0013 rule 2)
