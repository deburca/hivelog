# AGENTS.md

This file provides guidance to AI coding agents working in this repository.

## Project Overview

HiveLog is a standalone Drupal 11 module distributed as its own repository.
The repo root **is** the module root — there is no surrounding CMS checkout
here. When the module is installed into a Drupal site it lands at
`web/modules/hivelog` (or equivalent), but all paths in this repo are
module-relative (e.g. `src/`, `css/`, `components/`, `tests/`).

The module provides a beekeeping activity logger. Core (this repo's own
`src/`) defines 15 content entity types; four optional submodules under
`modules/` add 6 more (5 real, 1 development-only) — see "Content entities"
and "Submodules" below. Apiaries, hives and inspections form a strict
parent–child hierarchy; queens are tracked separately and linked to the hive
they are currently installed in (hives outlive queens); queen observations
hang off a queen:

```
Apiary → Hive → Hive Inspection
              ↑
            Queen (active ↔ inactive)
              ↓
            Queen Observation
```

Every other core entity type is scoped to an `Apiary` (directly, or via a
`CalendarAction`/action log) — see "Content entities" for the full picture,
grouped rather than diagrammed since a single ASCII tree stopped being
readable past the five above.

Required contrib dependencies: `geofield`, `leaflet` (see
`hivelog.info.yml`). The geocoder module is intentionally NOT a dependency:
the apiary map widget is `leaflet_widget_default`, not a geocoder-backed
widget, and nothing else in the module talks to geocoder services. Do not
reintroduce the dependency without adding and documenting a concrete use.

## CI Pipeline

A GitHub Actions workflow runs on every push and on every published release:
`.github/workflows/ci.yml`. As of task 0137, local (`composer lint` /
`composer stan`) and CI checks read the exact same config files
(`phpcs.xml.dist`, `phpstan.neon`) with no separate path/extension lists to
drift out of sync — every `phpcs`/`phpstan` invocation, local or CI, covers
`src/`, `tests/` **and every submodule under `modules/`** (nanoprobe,
collective, nexus, assimilate). A submodule with zero test/lint coverage is
the gap ADR-0098 §7 warns about; every discovery loop below (phpcs, phpstan,
Kernel/Unit/Functional test dirs) exists specifically to avoid it.

**`lint` job (PHP 8.3 only, no database):** runs bare `phpcs --warning-severity=0`
(reads `phpcs.xml.dist`) as a hard gate. Its own `phpstan` step is a no-op
placeholder — mglaman/phpstan-drupal (needed for Drupal-aware analysis without
a full scaffold) isn't installed in this job's isolated tool directory: real
phpstan runs in the `test` job instead, where `drupal/core` is available.

**`test` job (PHP 8.3 / 8.4 / 8.5 matrix):** builds a full `drupal/recommended-project`
scaffold, installs the module via a Composer `path` repository (symlink:false),
then runs, per matrix cell unless noted:
- **PHPUnit kernel + unit (hard gate).** Test directories are discovered via
  `find "$MODULE_PATH" -type d -path '*/tests/src/Kernel'` (and `/Unit`), not
  a fixed path — a submodule's own `tests/src/Kernel` is picked up
  automatically.
- **phpstan (PHP 8.3 only, hard gate).** `phpstan analyse -c "$MODULE_PATH/phpstan.neon"`
  — the module's own config, so it covers `modules/` and reads
  `phpstan-baseline.neon` (440 pre-existing level-2 findings, all the
  Drupal-specific false positives bare phpstan produces without the mglaman
  extension — see `phpstan.neon`'s own comment for why that extension can't
  run here). The baseline is a ratchet, not a pass: fails on any *new*
  finding, baselined file or not; only shrinks when a baselined finding's
  code is fixed or removed.
