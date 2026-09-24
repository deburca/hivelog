---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[page-structure-consistency]]"
area: theme
created: 2026-09-23
branch: feature/0128-page-heading-hierarchy
release:
depends-on:
blocked-by:
---
# Task: Consistent heading hierarchy on every page

## Context
From the page-structure review of 2026-09-23, with heading outlines
taken from the live `cms2` pages. The page title is the theme's H1.
Below it:

| Page | Outline today |
|---|---|
| All 9 detail pages (Inspection, Queen, …) | H1 → **H3** sections (H2 skipped) |
| Apiary | H1 → **H3** (Weather, Hives, Seasonal Calendar, Inventory, Products) |
| Hive | H1 → **H4** (weights chart) → H3 Queen → H2 Hive Activity → H3 Inspections … |
| Hive Insights | H1 → H2 (AI Insight, Sensors) → H3 per device |
| Dashboard | H1 → H2 |
| API client | H1 → H3 (API endpoint) |

Skipped and out-of-order levels break screen-reader heading navigation
(WCAG 2.2 SC 1.3.1 / 2.4.6 good practice), and themes can't style
"section heading" reliably when its level varies by page.

## Acceptance criteria
- [x] Rule documented in AGENTS.md (CSS and components): **page title
      = H1 (theme); a page's top-level sections = H2; subsections
      inside a section = H3; nothing deeper without a parent level.**
- [x] Every detail-page `buildSection()` emits H2. If
      [[0125-shared-detail-page-builder]] has landed, that's one change.
- [x] Apiary and Hive pages restructured to the rule. On the Hive page
      specifically: the weights chart gets an H2 (or joins a section),
      Queen is H2, and "Hive Activity" is either a real H2 wrapping
      H3 sub-lists or removed so its lists become H2s. Pick whichever
      matches the visual grouping.
- [x] `hivelog-list-heading` titles (embedded list headings) follow the
      rule for their context: H2 at page top level, H3 when nested.
- [x] Submodule pages (sensor device, API client, AI provider config,
      Insights panels via `hook_hivelog_*` panel builders) follow it.
      Panel builders receive or assume the correct level, not a
      hard-coded one.
- [x] CSS that targets `h3` inside hivelog sections (e.g.
      `collective.api-client.css`, `hivelog.tables.css`) is updated so
      the visual appearance doesn't change unintentionally.
- [x] Verified by re-running the heading outline on all canonical,
      collection and sub-pages on `cms2`: no skipped levels.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
**Implemented 2026-09-24.**
- **0125 had already landed** (`HivelogDetailPageTrait::buildSection()`),
  so the 9 shared detail pages were a single change: `buildSection()`
  and `buildPhotosGrid()`'s headings, h3 → h2. Every controller that
  adds its *own* extra top-level section beyond the trait's — Queen's
  Observations, InventoryItem's Stock on Hand, CalendarAction's
  Required Items/Expected Yield — needed its own matching h3 → h2 edit,
  since those are siblings of the trait's sections, not nested under
  them.
- **The Apiary/Hive page table in this task's own Context section was
  already partly stale** by the time this task started — nanoprobe's
  apiary-page Sensors panel had already dropped its heading entirely
  (a same-day, unrelated change per `SensorPanelBuilder::buildApiaryPanel()`'s
  own docblock), and "Weather" no longer exists as a section at all.
  Worked from the *live* page structure instead of the table, via a
  full grep of every `'#tag' => 'h[1-6]'` occurrence across `src/` and
  every submodule's `src/` (13 files) rather than trusting the task's
  own review notes.
- **Two sections were already correct and needed no change**: the Hive
  page's "Hive Activity" was already H2 (a prior task had gotten this
  right), with Inspections/Queen Observations correctly nested as H3
  sub-lists under it — matching exactly the "H2 wrapping H3 sub-lists"
  option this task's own text offered, so nothing to pick between.
  Comments added at both nested h3 sites explaining why they
  deliberately stay h3, so a future pass doesn't "fix" them by mistake.
  Likewise, nanoprobe's `SensorPanelBuilder` (Sensors H2 → per-device
  H3) and nexus's `HiveInsightPanelBuilder`/`DashboardAiInsightsBuilder`
  (AI Insight H2) were already compliant on every page they render on —
  confirmed by tracing each call site (`hook_hivelog_apiary_view_panels()`,
  `hook_hivelog_hive_insights_panels()`, the Dashboard) rather than
  assuming from the class name alone.
