# HiveLog — Beekeeping Activity Logger

A Drupal 11 module for managing apiaries, bee hives, and recording regular
inspection activities.

## Overview

HiveLog provides a structured system for beekeepers to:

- Track **apiary locations** (with optional GPS coordinates)
- Manage individual **hives** and their characteristics (type, material, breed,
  queen details)
- Record detailed **inspection logs** covering queen status, brood, stores,
  health, feeding, and management actions

The module uses three custom content entities with parent–child relationships:

```
Apiary → Hive → Hive Inspection
```

## Requirements

- Drupal 11
- [Geofield](https://www.drupal.org/project/geofield) module — provides the geofield field type for storing geospatial data
- [Geocoder](https://www.drupal.org/project/geocoder) module — provides geocoding services
- [Leaflet](https://www.drupal.org/project/leaflet) module — provides Leaflet/OpenStreetMap map display and interactive map widget

## Installation

Enable the module with Drush:

```
drush en hivelog -y
```

Entity database tables are created automatically on install.

## Usage

After installation, navigate to **Administration → Structure → HiveLog**
(`/admin/hivelog`).

### Workflow

1. **Create an apiary** — Add a location where your hives are kept. Optionally
   include a text description, geolocation coordinates (via an interactive
   Leaflet map picker), and notes.

2. **Add hives to an apiary** — From the apiary view page, click "Add Hive".
   Each hive records:
   - Hive type (10x12, Norwegian, Langstroth, Trugstad, Normal)
   - Hive material (Wood, Styrofoam)
   - Queen year and auto-calculated queen marking colour
   - Bee breed (Buckfast, Carniolan, Italian, Caucasian, Dark European/AMM, Other)
   - Temperament (Calm, Moderate, Aggressive)
   - Status (Active, Inactive, Dead, Sold, Merged)

3. **Log inspections** — From the hive view page, click "Add Inspection". Each
   inspection captures:
   - **External check (before opening)** — Flight activity, dead bees, signs of
     robbing, wasps, hive weight (hefting)
   - **Queen status** — Queen seen, queen cells present, eggs seen
   - **Brood** — Brood pattern quality, capped queen brood
   - **Stores** — Honey stores level, pollen stores level
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

When a queen year is entered on a hive, the international queen marking colour
is calculated automatically on save:

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
- **Hive view** → Shows hive details + inspections table (newest first)
- **Inspection view** → Shows full inspection details

View, Edit, and Delete tabs are available on each entity page.

## Permissions

| Permission               | Description                          |
|--------------------------|--------------------------------------|
| Administer HiveLog       | Full administrative access            |
| View/Add/Edit/Delete apiaries    | Per-operation access to apiaries |
| View/Add/Edit/Delete hives       | Per-operation access to hives    |
| View/Add/Edit/Delete hive inspections | Per-operation access to inspections |

Users with "Administer HiveLog" bypass all individual permission checks.

## Routes

| Path                                          | Description            |
|-----------------------------------------------|------------------------|
| `/admin/hivelog`                              | Apiary list            |
| `/admin/hivelog/hives`                        | Hive list              |
| `/admin/hivelog/inspections`                  | Inspection list        |
| `/admin/hivelog/apiary/add`                   | Add apiary             |
| `/admin/hivelog/apiary/{id}`                  | View apiary            |
| `/admin/hivelog/apiary/{id}/edit`             | Edit apiary            |
| `/admin/hivelog/apiary/{id}/delete`           | Delete apiary          |
| `/admin/hivelog/apiary/{id}/hive/add`         | Add hive to apiary     |
| `/admin/hivelog/hive/{id}`                    | View hive              |
| `/admin/hivelog/hive/{id}/edit`               | Edit hive              |
| `/admin/hivelog/hive/{id}/delete`             | Delete hive            |
| `/admin/hivelog/hive/{id}/inspection/add`     | Add inspection to hive |
| `/admin/hivelog/inspection/{id}`              | View inspection        |
| `/admin/hivelog/inspection/{id}/edit`         | Edit inspection        |
| `/admin/hivelog/inspection/{id}/delete`       | Delete inspection      |

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
├── hivelog.links.action.yml      # Action links (Add buttons)
├── hivelog.links.task.yml        # Local task tabs (View/Edit/Delete)
├── README.md
├── src/
│   ├── Entity/
│   │   ├── Apiary.php            # Apiary content entity
│   │   ├── Hive.php              # Hive content entity
│   │   └── HiveInspection.php    # Inspection content entity
│   ├── Form/
│   │   ├── ApiaryForm.php        # Apiary add/edit form
│   │   ├── ApiaryDeleteForm.php
│   │   ├── HiveForm.php          # Hive add/edit form
│   │   ├── HiveDeleteForm.php
│   │   ├── HiveInspectionForm.php    # Inspection add/edit form
│   │   └── HiveInspectionDeleteForm.php
│   ├── Controller/
│   │   ├── ApiaryController.php      # Apiary view (with hives table)
│   │   ├── HiveController.php        # Hive view (with inspections table)
│   │   └── HiveInspectionController.php  # Inspection view
│   ├── Breadcrumb/
│   │   └── HivelogBreadcrumbBuilder.php  # Breadcrumb builder service
│   ├── ApiaryListBuilder.php
│   ├── HiveListBuilder.php
│   ├── HiveInspectionListBuilder.php
│   ├── ApiaryAccessControlHandler.php
│   ├── HiveAccessControlHandler.php
│   └── HiveInspectionAccessControlHandler.php
└── tests/
    └── src/
        ├── Kernel/
        │   ├── ApiaryTest.php            # Apiary entity tests
        │   ├── HiveTest.php              # Hive entity + queen colour tests
        │   └── HiveInspectionTest.php    # Inspection entity tests
        └── Unit/
            └── Breadcrumb/
                └── HivelogBreadcrumbBuilderTest.php  # Breadcrumb unit tests
```

## Testing

Run the kernel test suite:

```
ddev exec "SIMPLETEST_DB=mysql://db:db@db:3306/db \
  SIMPLETEST_BASE_URL=http://web \
  php /var/www/html/vendor/bin/phpunit \
  -c /var/www/html/web/core \
  /var/www/html/web/modules/hivelog/tests/ \
  --group hivelog"
```

The suite includes 53 tests covering entity CRUD, relationships, queen colour
auto-calculation, field option validation, inspection logging, and breadcrumb
building.

## Deployment

After pulling new code to a target environment, run the following steps:

```
composer install --no-dev
drush updb -y
drush cr
```

**Step summary:**

1. `composer install` — Ensures all PHP dependencies (including geofield,
   geocoder, leaflet) are present.
2. `drush updb` — Runs any pending database update hooks. Current hooks:
   - `10001` — Migrate apiary lat/lng fields to geolocation field.
   - `10002` — Migrate geolocation field from geolocation module to geofield.
   - `10003` — Add `external_check` field to hive inspections.
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

- **Add new hive types or breeds** — Edit the `allowed_values` arrays in the
  corresponding `baseFieldDefinitions()` in `src/Entity/Hive.php`.
- **Add new inspection fields** — Add a new `BaseFieldDefinition` in
  `src/Entity/HiveInspection.php`, then uninstall and reinstall the module (or
  write an update hook) to apply schema changes.
- **Views integration** — All entities are available as Views base tables
  (`hivelog_apiary`, `hivelog_hive`, `hivelog_hive_inspection`).