- **PHPUnit functional (`continue-on-error: true` until ChromeDriver is
  confirmed stable).** Discovered the same way as kernel/unit
  (`*/tests/src/Functional`). Being advisory is why `RouteEntityAccessTest`
  (kernel, hard gate) carries every access-critical assertion
  `PermissionMatrixTest` (functional) also makes — a real access regression
  must be caught by the hard gate on its own, not rely on this job.

**Patch-path constraint (important):** `composer.json` records geofield patch
paths relative to the Drupal project root at Packagist install time
(`web/modules/contrib/hivelog/patches/…`). In CI the path repository copies
the module source to `web/modules/hivelog/` (no `contrib/` segment). The CI
workflow overrides the patch block in the scaffold's `composer.json` before
running `composer install`. If you change patch file names or locations, update
both `composer.json` **and** the override step in `.github/workflows/ci.yml`.

Run lint and static analysis locally with:
```
composer lint
composer stan
```

## Common Commands

The surrounding project runs inside DDEV. All PHP/Drush commands must be
executed inside the `web` container; paths are from the container's
perspective (`/var/www/html/...`).

Enable / reinstall the module:

```
ddev drush en hivelog -y
ddev drush pmu hivelog -y
```

Run database updates after changing entity schema (see "Entity schema
changes" below):

```
ddev drush updb -y
ddev drush cr
```

Run the full PHPUnit suite for this module (kernel + unit + functional
tests, `hivelog` group):

```
ddev exec "SIMPLETEST_DB=mysql://db:db@db:3306/db \
  SIMPLETEST_BASE_URL=http://web \
  php /var/www/html/vendor/bin/phpunit \
  -c /var/www/html/web/core \
  /var/www/html/web/modules/hivelog/tests/ \
  --group hivelog"
```

Run a single test class or method by replacing the path argument, e.g.:

```
ddev exec "SIMPLETEST_DB=mysql://db:db@db:3306/db \
  SIMPLETEST_BASE_URL=http://web \
  php /var/www/html/vendor/bin/phpunit \
  -c /var/www/html/web/core \
  /var/www/html/web/modules/hivelog/tests/src/Kernel/HiveTest.php \
  --filter testQueenColourAutoCalculation"
```

**Functional tests** (`tests/src/Functional/`) extend `BrowserTestBase` and
require a fully booted Drupal site with a Chrome/ChromeDriver process. They
run under `--group hivelog` but are significantly slower than kernel tests
and will fail if the browser driver is unavailable. Prefer targeting
`tests/src/Kernel/` or `tests/src/Unit/` for fast feedback loops.

## Architecture

### Content entities

Each entity lives in `src/Entity/` as a `ContentEntityBase` subclass declared
with the PHP 8 `#[ContentEntityType]` attribute. Fields are defined entirely
in code via `baseFieldDefinitions()` — there is no exported config for field
storage, view display, or form display. Changing a field definition therefore
requires a corresponding update hook (see "Entity schema changes" below).
Core defines 15 entity types, grouped below by what they're for; each
submodule's own entity types are in "Submodules".

**The hive hierarchy** (the diagram above):

- `Apiary` — top-level location; stores a `geofield` `geolocation` column
  (WKT POINT). Earlier schemas used separate lat/lng columns and the
  `geolocation` module; `hivelog_update_10001` and `_10002` migrate through
  those states.
- `Hive` — references an `Apiary` via `apiary` entity_reference. Queen info
  is NOT stored on the hive (see `Queen` below); `Hive::getActiveQueen()`
  resolves the active queen via a reverse lookup on `queen.hive`, and
  `Hive::getQueens()` resolves every queen (active or retired) ever linked
  to the hive, most recent first, so the hive page can aggregate queen
  observations across the whole hive lifetime (the hive page shows no
  standalone "previous queens" list — "View all Queens" covers that).
- `HiveInspection` — references a `Hive` and carries the full inspection
  payload (external check, queen, brood, stores, health, management, notes).
- `Queen` — references a `Hive` via `hive` entity_reference (optional).
  Stores identity/provenance (`name`, `origin`, `queen_year`, `queen_colour`,
  `breed`, `temperament`, `purchase_cost`, `purchase_date`,
  `introduction_date`, `status` [`active` | `inactive`]). `preSave()`
  auto-derives `queen_colour` from `queen_year` via the `QUEEN_COLOUR_MAP`
  constant (international queen marking convention) AND enforces the
  "one active queen per hive" invariant by demoting any previously active
  queen on the same hive to `inactive` — her `hive` reference is left
  intact so her observations still aggregate onto the hive page and she
  stays listed under "View all Queens".
- `QueenObservation` — references a `Queen` via `queen` entity_reference
  (required). Captures point-in-time queen-specific notes separate from
  hive-level inspections: `observation_date`, `health` (excellent / good /
  fair / poor), `temperament` (calm / moderate / aggressive), `active`
  (boolean — observed actively laying / moving), `notes`, `images`.
  Surfaced from the hive page via an **Add Observation** button next to
  **Edit Queen**, and listed at the end of the queen canonical page.

**Seasonal calendar / action logs** (ADR-0025) — a `CalendarAction` is the
recurring "plan", scoped to one `Apiary`; the two log types are its "did it
happen" record, one row per occurrence, starting unreported (no row, or a
row with `status: pending`) until a beekeeper reports it `done` or `ignored`:

- `CalendarAction` — references an `Apiary`. One recurring seasonal duty
  (varroa treatment, harvest, winter prep), week-number scheduled, shared by
  every hive in the apiary. Can be disabled without losing history the two
  log types below may still reference.
- `HiveActionLog` — references a `Hive` and the `CalendarAction` it reports
  on. Per-hive execution record.
- `ApiaryActionLog` — references an `Apiary` and the `CalendarAction` it
  reports on. The apiary-scoped sibling of `HiveActionLog`, for
  apiary-scoped actions; deliberately has no `inspection` field (linking an
  inspection is inherently hive-scoped).

**Inventory, products and yields** (ADR-0027, ADR-0034) — each catalog type
is scoped to one `Apiary`; the "recipe" (`CalendarActionItemRequirement`/
`CalendarActionProductYield`) and "actual" (`InventoryUsage`/`HarvestYield`)
entities mirror each other one level removed (inputs vs. outputs):

- `InventoryItem` — references an `Apiary`. One catalog entry for something
  bought and used (sugar, varroa strips, frames). `item_type` branches
  `consumable` (tracked via purchases + usage) from `durable` (purchased
  once, depreciates over `useful_life_years`).
- `InventoryPurchase` — references an `Apiary` and the `InventoryItem`
  bought. One acquisition record — amount and unit price; stock on hand is
  always computed from these, never a stored running balance.
- `InventoryUsage` — references an `InventoryItem` and exactly one of
  `hive_action_log` / `apiary_action_log`. How much of a *consumable* item
  was really used when that log was reported `done`. No add/edit/delete UI
  of its own — written as a side effect of saving the log's own form (see
  `InventoryUsageFormTrait`).
- `Product` — references an `Apiary`. One catalog entry for something
  produced and sold (honey, beeswax, propolis). No separate purchase
  ledger like `InventoryItem`'s: `expected_unit_price` lives directly on
  the product as a single mutable current-best-guess.
- `CalendarActionItemRequirement` — references a `CalendarAction` and an
  `InventoryItem` (must belong to the same apiary). The "recipe" estimate:
  this action typically requires this much of this item.
- `CalendarActionProductYield` — references a `CalendarAction` and a
  `Product` (must belong to the same apiary). The "recipe" estimate:
  this action typically produces this much of this product.
- `HarvestYield` — references a `Product` and exactly one of
  `hive_action_log` / `apiary_action_log`. How much of a product was
  *really* produced when that log was reported `done`. No add/edit/delete
  UI of its own, same as `InventoryUsage`.