- **"Panel builders receive or assume the correct level, not a
  hard-coded one" (AC5) resolved as "assume," not "receive."** Every
  `hook_hivelog_*_panels()` injection point only ever contributes a
  top-level section to whichever page invokes it — there is no call
  site anywhere that nests a panel inside another section — so a
  `$level` parameter threaded through every hook signature would be
  dead API surface. Documented this reasoning in AGENTS.md's new
  "Heading hierarchy" subsection rather than adding an unused
  parameter.
- **The weight histogram's heading moved from H4 straight to H2** (not
  H3), per the task's own "gets an H2 (or joins a section)" option —
  it's a standalone top-level hive-page section (own `#weight => 7`),
  not nested under anything. The `.hivelog-weight-histogram__title`
  class (not the tag) is what actually drives its visual size, so nothing
  in `css/hivelog.weight-histogram.css` needed touching.
- **CSS audit**: grepped every `.css` file in the module for `h3`/`h4`
  selectors — found exactly two real tag-specific rules needing an
  update: `.hivelog-notice--critical h3` / `.hivelog-notice--warning h3`
  (`css/hivelog.notices.css`, shared by the delete-form dependency
  sections and by `collective`'s API-client page) and
  `.collective-api-client-endpoints h3`
  (`modules/collective/css/collective.api-client.css`) — both changed
  to `h2`, both keep their existing explicit `font-size: 1rem`, so
  their rendered size is unchanged. This task's own text also named
  `hivelog.tables.css` as an example; grepped it directly and it has no
  heading-tag selectors at all (nothing to change there — the example
  in the task text didn't hold up against the real file).
  `.hivelog-list-heading__title` and `.hivelog-activity-section-title`
  (used by every other newly-h2 heading in this task — Hives, Seasonal
  Calendar, Queen, Observations, Required Items, Expected Yield,
  Stock on Hand, Pictures, the report headings, API endpoint's sibling
  summary table) have never had an explicit `font-size` — they've
  always taken the browser/theme's own tag-default size, exactly like
  the pre-existing "Hive Activity" H2 already did. Left them alone
  deliberately: adding an override now would be pinning these peer
  sections to look *smaller* than "Hive Activity," a new inconsistency
  the task's own "matches the visual grouping" spirit argues against,
  not a regression to prevent.
- **No kernel test assertions needed updating.** Grepped the whole test
  suite for `'h3'`/`'h4'`/`<h3`/`<h4` — nothing asserts on a specific
  heading tag level anywhere; tests that touch these render arrays
  check text content or CSS classes, not the tag.
- **Beeswax (the reference theme, `deburca/beeswax`) is out of reach
  from this repo** — a separate repository, not checked out here. Its
  `src/hivelog.css` may have its own `h3`-specific selectors targeting
  `.hivelog-list-heading__title`/`.hivelog-notice--*`/etc. that would
  now silently stop matching. **Flagged, not fixed** — needs a
  follow-up check (and likely release) in that repo; recorded here
  since this task can't do it from inside `hivelog`.
- **Verified live on `cms2`**: fetched the rendered heading outline
  (`<h1>`–`<h6>`, tag + text) for the apiary, hive, queen,
  calendar-action, inventory-item and product canonical pages, the
  apiary delete-blocked page, the sensor-device readings page, the API
  client page and the hive Insights page. Every one now reads H1 → flat
  H2 sections, with only the two documented H3 nestings (Inspections/
  Queen Observations under Hive Activity; per-device under Sensors) —
  no skipped or out-of-order level anywhere. (The in-app browser's
  sandbox blocks this dev site's CSS requests outright — confirmed via
  its own network log — so visual/computed-style verification used
  `getComputedStyle()` against the raw HTML instead of a screenshot.)
- **Verification**: full `hivelog` suite (701 tests, 11,893 assertions)
  — only the 3 pre-existing, already-documented, unrelated
  `DashboardTest` Functional errors. phpcs clean (0 errors) and phpstan
  introduces no new errors (confirmed by diffing against the
  pre-change tree) on every file this task touched.
- Key files: `src/HivelogDetailPageTrait.php`, `src/Form/
  HivelogEntityDeleteForm.php`, `src/Controller/
  {Apiary,Hive,Queen,CalendarAction,InventoryItem,InventoryReport}Controller.php`,
  `modules/collective/src/Controller/ApiClientController.php`,
  `modules/nanoprobe/src/Controller/SensorDeviceController.php`,
  `css/hivelog.notices.css`, `modules/collective/css/
  collective.api-client.css`, `AGENTS.md` (new "Heading hierarchy"
  subsection).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]]
- Tasks:: [[0125-shared-detail-page-builder]]
- Commits::
