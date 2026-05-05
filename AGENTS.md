# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

HiveLog is a custom Drupal 11 module (located at `web/modules/hivelog` inside a
larger CMS checkout) that provides a beekeeping activity logger. It defines
five custom content entities. Apiaries, hives and inspections form a strict
parent–child hierarchy; queens are tracked separately and linked to the
hive they are currently installed in (hives outlive queens); queen
observations hang off a queen:

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

Run the full PHPUnit suite for this module (kernel + unit tests, `hivelog`
group):

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
- Custom "scoped add" routes (`hivelog.hive.add`,
  `hivelog.inspection.add`, `hivelog.queen.add`) whose path includes the
  parent entity (`/apiary/{apiary}/hive/add`,
  `/hive/{hive}/inspection/add`, `/hive/{hive}/queen/add`). These are
  handled by `addForm()` methods on the child's controller, which
  pre-populates the parent reference on a new child entity before handing
  it to the entity form. Always use these routes when adding children so
  the parent reference is set correctly.

Canonical view pages are rendered by custom controllers
(`ApiaryController`, `HiveController`, `HiveInspectionController`) rather
than the default view builder, because each parent view embeds a list
builder of its children (apiary shows its hives; hive shows its
inspections newest-first).

Permissions use the `_permission: 'X+administer hivelog'` OR syntax so that
users with `administer hivelog` bypass all fine-grained checks; the access
control handlers in `src/*AccessControlHandler.php` mirror the same rule.

### Services

Only one service is registered (`hivelog.services.yml`):
`hivelog.breadcrumb` — a `BreadcrumbBuilder` with priority 100 that produces
the Apiary → Hive → Inspection trail on any hivelog route. When adding new
routes under `/hivelog/...` make sure the breadcrumb builder's
`applies()` logic still matches.

### Tests

- `tests/src/Kernel/*` — kernel tests (extend `KernelTestBase`) covering
  entity CRUD, field option validation, parent/child relationships,
  queen-colour auto-calc, and inspection logging. They install this module
  plus its dependencies via `$modules`.
- `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php` — pure unit
  test for the breadcrumb builder using mocked `EntityTypeManager`.

All tests are tagged with `@group hivelog` so the `--group hivelog` filter
above runs exactly this module's suite.

## Entity schema changes

Because all field storage is defined in code, any change to
`baseFieldDefinitions()` (new field, changed settings, removed field) must
be paired with an update hook in `hivelog.install` using
`\Drupal::entityDefinitionUpdateManager()`. Existing hooks
(`hivelog_update_10001`–`10003`) are the canonical examples: read existing
column data, uninstall the old storage, install the new storage, then
re-save entities through the entity API so derived columns (e.g. geofield's
lat/lon/geohash) are recomputed. Do not rely on
`drush entity:updates` / `entup` — it no longer performs destructive schema
changes in Drupal 11.