The module also adds a `cbr_number` field to the Drupal `user` entity via
`hook_entity_base_field_info()`. Uninstall has special handling in
`hivelog_uninstall()` to avoid a fatal PDO exception when the column has
already been removed — see `_hivelog_cleanup_cbr_field()`.

Allowed-value lists for hive `type`, `material`, `temperament`, `status`,
queen `breed` / `temperament` / `status`, and the various inspection enums
are hard-coded in the respective `baseFieldDefinitions()`.
Extending them requires editing the entity class **and** writing an update
hook if existing data must be preserved.

### Submodules

Four optional modules live under `modules/`, each its own installable
Drupal module with its own `src/`, routes, hooks and tests — `hivelog` core
never depends on any of them (ADR-0098). They register with core through the
`hook_hivelog_*` hooks documented in `hivelog.api.php`:
`hook_hivelog_apiary_view_panels()` / `hook_hivelog_hive_view_panels()`
(a read-only section on the apiary/hive canonical page, ADR-0099),
`hook_hivelog_hive_insights_panels()` (the hive's separate Insights page),
`hook_hivelog_apiary_stat_tiles()` / `hook_hivelog_hive_stat_tiles()`
("at a glance" stat tiles), `hook_hivelog_needs_attention_alerts()` /
`hook_hivelog_dashboard_sections()` (the dashboard), `hook_hivelog_app_nav_items()`
(the front-end nav strip) and `hook_hivelog_delete_dependencies()` (the
delete-dependency registry, task 0134 — a submodule declares rows for
entity types core can't name directly).

- **`nanoprobe`** — sensor device registration and automated data
  ingestion ("the senses"). Adds `SensorDevice` (registered hardware,
  scoped to an apiary or one hive within it), `SensorReading` (raw
  machine-written time-series points, ingestion-endpoint-only) and
  `SensorReadingDaily` (persisted per-day min/max/avg rollups, since raw
  readings are purged after a retention window). Depends only on
  `hivelog`.
- **`collective`** — structured hive/apiary context served via a
  credentialed HTTP API. Adds `ApiClient` (a site-level context-read
  credential; normally exactly one row). Works from manually-logged data
  alone, richer with `nanoprobe` sensor data too. Depends only on
  `hivelog`.
- **`nexus`** — in-process AI synthesis: reads structured context from
  `collective` and calls a configured AI provider to write `HiveInsight`
  recommendations directly (no HTTP round-trip to `collective`'s own API
  for this). Adds `AiProviderConfig` (which integration mode and
  credentials to use — the credential itself is resolved at call time via
  the `key` module, never stored on the entity) and `HiveInsight` (the
  three-way daily verdict: act now / inspect soon / all clear;
  machine-written only, by `nexus_cron()`). Depends on `hivelog`,
  `collective` and `key`.
- **`assimilate`** — **development/demo only, never enable on a real
  site.** Fabricates a demo apiary, hive and sensor devices, then keeps
  generating plausible mock `SensorReading` data on cron so the Sensors
  panel, dashboard and `nexus` insight generation have something to show
  before real hardware exists. Refuses to install on a site with real
  AI-insights-enabled data. Depends on `hivelog` and `nanoprobe`; adds no
  entity types of its own.

### Routing, controllers and forms

Routes in `hivelog.routing.yml` follow two patterns:

- Standard entity CRUD routes (`entity.<id>.canonical`, `.add_form`,
  `.edit_form`, `.delete_form`) that use Drupal's `_entity_form` / list
  builder machinery.
- Custom "scoped add" routes whose path includes the parent entity.
  Always use these routes when adding children so the parent reference is
  pre-populated correctly:
  - `hivelog.hive.add` → `/hivelog/apiary/{apiary}/hive/add`
  - `hivelog.inspection.add` → `/hivelog/hive/{hive}/inspection/add`
  - `hivelog.queen.add` → `/hivelog/hive/{hive}/queen/add`
  - `hivelog.queen_observation.add` → `/hivelog/queen/{queen}/observation/add`

All routes use the `/hivelog/` path prefix. New routes must follow this
convention; the breadcrumb builder's `applies()` also relies on it (see
Services below).

Canonical view pages are rendered by custom controllers
(`ApiaryController`, `HiveController`, `HiveInspectionController`,
`QueenController`, `QueenObservationController`) rather than the default
view builder, because each parent view embeds a list builder of its children
(apiary shows its hives; hive shows its inspections newest-first).

Permissions use the `_permission: 'X+administer hivelog'` OR syntax so that
users with `administer hivelog` bypass all fine-grained checks; the access
control handlers in `src/*AccessControlHandler.php` mirror the same rule.

The whole HiveLog menu tree lives in the site's front-end `main` menu, not
Structure, so a top-level "Add" action reachable via Drupal's core Local
Actions block or nested menu links is not guaranteed to be visible — the
front end may not place that block, or its menu block may not render more
than one level deep. `ApiaryListBuilder` and `QueenListBuilder` (the two
collections with a context-free add route — `entity.apiary.add_form` and
`entity.queen.add_form`) therefore build their own "Add" heading directly
in `render()` instead of relying on `hivelog.links.action.yml`. Hive,
HiveInspection and QueenObservation have no context-free add route at all
(they always require a parent — apiary/hive/queen — pre-selected via a
scoped add route above), so their collection pages have no add button by
design, with or without menu chrome.

Every list builder, core and submodule alike, extends `HivelogListBuilder`
(task 0068), whose `buildOperations()` renders the row Operations column as a
`hivelog:button-group` cluster (Edit + Delete, Delete in the danger
variant) instead of core's collapsed dropbutton — matching the flat
action buttons on the canonical pages and the dashboard (ADR-0012). The
SDC-table builders call `$this->buildOperations($entity)` for the cell;
the ones on core's `#type => 'table'` inherit it via
`EntityListBuilder::buildRow()`. A new list builder should extend
`HivelogListBuilder`, never `EntityListBuilder` directly.

### CSS and components

CSS libraries are declared in `hivelog.libraries.yml` (core) and each
submodule's own `<module>.libraries.yml` (currently `collective`,
`nanoprobe`; `nexus`/`assimilate` ship no CSS of their own). Every library,
core or submodule, depends on `hivelog/responsive` (which defines shared
breakpoint tokens in `css/hivelog.responsive.css`) — directly or
transitively. Core's own dependency chain:

```
hivelog/responsive
  ← hivelog/weight_histogram
  ← hivelog/images
  ← hivelog/map
  ← hivelog/app_nav
  ← hivelog/notices
  ← hivelog/buttons
      ← hivelog/tables
          ← hivelog/activity_columns
          ← hivelog/dashboard
      ← hivelog/filter_form
      ← hivelog/forms
      ← hivelog/dashboard   (also depends on buttons + tables directly)
```

When adding a new CSS library, declare `hivelog/responsive` as a dependency.
Do not add `@media` rules without following the breakpoints defined in
`css/hivelog.responsive.css` (`≤480px` phone, `≤768px` small tablet).

SDC components live in `components/` (`button/`, `button-group/`,
`entity-table/`, `stat-tile/`) and, for `nanoprobe`, its own
`components/metric-tabs/`. `css/hivelog.buttons.css` is the **sole source
of truth** for button appearance (ADR-0012, task 0010). Rules are scoped to
the named context wrappers listed in that file's own header — keep that
list (not a count here) in sync when a new context wrapper is added,
including a submodule's. Do not add theme framework classes (`btn`,
`btn-primary`, `btn-danger`, Tailwind utilities) to `button.twig` or
`button-group.twig` — the module has no Tailwind build step and admin-theme
classes will conflict with the module's token rules.

