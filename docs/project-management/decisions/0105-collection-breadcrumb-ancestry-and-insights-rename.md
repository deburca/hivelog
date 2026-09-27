---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-27
supersedes:
---
# ADR-0105: Collection-page breadcrumb ancestry, and renaming Setup to Insights

## Status
accepted, 2026-09-27, at the user's explicit request — a breadcrumb
trail specified as a list of type-to-type chains, plus "rename setup
to Insights" as its own explicit instruction. Design/planning artifact
only; see [[in-app-navigation-restructuring]] for the implementation
tasks (0152–0154).

## Context
[[0104-two-tier-in-app-navigation]] explicitly scoped the breadcrumb
trail as unaffected — it "walks the entity hierarchy, not the nav
registry." That's still true for **canonical/edit/delete pages**: a
specific hive inspection's own page already threads
`Apiaries › [Apiary] › [Hive] › [Inspection]` today, because
`HivelogEntityHierarchy::PARENT_FIELD` already encodes
`hive_inspection → hive`, `queen → hive`, `queen_observation → queen`
— the real per-instance domain hierarchy AGENTS.md's own diagram
describes. What's *not* true is **collection pages**: `/hivelog/hives`,
`/hivelog/inspections`, `/hivelog/queens`, etc. each get a flat
`Home › HiveLog › <Plural>` terminal crumb today (the `$leaf_pages`
mechanism in `HivelogBreadcrumbBuilder::build()`), with no "Apiaries"
or "Hives" ancestor crumb threaded in front of them at all — unlike the
nav strip, which (since task 0148) *does* show Hives/Inspections/
Queens/Queen Observations/Inventory Items/Inventory Purchases/Products
as children of Apiaries, and API Clients/AI Provider Configs/Sensor
Devices as children of Setup. The user's request is to close that gap:
make collection-page breadcrumbs reflect the same conceptual
hierarchy, deeper in places than the (deliberately flat, two-tier-only)
nav strip goes — `Apiaries › Hives › Inspections` and
`Apiaries › Hives › Queens › Observations` are three and four levels,
not two.

The request also names an explicit rename ("Rename setup to
Insights") separate from the breadcrumb-chain list itself, and uses
shorter terminal labels for four other steps in that list
("Observations", "Inventory", "Purchases", "AI Providers", "Sensors")
without an equivalent explicit "rename" instruction attached to them —
a meaningful distinction this ADR treats as intentional, not sloppy
phrasing (see Decision).

## Decision

### Setup → Insights: a real, universal rename
Every surface renames: the built-in nav item's key (`setup` →
`insights`) and title, the route (`hivelog.setup` → `hivelog.insights`,
path `/hivelog/setup` → `/hivelog/insights`), `SetupController` →
`InsightsController`, `SetupPageAccessCheck` → `InsightsPageAccessCheck`,
and every submodule's own `parent: 'setup'` → `parent: 'insights'`
(`collective`, `nanoprobe`, `nexus`). Safe to do as a clean rename, not
a deprecate-and-alias: nothing in this nav restructuring has been
tagged in a release yet, so there is no existing bookmark, external
link, or `core.menu.static_menu_link_overrides` row anywhere to
preserve compatibility for.

### Everything else in the list: breadcrumb terminal-label shorthand, not an entity rename
"Observations", "Inventory", "Purchases", "AI Providers" and "Sensors"
become that step's breadcrumb text **only** — via the same literal
`t()`-per-route mechanism `terminalCrumbLabel()` already uses for named
sub-pages, extended to also override a *collection* route's terminal
label where it differs from `label_collection`. `label_collection`
itself, every routing.yml `_title`, the nav strip's own item title, and
the main-menu link text are **unchanged** ("Queen Observations",
"Inventory Items", "Inventory Purchases", "AI Provider Configs",
"Sensor Devices" stay exactly as they are everywhere except inside a
breadcrumb). Reasoning: only the Setup→Insights rename came with its
own explicit instruction; treating the others the same way would be a
bigger, unrequested footprint (entity attribute changes, routing.yml
`_title` changes for Add/Edit/Delete forms too, more tests touched)
for a request that read, everywhere else, as describing breadcrumb
*text*, not a product rename. "API Clients", "Hives", "Inspections",
"Queens" and "Products" need no override at all — their existing
`label_collection` already matches the requested breadcrumb text.

### A new, separate ancestor map — `PARENT_FIELD` is untouched
`HivelogBreadcrumbBuilder` gains its own declarative map, keyed by
collection route name, naming that collection's own parent-crumb
route — independent of `HivelogEntityHierarchy::PARENT_FIELD`, which
stays exactly as it is:

