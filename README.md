# HiveLog — Beekeeping Activity Logger

[![CI](https://github.com/deburca/hivelog/actions/workflows/ci.yml/badge.svg)](https://github.com/deburca/hivelog/actions/workflows/ci.yml)

A Drupal 11 module for managing apiaries, bee hives, and recording regular
inspection activities.

## Overview

HiveLog provides a structured system for beekeepers to:

- Track **apiary locations** (with optional GPS coordinates)
- Manage individual **hives** and their characteristics (type, material,
  temperament)
- Track **queens** as first-class entities linked to a hive, with their own
  identifier, provenance, breed, and active/inactive lifecycle (hives outlive
  queens, so queen info — including breed — is not stored on the hive
  itself; a hive's breed identity comes from whichever queen currently
  occupies it)
- Record detailed **inspection logs** covering queen status, brood, stores,
  health, feeding, and management actions
- Log **queen observations** separately from hive inspections for
  queen-specific notes (health, temperament, active laying, photos)

The module uses five custom content entities. Apiaries, hives and
inspections form a strict parent–child hierarchy; queens are tracked
separately and linked to a hive; queen observations hang off a queen:

```
Apiary → Hive → Hive Inspection
              ↑
            Queen (active ↔ inactive)
              ↓
            Queen Observation
```

## Requirements

- Drupal 11
- [Geofield](https://www.drupal.org/project/geofield) module — provides the geofield field type for storing geospatial data
- [Leaflet](https://www.drupal.org/project/leaflet) module — provides Leaflet/OpenStreetMap map display and interactive map widget

## Installation

Enable the module with Drush:

```
drush en hivelog -y
```

Entity database tables are created automatically on install.

## Usage

After installation, navigate to **Administration → Structure → HiveLog**
(`/hivelog`).

### Workflow

1. **Create an apiary** — Add a location where your hives are kept. Optionally
   include a text description, geolocation coordinates (via an interactive
   Leaflet map picker), and notes.

2. **Add hives to an apiary** — From the apiary view page, click "Add Hive".
   Each hive records:
   - Hive type (10x12, Norwegian, Langstroth, Trugstad, Normal)
   - Hive material (Wood, Styrofoam)
   - Temperament (Calm, Moderate, Aggressive)
   - Status (Active, Inactive, Dead, Sold, Merged)

3. **Track queens** — Queens are separate from hives because a hive typically
   outlives any single queen. From the hive view page, click "Add Queen" to
   record a new queen and attach it to the hive. Each queen records:
   - A human-readable ID (e.g. `Q-2026-001`)
   - Origin (breeder, swarm, supplier, …)
   - Queen year (international marking colour auto-calculated)
   - Breed (Buckfast, Carniolan, Italian, Caucasian, Dark European/AMM, Other)
     and temperament
   - Purchase cost and purchase date
   - Hive reference and introduction date
   - Status: **Active** or **Inactive**
   Only one queen may be active per hive at a time. Saving a new active
   queen on a hive that already has one automatically marks the previous
   queen inactive; she stays linked to the hive as history rather than
   being detached, and appears in the "Previous Queens" list on the hive
   view page.

4. **Log queen observations** — From the hive view page, beside the
   **Edit Queen** button, an **Add Observation** button opens a form
   scoped to the currently-active queen, and returns you to the hive
   afterward. Observations for every queen the hive has ever had are
   listed on the hive view page in a "Queen Observations" table
   side-by-side with the Inspections table (newest first) — the two logs
   a beekeeper keeps during the same hive visit stay next to each other
   rather than on separate pages. The full history for one queen
   specifically is still also listed at the bottom of that queen's own
   page. Each observation captures:
   - Observation date
   - Health (Excellent / Good / Fair / Poor)
   - Temperament (Calm / Moderate / Aggressive)
   - Active (was the queen observed actively laying / moving)
   - Free-text notes
   - Photos (multi-value image field)

5. **Record your CBR number** — Each user can store a Central Beehive
   Registration (CBR) number on their account (`/user/{uid}/edit`). The
   number is shown as a caption on the HiveLog landing page and rendered
   as the first column of every apiary row, so the apiary list always
   identifies the registered owner alongside the apiary.

6. **Log inspections** — From the hive view page, click "Add Inspection". Each
   inspection captures:
   - **External check (before opening)** — Flight activity, dead bees, signs of
     robbing, wasps, hive weight (hefting)
   - **Queen status** — Queen seen, queen cells present, eggs seen
   - **Brood, honey and pollen** — Brood pattern quality, honey stores,
     pollen stores
   - **Colony condition** — Temperament, population strength
   - **Health** — Varroa check performed, mite count, disease signs
   - **Management** — Colony fed, feed type, number of supers, actions taken
   - **Notes** — Free-text observations

### Inspection Validation Rules

To preserve data integrity, the inspection form enforces dependent-field rules:

- If **Fed** is checked, **Feed Type** is required.
- If **Fed** is not checked, **Feed Type** must be left empty.
- If **Varroa Check** is checked, **Varroa Count** is required.
- If **Varroa Check** is not checked, **Varroa Count** must be left empty.

### Queen Marking Colour

When a queen year is entered on the queen record, the international queen
marking colour is calculated automatically on save:

| Last digit of year | Colour |
|--------------------|--------|
| 1, 6               | White  |
| 2, 7               | Yellow |
| 3, 8               | Red    |
| 4, 9               | Green  |
| 0, 5               | Blue   |

### Navigation

Each entity view page provides drill-down navigation:

- **Apiary list** → Click apiary → Shows apiary details + hives table
- **Hive view** → Shows hive details + queen section + inspections table (newest first)
- **Inspection view** → Shows full inspection details
- **Queen view** → Shows queen details + observations list
- **Queen observation view** → Shows observation details

Breadcrumbs reflect the full entity hierarchy on every page
(Home › HiveLog › Apiary › Hive › …). All ancestor crumbs are navigable links.

View, Edit, and Delete actions are available on each entity page.

## Permissions

| Permission               | Description                          |
|--------------------------|--------------------------------------|
| Administer HiveLog       | Full administrative access            |
| View/Add/Edit/Delete apiaries    | Per-operation access to apiaries |
| View/Add/Edit/Delete hives       | Per-operation access to hives    |
| View/Add/Edit/Delete hive inspections | Per-operation access to inspections |
| View/Add/Edit/Delete queens      | Per-operation access to queens   |
| View/Add/Edit/Delete queen observations | Per-operation access to queen observations |

Users with "Administer HiveLog" bypass all individual permission checks.

## Routes

| Path                                          | Description            |
|-----------------------------------------------|------------------------|
| `/hivelog`                              | Apiary list            |
| `/hivelog/hives`                        | Hive list              |
| `/hivelog/inspections`                  | Inspection list        |
| `/hivelog/apiary/add`                   | Add apiary             |
| `/hivelog/apiary/{id}`                  | View apiary            |
| `/hivelog/apiary/{id}/edit`             | Edit apiary            |
| `/hivelog/apiary/{id}/delete`           | Delete apiary          |
| `/hivelog/apiary/{id}/hive/add`         | Add hive to apiary     |
| `/hivelog/hive/{id}`                    | View hive              |
| `/hivelog/hive/{id}/edit`               | Edit hive              |
| `/hivelog/hive/{id}/delete`             | Delete hive            |
| `/hivelog/hive/{id}/inspection/add`     | Add inspection to hive |
| `/hivelog/inspection/{id}`              | View inspection        |
| `/hivelog/inspection/{id}/edit`         | Edit inspection        |
| `/hivelog/inspection/{id}/delete`       | Delete inspection      |
| `/hivelog/queens`                       | Queen list             |
| `/hivelog/queen/add`                    | Add queen              |
| `/hivelog/hive/{id}/queen/add`          | Add queen to hive      |
| `/hivelog/queen/{id}`                   | View queen             |
| `/hivelog/queen/{id}/edit`              | Edit queen             |
| `/hivelog/queen/{id}/delete`            | Delete queen           |
| `/hivelog/queen-observations`           | Queen observation list |
| `/hivelog/queen/{id}/observation/add`   | Add observation to queen |
| `/hivelog/queen-observation/{id}`       | View queen observation |
| `/hivelog/queen-observation/{id}/edit`  | Edit queen observation |
| `/hivelog/queen-observation/{id}/delete` | Delete queen observation |

## Module Structure

```
hivelog/
├── hivelog.info.yml              # Module definition
├── hivelog.install               # Install, update and uninstall hooks
├── hivelog.module                # Hook implementations
├── hivelog.permissions.yml       # Permission definitions
├── hivelog.routing.yml           # Route definitions
├── hivelog.services.yml          # Service definitions
├── hivelog.links.menu.yml        # Admin menu link
├── hivelog.libraries.yml         # CSS library definitions
├── hivelog.links.action.yml      # Action links (Add buttons)
├── hivelog.links.task.yml        # Local task tabs (View/Edit/Delete)
├── README.md
├── css/
│   ├── hivelog.responsive.css    # Breakpoint tokens and shared CSS custom properties
│   ├── hivelog.buttons.css       # Button appearance (sole source of truth, ADR-0012)
│   ├── hivelog.forms.css         # Entity form spacing and vertical-tab layout
│   ├── hivelog.tables.css        # Responsive entity-list and detail tables
│   ├── hivelog.filter-form.css   # Filter form layout
│   ├── hivelog.images.css        # Image grid
│   ├── hivelog.map.css           # Leaflet map responsive sizing
│   └── hivelog.weight-histogram.css  # Weight histogram SVG
├── components/                       # Single Directory Components (SDC)
│   ├── button/
│   │   ├── button.component.yml
│   │   └── button.twig
│   ├── button-group/
│   │   ├── button-group.component.yml
│   │   ├── button-group.css
│   │   └── button-group.twig
│   └── entity-table/
│       ├── entity-table.component.yml
│       ├── entity-table.css
│       └── entity-table.twig
├── src/
│   ├── Entity/
│   │   ├── Apiary.php            # Apiary content entity
│   │   ├── Hive.php              # Hive content entity
│   │   ├── HiveInspection.php    # Inspection content entity
│   │   ├── Queen.php             # Queen content entity
│   │   └── QueenObservation.php  # Queen observation content entity
│   ├── Form/
│   │   ├── ApiaryForm.php        # Apiary add/edit form
│   │   ├── ApiaryDeleteForm.php
│   │   ├── HiveForm.php          # Hive add/edit form
│   │   ├── HiveDeleteForm.php
│   │   ├── HiveInspectionForm.php    # Inspection add/edit form
│   │   ├── HiveInspectionDeleteForm.php
│   │   ├── HivelogHiveFilterForm.php         # Hive list filter form
│   │   ├── HivelogInspectionFilterForm.php   # Inspection list filter form
│   │   ├── QueenForm.php         # Queen add/edit form
│   │   ├── QueenDeleteForm.php
│   │   ├── QueenObservationForm.php  # Queen observation add/edit form
│   │   └── QueenObservationDeleteForm.php
│   ├── Controller/
│   │   ├── ApiaryController.php      # Apiary view (with hives table)
│   │   ├── HiveController.php        # Hive view (queen + histogram + inspections)
│   │   ├── HiveInspectionController.php  # Inspection view
│   │   ├── QueenController.php       # Queen view + hive-scoped add
│   │   └── QueenObservationController.php  # Observation view + queen-scoped add
│   ├── Breadcrumb/
│   │   └── HivelogBreadcrumbBuilder.php  # Breadcrumb builder service
│   ├── ApiaryAccessControlHandler.php
│   ├── ApiaryAccessTrait.php         # Shared apiary-scoped access logic
│   ├── ApiaryListBuilder.php
│   ├── HiveAccessControlHandler.php
│   ├── HiveInspectionAccessControlHandler.php
│   ├── HiveInspectionListBuilder.php
│   ├── HiveListBuilder.php
│   ├── QueenAccessControlHandler.php
│   ├── QueenListBuilder.php
│   ├── QueenObservationAccessControlHandler.php
│   └── QueenObservationListBuilder.php
└── tests/
    └── src/
        ├── Functional/
        │   ├── EntityCrudJourneyTest.php          # Full add/edit/delete browser journeys
        │   └── PermissionMatrixTest.php           # Route access per permission
        ├── Kernel/
        │   ├── ApiaryScopedAccessTest.php          # Apiary-scoped access checks
        │   ├── ApiaryTest.php                      # Apiary entity tests
        │   ├── CbrFieldTest.php                    # CBR user field tests
        │   ├── ControllerCacheMetadataTest.php     # Controller cache metadata tests
        │   ├── EmbeddedTableFilterPaginationTest.php # Embedded table filter + pagination
        │   ├── HiveInspectionTest.php              # Inspection entity tests
        │   ├── HiveTest.php                        # Hive entity + queen section tests
        │   ├── ModuleDependencyAuditTest.php       # Module dependency audit
        │   ├── QueenObservationTest.php            # Queen observation CRUD + view tests
        │   └── QueenTest.php                       # Queen CRUD + colour + invariant tests
        └── Unit/
            └── Breadcrumb/
                └── HivelogBreadcrumbBuilderTest.php  # Breadcrumb unit tests
```

## Testing

Run the kernel and unit test suite (fast — no browser required):

```
ddev exec "SIMPLETEST_DB=mysql://db:db@db:3306/db \
  SIMPLETEST_BASE_URL=http://web \
  php /var/www/html/vendor/bin/phpunit \
  -c /var/www/html/web/core \
  /var/www/html/web/modules/contrib/hivelog/tests/src/Kernel/ \
  /var/www/html/web/modules/contrib/hivelog/tests/src/Unit/ \
  --group hivelog"
```

The suite covers entity CRUD, parent–child relationships, queen colour
auto-calculation, field option validation, inspection logging, access control,
breadcrumb ancestry threading and `applies()` coverage, controller cache
metadata, filter/pagination, and button variant rendering (178 tests, 2310
assertions).

Functional tests (full browser journeys) are in `tests/src/Functional/` and
require a running ChromeDriver. Run them with the same command but include the
`Functional/` path; they are slower and are `continue-on-error` candidates in CI.

## Deployment

After pulling new code to a target environment, run the following steps:

```
composer install --no-dev
drush updb -y
drush cr
```

**Step summary:**

1. `composer install` — Ensures all PHP dependencies (including geofield and
   leaflet) are present.
2. `drush updb` — Runs any pending database update hooks. Current hooks:
   - `10001` — Migrate apiary lat/lng fields to geolocation field.
   - `10002` — Migrate geolocation field from geolocation module to geofield.
   - `10003` — Add `external_check` field to hive inspections.
   - `10004` — Add `weight` field to hive inspections.
   - `10005` — Add `images` field to hives.
   - `10006` — Add `images` field to hive inspections.
   - `10007` — Retire the dormant `queen_brood` field on hive inspections
     (data in the removed column is dropped; the swarming / supersedure
     signal is already captured by `queen_cells`).
   - `10008` — Install the `queen` entity type and drop the `queen_year` /
     `queen_colour` columns from `hive`. No data migration.
  - `10009` — Install the `queen_observation` entity type.
  - `10010` — Install the `cbr_number` (Central Beehive Registration) base
    field on the `user` entity. No data migration.
3. `drush cr` — Rebuilds caches so Drupal picks up any changes to entity
   definitions, routing, or services.

For DDEV-based environments, prefix commands with `ddev`:

```
ddev composer install
ddev drush updb -y
ddev drush cr
```

**Post-deployment verification:**

- Confirm all modules are enabled: `drush pm:list --status=enabled --filter=hivelog`
- Confirm no pending updates remain: `drush updb --no`
- Run the test suite (see Testing section below).

## Extending the Module

- **Add new hive types or materials** — Edit the `allowed_values` arrays in
  the corresponding `baseFieldDefinitions()` in `src/Entity/Hive.php`.
- **Add new breeds** — Edit the `breed` field's `allowed_values` array in
  `Queen::baseFieldDefinitions()` in `src/Entity/Queen.php`.
- **Add new inspection fields** — Add a new `BaseFieldDefinition` in
  `src/Entity/HiveInspection.php`, then uninstall and reinstall the module (or
  write an update hook) to apply schema changes.
- **Views integration** — All entities are available as Views base tables
  (`hivelog_apiary`, `hivelog_hive`, `hivelog_hive_inspection`,
  `hivelog_queen`, `hivelog_queen_observation`).