`button.twig` emits only semantic class names: `button` (base), plus one of
`button--primary`, `button--danger`, or `button--default` as a variant
modifier. No framework or utility classes.

`button-group.twig` renders a `<div class="hivelog-button-group">` wrapper
only. All layout (inline-flex), join-corner styling (`:first-child` /
`:last-child` border-radius overrides), border collapse (`margin-left: -1px`),
and compact sizing (`--hivelog-btn-compact-padding-*` tokens with a
`@media (max-width: 768px)` promotion to standard size) are defined entirely
in `css/hivelog.buttons.css`. See ADR-0012 and ADR-0024 in
`docs/project-management/decisions/`.

#### Heading hierarchy (task 0128)

Every page's title is the theme's own H1. Below it: **a page's top-level
sections are H2; subsections nested inside a section are H3; nothing goes
deeper without a parent level.** This applies uniformly — detail-page
sections (`HivelogDetailPageTrait::buildSection()`/`buildPhotosGrid()`),
embedded-list headings (`hivelog-list-heading__title`, whether built inline
by a controller or via `HivelogListBuilder`), delete-form dependency notices
(`HivelogEntityDeleteForm`), and submodule panels contributed through
`hook_hivelog_*_panels()` (nanoprobe's Sensors panel, nexus's AI Insight
panel) — a panel injected into a page is always a top-level section there,
so it always emits H2, never a level passed in by the hook caller. The one
place a nested H3 is correct today is the Hive page's "Hive Activity" H2
wrapping its Inspections/Queen Observations H3 sub-lists. Skipped or
out-of-order levels break screen-reader heading navigation (WCAG 2.2
SC 1.3.1 / 2.4.6) and stop a theme from styling "section heading"
consistently.

### Theming HiveLog

The module ships a complete, neutral look that works on any admin theme.
A site theme recolours it by **redefining CSS custom properties**, never by
restating the module's selectors (ADR-0060 — the brand lives in the theme,
the module stays palette-free). The supported surface:

- **Button tokens** — `--hivelog-btn-*` on `:root` in
  `css/hivelog.responsive.css` (ADR-0012). `default` / `primary` / `danger`
  backgrounds, borders, hover shades, sizing. `button.twig` emits only
  semantic classes and consumes these.
- **Surface / ink / line / severity tokens** — `--hivelog-*` on `:root` in
  `css/hivelog.responsive.css` (task 0062):
  `--hivelog-surface`, `--hivelog-surface-2`, `--hivelog-ground`,
  `--hivelog-ink`, `--hivelog-ink-muted`, `--hivelog-ink-faint`,
  `--hivelog-hairline`, `--hivelog-border`, `--hivelog-rule`,
  `--hivelog-critical` / `--hivelog-critical-tint`,
  `--hivelog-warning` / `--hivelog-warning-tint`.
  Consumed by `css/hivelog.dashboard.css`, `css/hivelog.tables.css`,
  `css/hivelog.filter-form.css`, `css/hivelog.forms.css` and the
  `stat-tile` / `entity-table` SDC stylesheets. `css/hivelog.map.css`,
  `.images.css` and `.weight-histogram.css` are not tokenised (chart /
  media internals, not part of the themed chrome).
- **Stable class names** — the `.hivelog-*` BEM classes on the dashboard
  (`__masthead`, `__attention`, `__stat-tiles`, `__split`, …), the
  `hivelog-inventory-report-table` / `-breakdown` / `-trend` report
  tables, `.hivelog-filter-form`, `.hivelog-list-heading` and the SDC
  roots (`.hivelog-entity-table`, `.hivelog-stat-tile`,
  `.hivelog-button-group`). Treat these as API; renames go through a task.