| Collection route | Parent crumb route |
|---|---|
| `entity.hive.collection` | `entity.apiary.collection` |
| `entity.hive_inspection.collection` | `entity.hive.collection` |
| `entity.queen.collection` | `entity.hive.collection` |
| `entity.queen_observation.collection` | `entity.queen.collection` |
| `entity.inventory_item.collection` | `entity.apiary.collection` |
| `entity.inventory_purchase.collection` | `entity.inventory_item.collection` |
| `entity.product.collection` | `entity.apiary.collection` |
| `entity.api_client.collection` | `hivelog.insights` |
| `entity.ai_provider_config.collection` | `hivelog.insights` |
| `entity.sensor_device.collection` | `hivelog.insights` |

`build()` walks this map from the current collection route to its
root (`entity.apiary.collection` or `hivelog.insights`, neither of
which appears as a key — the walk terminates there naturally),
threading one crumb per ancestor in root-to-leaf order before the
current route's own terminal crumb — the same "declarative map, walk
to root" shape `addAncestryLinks()` already uses for per-instance
pages, applied to collection routes instead of entity instances.
`calendar_action`/`hive_action_log`/`apiary_action_log`'s own
collection breadcrumbs, and the combined financial report, are **not**
in this map and stay exactly as flat as they are today — the request
never named them, and inventing a chain for them isn't this ADR's call
to make.

**Why a separate map instead of extending `PARENT_FIELD` itself:**
`PARENT_FIELD` is read by three consumers today — the breadcrumb's own
per-instance ancestor walk, `resolveSubject()`'s active-nav-section
resolution, and (by cross-reference, not code) the delete-dependency
registry's own treatment table. Changing what `PARENT_FIELD['inventory_purchase']`
*means* to make this request's `Inventory → Purchases` collection
chain work would also silently deepen every real purchase's own
canonical-page breadcrumb (`Apiaries › [Apiary] › [Purchase]` today)
into a five-level trail through its specific inventory item — a much
bigger, unrequested behavioural change to already-correct, tested
production breadcrumbs. The request only ever named *types*, never an
instance; a second, purpose-built map keeps the two concerns (which
type conceptually nests under which, for a collection listing) and
(which specific record is this instance's real parent, for a canonical
page) independently correct instead of conflating them.

## Consequences
- Positive:
  - Collection-page breadcrumbs finally match what the nav strip
    already shows for the same relationships, closing the exact gap
    the user pointed at.
  - Per-instance breadcrumbs (canonical/edit/delete) are provably
    unaffected — no existing, correct, tested behaviour there changes.
  - The rename is clean (no compatibility shim needed) since nothing
    in this nav work has shipped in a tagged release yet.
- Negative / trade-offs:
  - `HivelogBreadcrumbBuilderTest.php`'s `leafPageProvider()` loses 10
    of its current members (every collection route this ADR threads)
    to a new, deeper-asserting provider — a real rewrite, not a patch,
    mirroring exactly the kind of test-shape change task 0148 already
    went through for the nav strip itself.
  - Two now-independent declarative maps (`PARENT_FIELD` and this
    one) both describe "what does X's parent look like", for
    different purposes — a future reader must know which one a given
    page type uses. Documented plainly in both `HivelogBreadcrumbBuilder`'s
    own docblock and AGENTS.md (task 0154) to keep that legible.
- Follow-up tasks:
  [[0152-rename-setup-to-insights]],
  [[0153-collection-breadcrumb-ancestor-threading]],
  [[0154-breadcrumb-consistency-tests-and-docs]].

## Open questions
- **Should `calendar_action`/`hive_action_log`/`apiary_action_log`
  eventually get an equivalent ancestor chain too** (e.g.
  `Apiaries › Calendar Actions`)? Deliberately left flat here — the
  request never named them, and the calendar-action breadcrumb already
  has its own, different, well-established threading rule (task 0122:
  `calendar_action` always threads its own apiary-scoped Calendar page
  wherever it appears in a per-instance trail) that a collection-level
  chain would need to be reconciled with, not just copied. A genuine
  follow-up if wanted, not a gap in this ADR.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0104-two-tier-in-app-navigation]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Tasks:: [[0146-setup-landing-page-and-route]],
  [[0152-rename-setup-to-insights]],
  [[0153-collection-breadcrumb-ancestor-threading]],
  [[0154-breadcrumb-consistency-tests-and-docs]]
