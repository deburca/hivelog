# HiveLog — Beekeeping Activity Logger

[![CI](https://github.com/deburca/hivelog/actions/workflows/ci.yml/badge.svg)](https://github.com/deburca/hivelog/actions/workflows/ci.yml)

A Drupal 11 module for managing apiaries, bee hives, and recording regular
inspection activities — plus a seasonal duty calendar, inventory/cost
tracking, and product yield/income reporting.

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
- Follow a **seasonal duty calendar** per apiary (varroa treatment, feeding,
  harvests, winter prep, etc.), seeded automatically with a 31-entry starter
  set, with per-hive or per-apiary completion tracking by year
- Track **inventory** (consumables and durable equipment) with a purchase
  ledger, usage recording tied to calendar duties, straight-line
  depreciation, and low-stock warnings
- Maintain a **product catalog** (honey, wax, propolis, …) with harvest
  yield recording tied to calendar duties, and a cost/income report showing
  potential income and net position per apiary per year

The module uses 15 custom content entities. Apiaries, hives and inspections
form a strict parent–child hierarchy; queens are tracked separately and
linked to a hive; queen observations hang off a queen:

```
Apiary → Hive → Hive Inspection
              ↑
            Queen (active ↔ inactive)
              ↓
            Queen Observation
```

A second subsystem hangs off the apiary: a seasonal calendar of duties, each
optionally carrying a "recipe" of inventory it consumes and/or products it
yields. Reporting a duty done creates the "actual" usage/yield records (and,
for hive-scoped duties, can also create a Hive Inspection):

```
Apiary → Calendar Action ─┬─→ Hive Action Log ──→ (optional) Hive Inspection
         (31 seeded on    └─→ Apiary Action Log
          apiary creation)
              │
              ├─→ Item Requirement ──→ Inventory Item ←── Inventory Purchase
              │      (recipe: inputs)                          ↑
              │                                     Inventory Usage (from a
              │                                      "done" action log)
              │
              └─→ Product Yield ──→ Product
                     (recipe: outputs)                          ↑
                                                     Harvest Yield (from a
                                                      "done" action log)
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
   Leaflet map picker), and notes. Creating an apiary automatically seeds a
   31-entry starter seasonal calendar for it (see step 7).

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

4. **Log queen observations** — The hive view page has a shared "Hive
   Activity" heading over two tables: **Inspections**, then **Queen
   Observations** below it (newest first) — the two logs a beekeeper
   keeps during the same hive visit stay on the same page rather than on
   separate ones. Observations for every queen the hive has ever had are
   listed there, both filterable the same way (date range, plus
   observation-specific fields). An **Add Observation** button in the
   Queen Observations table's own heading opens a form scoped to the
   currently-active queen, and returns you to the hive afterward. The
   full history for one queen specifically is still also listed at the
   bottom of that queen's own page. Each observation captures:
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

7. **Follow the seasonal calendar** — Each apiary carries a set of recurring
   **Calendar Actions** scheduled by ISO week number (varroa treatment,
   spring buildup, swarm prevention, harvests, winter prep, requeening,
   CBR renewal, etc.) — 31 entries are seeded automatically when the apiary
   is created, and are ordinary, fully editable/deletable records from then
   on. Each action is scoped either to a **hive** (most of them — reported
   per hive, per year) or to the whole **apiary** (site-wide duties like
   equipment prep or CBR renewal — reported once per apiary, per year).
   - On the apiary page and each hive page, a checklist shows the current
     year's actions with their report status (unreported/done/ignored), a
     "current week" indicator, and Due now/Overdue/Upcoming timing for
     unreported items. A filter lets you switch year or status.
   - Click **Report Done** or **Report Ignored** to log an action's outcome
     for the year. For hive-scoped actions, reporting an action done offers
     an optional "also create a hive inspection" checkbox, which creates a
     linked Hive Inspection pre-filled from the action.
   - The apiary's **Full Calendar** view (`/hivelog/apiary/{apiary}/calendar`)
     lists every enabled action regardless of scope, with its own filter —
     a reference/management view rather than a reporting one.

8. **Track inventory** — Add **Inventory Items** (per apiary) for anything
   you buy or use: feed, varroa treatment, packaging, equipment. Each item
   is either a **consumable** (tracked via purchases and usage, with an
   optional low-stock threshold) or a **durable** (given a useful life in
   years and depreciated straight-line). Record **Inventory Purchases**
   (quantity, unit price, supplier) to build up a purchase ledger; stock on
   hand and weighted-average unit cost are always computed live from that
   ledger, never stored.
   - A Calendar Action can carry an **Item Requirement** "recipe" — the
     items/quantities it typically needs — set from the action's own view
     page. When a hive- or apiary-scoped report is marked done, the report
     form is pre-filled with one quantity field per required consumable;
     saving it creates/updates/removes the matching **Inventory Usage**
     records, each with a cost snapshot frozen at the moment of use.
   - Item and product autocomplete fields are scoped to the current apiary
     and exclude discontinued items/products, so selecting from a large
     multi-apiary inventory stays fast and relevant.
   - Deleting an item or product that has purchase/usage/yield history
     shows a warning on the delete confirmation page (the delete still
     proceeds — historical usage/yield records keep their own frozen cost
     snapshots regardless).

9. **Track products and harvest yield** — Add **Products** (per apiary) for
   anything you produce: honey, wax, propolis. Each product carries a
   mutable "expected unit price" used for potential-income estimates.
   - A Calendar Action can also carry a **Product Yield** "recipe" —
     mirroring item requirements, but for expected outputs instead of
     inputs (e.g. "Harvest Summer Honey" typically yields some amount of
     honey).
   - Marking a report done pre-fills one quantity field per expected
     product yield; saving it creates/updates/removes the matching
     **Harvest Yield** records, each with a unit-price snapshot frozen at
     the moment of harvest.

10. **Review the financial report** — Each apiary has a cost/income report
    (`/hivelog/apiary/{apiary}/inventory/cost-report`) for a chosen year,
    showing total consumable cost, total depreciation on durables, total
    cost, total potential income (from harvest yields), and the resulting
    net figure — plus a breakdown by item/product and a multi-year trend
    table (current year and five years prior) for spotting trends over
    time.

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

- **Apiary list** → Click apiary → Shows apiary details + hives table +
  calendar checklist + inventory/product summaries
- **Hive view** → Shows hive details + queen section + inspections table +
  calendar checklist
- **Inspection view** → Shows full inspection details
- **Queen view** → Shows queen details + observations list
- **Queen observation view** → Shows observation details
- **Calendar action view** → Shows action details + item requirements +
  product yields ("recipe")
- **Inventory item / product view** → Shows item/product details + purchase
  or yield history
- **Apiary full calendar** → Shows every enabled action for the apiary
  regardless of scope

Breadcrumbs reflect the full entity hierarchy on every page
(Home › HiveLog › Apiary › Hive › …). All ancestor crumbs are navigable links.

View, Edit, and Delete actions are available on each entity page.
`Inventory Usage` and `Harvest Yield` are the exception: they have no
dedicated add/edit/delete UI of their own — they're created, updated, or
removed only as a side effect of saving a "done" action-log report.

## Permissions

Every entity type follows the same seven-permission own/any pattern (`view
own X` / `view any X` / `add X` / `edit own X` / `edit any X` / `delete own
X` / `delete any X`), plus a single blanket permission:

| Permission               | Description                          |
|--------------------------|--------------------------------------|
| Administer HiveLog       | Full administrative access — bypasses all per-operation checks below |

The own/any pattern applies to: apiary, hive, hive inspection, queen, queen
observation, calendar action, hive action log, apiary action log, inventory
item, inventory purchase, calendar action item requirement, inventory usage,
product, calendar action product yield, harvest yield — 15 entity types ×
7 permissions = 105, plus "Administer HiveLog" = **106 permissions** total.

`Inventory usage` and `harvest yield` permissions exist even though those
entities have no dedicated CRUD UI — they're checked internally by the
access control handlers when a "done" action-log report creates, updates,
or removes the underlying usage/yield records.

## Routes

**Apiary**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog`                              | Apiary list            |
| `/hivelog/apiary/add`                   | Add apiary              |
| `/hivelog/apiary/{id}`                  | View apiary             |
| `/hivelog/apiary/{id}/edit`             | Edit apiary             |
| `/hivelog/apiary/{id}/delete`           | Delete apiary           |

**Hive**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/hives`                        | Hive list               |
| `/hivelog/apiary/{id}/hive/add`         | Add hive to apiary      |
| `/hivelog/hive/{id}`                    | View hive               |
| `/hivelog/hive/{id}/edit`               | Edit hive               |
| `/hivelog/hive/{id}/delete`             | Delete hive             |

**Hive Inspection**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/inspections`                  | Inspection list         |
| `/hivelog/hive/{id}/inspection/add`     | Add inspection to hive  |
| `/hivelog/inspection/{id}`              | View inspection         |
| `/hivelog/inspection/{id}/edit`         | Edit inspection         |
| `/hivelog/inspection/{id}/delete`       | Delete inspection       |

**Queen / Queen Observation**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/queens`                       | Queen list              |
| `/hivelog/queen/add`                    | Add queen               |
| `/hivelog/hive/{id}/queen/add`          | Add queen to hive       |
| `/hivelog/queen/{id}`                   | View queen              |
| `/hivelog/queen/{id}/edit`              | Edit queen              |
| `/hivelog/queen/{id}/delete`            | Delete queen            |
| `/hivelog/queen-observations`           | Queen observation list  |
| `/hivelog/queen/{id}/observation/add`   | Add observation to queen |
| `/hivelog/queen-observation/{id}`       | View queen observation  |
| `/hivelog/queen-observation/{id}/edit`  | Edit queen observation  |
| `/hivelog/queen-observation/{id}/delete` | Delete queen observation |

**Seasonal calendar**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/calendar-actions`             | Calendar action list    |
| `/hivelog/apiary/{id}/calendar-action/add` | Add calendar action to apiary |
| `/hivelog/calendar-action/{id}`         | View calendar action    |
| `/hivelog/calendar-action/{id}/edit`    | Edit calendar action    |
| `/hivelog/calendar-action/{id}/delete`  | Delete calendar action  |
| `/hivelog/hive-action-logs`              | Hive action log list    |
| `/hivelog/hive/{id}/calendar-action/{action}/log/add` | Report a hive-scoped action for a hive |
| `/hivelog/hive-action-log/{id}`          | View hive action log    |
| `/hivelog/hive-action-log/{id}/edit`     | Edit hive action log    |
| `/hivelog/hive-action-log/{id}/delete`   | Delete hive action log  |
| `/hivelog/apiary-action-logs`             | Apiary action log list  |
| `/hivelog/apiary/{id}/calendar-action/{action}/log/add` | Report an apiary-scoped action for an apiary |
| `/hivelog/apiary-action-log/{id}`         | View apiary action log  |
| `/hivelog/apiary-action-log/{id}/edit`    | Edit apiary action log  |
| `/hivelog/apiary-action-log/{id}/delete`  | Delete apiary action log |
| `/hivelog/apiary/{id}/calendar`         | Full calendar (all scopes) for apiary |

**Inventory**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/inventory-items`               | Inventory item list     |
| `/hivelog/inventory-item/add`            | Add inventory item      |
| `/hivelog/apiary/{id}/inventory-item/add` | Add inventory item to apiary |
| `/hivelog/inventory-item/{id}`           | View inventory item     |
| `/hivelog/inventory-item/{id}/edit`      | Edit inventory item     |
| `/hivelog/inventory-item/{id}/delete`    | Delete inventory item   |
| `/hivelog/inventory-purchases`           | Inventory purchase list |
| `/hivelog/inventory-purchase/add`        | Add inventory purchase  |
| `/hivelog/apiary/{id}/inventory-purchase/add` | Add inventory purchase to apiary |
| `/hivelog/inventory-purchase/{id}`       | View inventory purchase |
| `/hivelog/inventory-purchase/{id}/edit`  | Edit inventory purchase |
| `/hivelog/inventory-purchase/{id}/delete` | Delete inventory purchase |
| `/hivelog/calendar-action/{id}/requirement/add` | Add item requirement to calendar action |
| `/hivelog/calendar-action-requirement/{id}/edit` | Edit item requirement |
| `/hivelog/calendar-action-requirement/{id}/delete` | Delete item requirement |
| `/hivelog/apiary/{id}/inventory/cost-report` | Apiary cost/income report |

**Products / harvest yield**

| Path                                          | Description            |
|-----------------------------------------------|-------------------------|
| `/hivelog/products`                      | Product list            |
| `/hivelog/product/add`                   | Add product             |
| `/hivelog/apiary/{id}/product/add`       | Add product to apiary   |
| `/hivelog/product/{id}`                  | View product            |
| `/hivelog/product/{id}/edit`             | Edit product            |
| `/hivelog/product/{id}/delete`           | Delete product          |
| `/hivelog/calendar-action/{id}/yield/add` | Add product yield to calendar action |
| `/hivelog/calendar-action-yield/{id}/edit` | Edit product yield    |
| `/hivelog/calendar-action-yield/{id}/delete` | Delete product yield |

`inventory_usage` and `harvest_yield` have no dedicated routes — they're
system-managed only (see Usage above).

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
│   ├── hivelog.weight-histogram.css  # Weight histogram SVG
│   └── hivelog.activity-columns.css  # Hive page "Hive Activity" section layout
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
│   │   ├── Apiary.php                       # Apiary content entity; seeds the starter calendar
│   │   ├── Hive.php                         # Hive content entity
│   │   ├── HiveInspection.php               # Inspection content entity
│   │   ├── Queen.php                        # Queen content entity
│   │   ├── QueenObservation.php             # Queen observation content entity
│   │   ├── CalendarAction.php               # Seasonal-duty "plan" entity, per apiary
│   │   ├── HiveActionLog.php                # Per-hive "actual" report against a calendar action
│   │   ├── ApiaryActionLog.php              # Per-apiary "actual" report against a calendar action
│   │   ├── InventoryItem.php                # Consumable/durable inventory catalog entry
│   │   ├── InventoryPurchase.php            # Inventory purchase ledger line
│   │   ├── CalendarActionItemRequirement.php # "Recipe" line: action needs X of an item
│   │   ├── InventoryUsage.php               # "Actual" consumption record (system-managed)
│   │   ├── Product.php                      # Sellable product catalog entry
│   │   ├── CalendarActionProductYield.php   # "Recipe" line: action yields X of a product
│   │   └── HarvestYield.php                 # "Actual" production record (system-managed)
│   ├── Form/
│   │   ├── ApiaryForm.php        # Apiary add/edit form
│   │   ├── ApiaryDeleteForm.php
│   │   ├── HiveForm.php          # Hive add/edit form
│   │   ├── HiveDeleteForm.php
│   │   ├── HiveInspectionForm.php    # Inspection add/edit form
│   │   ├── HiveInspectionDeleteForm.php
│   │   ├── HivelogHiveFilterForm.php         # Hive list filter form
│   │   ├── HivelogInspectionFilterForm.php   # Inspection list filter form
│   │   ├── HivelogQueenObservationFilterForm.php  # Queen observation list filter form
│   │   ├── HivelogCalendarFilterForm.php     # Apiary/hive calendar checklist filter form
│   │   ├── HivelogFullCalendarFilterForm.php # Full calendar view filter form
│   │   ├── QueenForm.php         # Queen add/edit form
│   │   ├── QueenDeleteForm.php
│   │   ├── QueenObservationForm.php  # Queen observation add/edit form
│   │   ├── QueenObservationDeleteForm.php
│   │   ├── CalendarActionForm.php / CalendarActionDeleteForm.php
│   │   ├── CalendarActionItemRequirementForm.php / …DeleteForm.php  # Item "recipe" form
│   │   ├── CalendarActionProductYieldForm.php / …DeleteForm.php     # Yield "recipe" form
│   │   ├── HiveActionLogForm.php / …DeleteForm.php   # Hive report form; offers linked inspection
│   │   ├── ApiaryActionLogForm.php / …DeleteForm.php # Apiary report form
│   │   ├── InventoryUsageFormTrait.php       # Syncs Inventory Usage rows on report save
│   │   ├── HarvestYieldFormTrait.php         # Syncs Harvest Yield rows on report save
│   │   ├── InventoryItemForm.php / …DeleteForm.php   # Delete warns on historical references
│   │   ├── InventoryPurchaseForm.php / …DeleteForm.php
│   │   ├── ProductForm.php / ProductDeleteForm.php   # Delete warns on historical references
│   │   └── ApiaryScopedAutocompleteTrait.php # Scopes item/product autocomplete to current apiary
│   ├── Controller/
│   │   ├── ApiaryController.php      # Apiary view (hives table + calendar + full calendar)
│   │   ├── HiveController.php        # Hive view (queen + histogram + inspections + calendar)
│   │   ├── HiveInspectionController.php  # Inspection view
│   │   ├── QueenController.php       # Queen view + hive-scoped add
│   │   ├── QueenObservationController.php  # Observation view + queen-scoped add
│   │   ├── CalendarActionController.php    # Calendar action view + recipe (requirement/yield) UI
│   │   ├── HiveActionLogController.php     # Hive report view + hive-scoped add
│   │   ├── ApiaryActionLogController.php   # Apiary report view + apiary-scoped add
│   │   ├── InventoryItemController.php     # Inventory item view + apiary-scoped add
│   │   ├── InventoryPurchaseController.php # Inventory purchase view + apiary-scoped add
│   │   ├── InventoryReportController.php   # Apiary cost/income report + multi-year trend
│   │   └── ProductController.php           # Product view + apiary-scoped add
│   ├── Plugin/EntityReferenceSelection/
│   │   └── ApiaryScopedSelection.php # Selection plugin: item/product autocomplete scoped to apiary, excludes discontinued
│   ├── Breadcrumb/
│   │   └── HivelogBreadcrumbBuilder.php  # Breadcrumb builder service
│   ├── Utility/
│   │   └── SimpleBulletText.php      # Renders "- " prefixed lines as a bulleted list
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
│   ├── QueenObservationListBuilder.php
│   ├── CalendarActionAccessControlHandler.php / CalendarActionListBuilder.php
│   ├── CalendarActionItemRequirementAccessControlHandler.php
│   ├── CalendarActionProductYieldAccessControlHandler.php
│   ├── HiveActionLogAccessControlHandler.php / HiveActionLogListBuilder.php
│   ├── ApiaryActionLogAccessControlHandler.php / ApiaryActionLogListBuilder.php
│   ├── InventoryItemAccessControlHandler.php / InventoryItemListBuilder.php
│   ├── InventoryPurchaseAccessControlHandler.php / InventoryPurchaseListBuilder.php
│   ├── InventoryUsageAccessControlHandler.php  # No list builder — no dedicated UI
│   ├── ProductAccessControlHandler.php / ProductListBuilder.php
│   ├── HarvestYieldAccessControlHandler.php    # No list builder — no dedicated UI
│   └── HivelogEntityStorage.php
└── tests/
    └── src/
        ├── Functional/
        │   ├── EntityCrudJourneyTest.php          # Full add/edit/delete browser journeys
        │   └── PermissionMatrixTest.php           # Route access per permission
        ├── Kernel/
        │   ├── ApiaryScopedAccessTest.php          # Apiary-scoped access checks
        │   ├── ApiaryTest.php                      # Apiary entity tests
        │   ├── ApiaryScopedAutocompleteTest.php    # Item/product autocomplete scoping
        │   ├── CbrFieldTest.php                    # CBR user field tests
        │   ├── ControllerCacheMetadataTest.php     # Controller cache metadata tests
        │   ├── EmbeddedTableFilterPaginationTest.php # Embedded table filter + pagination
        │   ├── HiveInspectionTest.php              # Inspection entity tests
        │   ├── HiveTest.php                        # Hive entity + queen section tests
        │   ├── ModuleDependencyAuditTest.php       # Module dependency audit
        │   ├── QueenObservationTest.php            # Queen observation CRUD + view tests
        │   ├── QueenTest.php                       # Queen CRUD + colour + invariant tests
        │   ├── CalendarActionTest.php / CalendarActionSeedingTest.php  # Calendar entity + 31-entry seeding
        │   ├── HiveActionLogTest.php / HiveActionLogInspectionLinkTest.php / HiveCalendarChecklistTest.php
        │   ├── ApiaryActionLogTest.php / ApiaryCalendarChecklistTest.php
        │   ├── InventoryItemTest.php / InventoryPurchaseTest.php / InventoryAccessTest.php
        │   ├── InventoryUsageTest.php / InventoryUsageAccessTest.php / InventoryUsageReportingIntegrationTest.php
        │   ├── CalendarActionItemRequirementTest.php / …AccessTest.php
        │   ├── InventoryCostReportTest.php / InventoryEndToEndTest.php
        │   ├── ProductTest.php / ProductAccessTest.php
        │   ├── CalendarActionProductYieldTest.php / …AccessTest.php
        │   ├── HarvestYieldTest.php / HarvestYieldAccessTest.php / HarvestYieldReportingIntegrationTest.php
        │   └── YieldEndToEndTest.php
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
metadata, filter/pagination, button variant rendering, seasonal-calendar
seeding and checklist behaviour (hive- and apiary-scoped), inventory
purchase/usage/depreciation/low-stock logic, the item/product "recipe" and
cost-report/trend reporting, and the parallel product/harvest-yield/income
system — 35 Kernel test classes plus 1 Unit test class, ~426 test methods
in total (up from 178 tests/2310 assertions at the module's earlier stage).

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
   - `10011` — Re-sync the stored field storage definition for `apiary.geolocation`.
   - `10012` — Migrate flat apiary/hive/etc. permissions to the new own/any pairs.
   - `10013` — Install `beekeepers` and `visibility` fields on `apiary`.
   - `10014` — Install the `calendar_action` entity type.
   - `10015` — Install the `hive_action_log` entity type.
   - `10016` — Install the `inspection` field on `hive_action_log`.
   - `10017` — Install the `scope` field on `calendar_action` (hive vs apiary).
   - `10018` — Install the `apiary_action_log` entity type.
   - `10019` — Remove the duplicate `bee_breed` field from `hive` (breed lives on `queen`).
   - `10020` — Install the `inventory_item` and `inventory_purchase` entity types.
   - `10021` — Install the `calendar_action_item_requirement` entity type.
   - `10022` — Install the `inventory_usage` entity type.
   - `10023` — Install the `product` entity type.
   - `10024` — Install the `calendar_action_product_yield` entity type.
   - `10025` — Install the `harvest_yield` entity type.
   - `10026` — Install the `low_stock_threshold` field on `inventory_item`.
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
- **Add new calendar action categories** — Edit the `category` field's
  `allowed_values` array in `CalendarAction::baseFieldDefinitions()`; edit
  `CalendarAction::DEFAULT_STARTER_CALENDAR` to change what gets seeded on
  new apiaries (existing apiaries are unaffected — seeding runs on insert only).
- **Add new inventory/product categories** — Edit the `category` field's
  `allowed_values` array in `InventoryItem::baseFieldDefinitions()`.
- **Views integration** — All entities are available as Views base tables
  (`hivelog_apiary`, `hivelog_hive`, `hivelog_hive_inspection`,
  `hivelog_queen`, `hivelog_queen_observation`, `hivelog_calendar_action`,
  `hivelog_hive_action_log`, `hivelog_apiary_action_log`,
  `hivelog_inventory_item`, `hivelog_inventory_purchase`,
  `hivelog_calendar_action_item_requirement`, `hivelog_inventory_usage`,
  `hivelog_product`, `hivelog_calendar_action_product_yield`,
  `hivelog_harvest_yield`).
</content>