- **`hivelog-page` body class** — `hivelog_preprocess_html()` adds it on
  every route whose path is under `/hivelog` (path match, because the
  plain `entity.<type>.collection` routes are not named `hivelog.*` —
  same gap `HivelogBreadcrumbBuilder` handles). A theme can scope
  whole-page rules to `body.hivelog-page` without its own preprocess
  hook. Themes still need a way to *attach* their skin sheet on the
  collection pages (those carry no hivelog library): load it from the
  theme's `global` library, not via `libraries-extend` — `libraries-extend`
  only fires for a library a controller attaches directly, so it reaches
  the dashboard but not the `EntityListBuilder` collections.

The reference implementation is the **beeswax** theme — Packagist package
**`deburca/beeswax`** (repo `github.com/deburca/beeswax`), kbg's default
theme. `src/hivelog.css` redefines the `--hivelog-*` tokens from its own
`--bw-*` palette and adds only the handful of rules the token surface
does not yet cover. beeswax `1.0.x` expects hivelog `>= 1.8.3` (the
`--hivelog-*` surface tokens and `hivelog_preprocess_html()`).

### Services

Core registers its own services in `hivelog.services.yml`; each submodule
registers its own in `<module>.services.yml` (panel/report builders, alert
collectors, provider callers, …) — check that file rather than this one for
a submodule's exact list, so this paragraph doesn't go stale the way the
count it used to state already had. Core's own:

- `hivelog.breadcrumb` — see below; the one with enough going on to warrant
  its own subsection.
- `hivelog.app_nav_builder` (`HivelogAppNavBuilder`) — builds the front-end
  nav strip's item list, merging core's own with whatever submodules
  contribute via `hook_hivelog_app_nav_items()`.
- `hivelog.stat_tile_builder` (`HivelogStatTileBuilder`) — merges the
  "at a glance" stat tiles a canonical page shows, from
  `hook_hivelog_apiary_stat_tiles()` / `hook_hivelog_hive_stat_tiles()`.
- `hivelog.delete_dependency_counter` (`HivelogDeleteDependencyCounter`,
  task 0134) — counts a parent entity's children per
  `HivelogDeleteDependencyRegistry` row (BLOCK/WARN/CASCADE/DETACH), for
  the delete form's dependency sections and (task 0141) the BLOCK access
  check. See `src/Delete/` and
  `docs/project-management/decisions/0103-delete-policy-for-records-with-children.md`.

`hivelog.breadcrumb` is a `BreadcrumbBuilder` with priority **1004** that produces
the Apiary → Hive → … trail on any hivelog route. Since task 0067 `applies()`
matches **by path**: every route whose path is `/hivelog` or under `/hivelog/`
(the module's own entity routes, the `hivelog.*` controllers, and bolt-on
routes such as `layout_builder.overrides.<entity>.*`), minus an explicit
`$non_page_routes` exclusion list for file-download endpoints. New routes under
`/hivelog/...` are covered automatically; only add a `$non_page_routes` entry
for a route that must NOT get a breadcrumb.

`build()` shape:
- **Collections + the combined report** end with their own name as a terminal
  crumb (`$leaf_pages` map): `Home › HiveLog › <Plural>`.
- **Site-wide `entity.<type>.add_form`** (no parent in the path) hang off their
  collection: `Home › HiveLog › <Plural>`.
- **Canonical / edit / delete / Layout Builder** pages thread the entity's
  ancestor chain to the apiary, ending with the entity's own label:
  `Home › HiveLog › <Apiary> [› <Hive> …] › <Entity>`. A missing ancestor
  (deleted apiary, unassigned queen) just shortens the trail.
- **Per-apiary report / full-calendar** add a named terminal after the apiary
  link (`$apiary_page_crumbs` map).
- **Calendar-action requirement / yield** edit-delete pages thread
  `Apiary → Calendar action → <sub-entity>` with a non-linked (`<nolink>`)
  terminal (these have no canonical page).

Adding a new hivelog entity type: give it a `build()` block that resolves its
`apiary` (directly or via its parent) and adds its `entity.<type>.canonical`
crumb — mirror the `product` / `inventory_item` / `inventory_purchase` loop.

