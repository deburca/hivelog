# AGENTS.md

This file provides guidance to AI coding agents working in this repository.

## Project Overview

HiveLog is a standalone Drupal 11 module distributed as its own repository.
The repo root **is** the module root — there is no surrounding CMS checkout
here. When the module is installed into a Drupal site it lands at
`web/modules/hivelog` (or equivalent), but all paths in this repo are
module-relative (e.g. `src/`, `css/`, `components/`, `tests/`).

The module provides a beekeeping activity logger with five custom content
entities. Apiaries, hives and inspections form a strict parent–child
hierarchy; queens are tracked separately and linked to the hive they are
currently installed in (hives outlive queens); queen observations hang off
a queen:

```
Apiary → Hive → Hive Inspection
              ↑
            Queen (active ↔ inactive)
              ↓
            Queen Observation
```

Required contrib dependencies: `geofield`, `leaflet` (see
`hivelog.info.yml`). The geocoder module is intentionally NOT a dependency:
the apiary map widget is `leaflet_widget_default`, not a geocoder-backed
widget, and nothing else in the module talks to geocoder services. Do not
reintroduce the dependency without adding and documenting a concrete use.

## CI Pipeline

A GitHub Actions workflow runs on every push and on every published release:
`.github/workflows/ci.yml`.

**`lint` job (PHP 8.3 only):** runs `phpcs` (Drupal + DrupalPractice standards)
and `phpstan` (level 2, mglaman/phpstan-drupal) against `src/` and `tests/`.
No database required. Config files: `phpcs.xml.dist`, `phpstan.neon`.

**`test` job (PHP 8.3 / 8.4 / 8.5 matrix):** builds a full `drupal/recommended-project`
scaffold, installs the module via a Composer `path` repository (symlink:false),
runs `phpunit --group hivelog` against kernel + unit tests (hard gate) and
functional tests (continue-on-error: true until ChromeDriver is confirmed
stable).

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
requires a corresponding update hook (see `hivelog.install`).

- `Apiary` — top-level location; stores a `geofield` `geolocation` column
  (WKT POINT). Earlier schemas used separate lat/lng columns and the
  `geolocation` module; `hivelog_update_10001` and `_10002` migrate through
  those states.
- `Hive` — references an `Apiary` via `apiary` entity_reference. Queen info
  is NOT stored on the hive (see `Queen` below); `Hive::getActiveQueen()`
  resolves the active queen via a reverse lookup on `queen.hive`.
- `HiveInspection` — references a `Hive` and carries the full inspection
  payload (external check, queen, brood, stores, health, management, notes).
- `Queen` — references a `Hive` via `hive` entity_reference (optional).
  Stores identity/provenance (`name`, `origin`, `queen_year`, `queen_colour`,
  `breed`, `temperament`, `purchase_cost`, `purchase_date`,
  `introduction_date`, `status` [`active` | `inactive`]). `preSave()`
  auto-derives `queen_colour` from `queen_year` via the `QUEEN_COLOUR_MAP`
  constant (international queen marking convention) AND enforces the
  "one active queen per hive" invariant by demoting any previously active
  queen on the same hive to `inactive` and clearing its `hive` reference.
- `QueenObservation` — references a `Queen` via `queen` entity_reference
  (required). Captures point-in-time queen-specific notes separate from
  hive-level inspections: `observation_date`, `health` (excellent / good /
  fair / poor), `temperament` (calm / moderate / aggressive), `active`
  (boolean — observed actively laying / moving), `notes`, `images`.
  Surfaced from the hive page via an **Add Observation** button next to
  **Edit Queen**, and listed at the end of the queen canonical page.

The module also adds a `cbr_number` field to the Drupal `user` entity via
`hook_entity_base_field_info()`. Uninstall has special handling in
`hivelog_uninstall()` to avoid a fatal PDO exception when the column has
already been removed — see `_hivelog_cleanup_cbr_field()`.

Allowed-value lists for hive `type`, `material`, `breed`, `temperament`,
`status`, queen `breed` / `temperament` / `status`, and the various
inspection enums are hard-coded in the respective `baseFieldDefinitions()`.
Extending them requires editing the entity class **and** writing an update
hook if existing data must be preserved.

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

### CSS and components

CSS libraries are declared in `hivelog.libraries.yml`. All libraries depend
on `hivelog/responsive` (which defines shared breakpoint tokens in
`css/hivelog.responsive.css`). The dependency chain is:

```
hivelog/responsive  ←  hivelog/buttons  ←  hivelog/tables
                    ←  hivelog/forms
                    ←  hivelog/filter_form
                    ←  hivelog/images
                    ←  hivelog/map
                    ←  hivelog/weight_histogram
```

When adding a new CSS library, declare `hivelog/responsive` as a dependency.
Do not add `@media` rules without following the breakpoints defined in
`css/hivelog.responsive.css` (`≤480px` phone, `≤768px` small tablet).

SDC components live in `components/` (`button/`, `button-group/`,
`entity-table/`). `css/hivelog.buttons.css` is the **sole source of truth**
for button appearance (ADR-0012, task 0010). Rules are scoped to eight named
context wrappers listed in the file header. Do not add theme framework classes
(`btn`, `btn-primary`, `btn-danger`, Tailwind utilities) to `button.twig` or
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

### Services

Only one service is registered (`hivelog.services.yml`):
`hivelog.breadcrumb` — a `BreadcrumbBuilder` with priority **1004** that produces
the Apiary → Hive → … trail on any hivelog route. `applies()` matches by
route-name prefix (`entity.apiary.`, `entity.hive.`, `entity.hive_inspection.`,
`entity.queen.`, `entity.queen_observation.`, `hivelog.`). When adding new
routes under `/hivelog/...` make sure `applies()` still matches them — and
explicitly exclude any non-page routes (e.g. file-download endpoints) so
they do not get an incorrect breadcrumb.

The priority of 1004 is intentional — it must exceed the `easy_breadcrumb`
module's priority of 1003, which is commonly installed on Drupal sites and
uses a catch-all `applies()`. If the hivelog builder does not outrank it,
`easy_breadcrumb` intercepts all hivelog routes and produces path-based trails
instead of the correct entity-hierarchy trails. Do not lower this priority
below 1004 without confirming `easy_breadcrumb` is not installed.

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
`\Drupal::entityDefinitionUpdateManager()`. The latest hook is
`hivelog_update_10013`. Existing hooks are the canonical examples: read
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