The priority of 1004 is intentional — it must exceed the `easy_breadcrumb`
module's priority of 1003, which is commonly installed on Drupal sites and
uses a catch-all `applies()`. If the hivelog builder does not outrank it,
`easy_breadcrumb` intercepts all hivelog routes and produces path-based trails
instead of the correct entity-hierarchy trails. Do not lower this priority
below 1004 without confirming `easy_breadcrumb` is not installed.

### In-app navigation (task 0119)

`HivelogAppNavBuilder::getAllItems()` is the single registry for
`hivelog`'s destinations: core's own 8 built-ins (`builtInItems()`) plus
every `hook_hivelog_app_nav_items()` contribution (`hivelog.api.php`) —
each item a `['title' => TranslatableMarkup, 'url' => Url, 'weight' =>
int]` descriptor. Two surfaces read this one registry instead of each
keeping its own hand-maintained copy:

- **The in-app nav strip** — `build()` filters `getAllItems()` to what
  the current user can access (`Url::access()`) and injects the result
  into `page.content` via `hivelog_preprocess_page()` (task 0105; not a
  placed block or `hook_page_top()` — see that hook's own docblock in
  `hivelog.module`).
- **Main-menu links** — `hivelog.links.menu.yml` declares one deriver
  base plugin (`hivelog.nav_item`, `deriver:
  Drupal\hivelog\Plugin\Derivative\HivelogMenuLinks`) that emits one
  `hivelog.nav_item:<key>` menu link per `getAllItems()` entry, all
  parented under the single hand-written `hivelog.admin` link. A
  submodule that wants a main-menu entry implements
  `hook_hivelog_app_nav_items()` only — it no longer ships its own
  `<module>.links.menu.yml` (`collective`/`nexus`/`nanoprobe` all did,
  pre-0119). A static → derived plugin ID is a different string (the
  derived one always carries `:`), so `hivelog_update_10029` re-keys any
  per-site menu-UI customisation (weight/enabled/expanded, stored by
  plugin ID in `core.menu.static_menu_link_overrides`) from the old
  static IDs onto the new derived ones.

### Tests

All test classes use the PHP 8 `#[Group('hivelog')]` attribute, so
`--group hivelog` runs exactly this module's suite.

- `tests/src/Kernel/*` — kernel tests (`KernelTestBase`) covering entity
  CRUD, field option validation, parent/child relationships, queen-colour
  auto-calc, inspection logging, access control, and cache metadata. Install
  the module plus its dependencies via `$modules`.
- `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` — pure unit
  test for the breadcrumb builder using mocked `EntityTypeManager`.
- `tests/src/Functional/*` — functional tests (`BrowserTestBase`) for
  permissions and end-to-end CRUD journeys. Require a running Drupal site
  and browser driver; slow — avoid running for fast iteration.

## Entity schema changes

Because all field storage is defined in code, any change to
`baseFieldDefinitions()` (new field, changed settings, removed field) must
be paired with an update hook in `hivelog.install` using
`\Drupal::entityDefinitionUpdateManager()`. Check the highest-numbered
`hivelog_update_N` already in `hivelog.install` for the next number to use
— existing hooks are the canonical examples: read
existing column data, uninstall the old storage, install the new storage,
then re-save entities through the entity API so derived columns (e.g.
geofield's lat/lon/geohash) are recomputed. Do not rely on
`drush entity:updates` / `entup` — it no longer performs destructive schema
changes in Drupal 11.

## Patches

Two geofield patches ship with the module in `patches/`:
- `geofield-drupal11-attribute-discovery.patch`
- `geofield-validator-compatibility.patch`

The patch paths recorded in `composer.json` use the install-time location
(`web/modules/contrib/hivelog/patches/...`), not the repo-relative path.
This is correct for Composer's `cweagans/composer-patches` plugin and should
not be changed to a repo-relative path.
