---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] Rule documented in AGENTS.md (CSS and components): **page title
      = H1 (theme); a page's top-level sections = H2; subsections
      inside a section = H3; nothing deeper without a parent level.**
- [ ] Every detail-page `buildSection()` emits H2. If
      [[0125-shared-detail-page-builder]] has landed, that's one change.
- [ ] Apiary and Hive pages restructured to the rule. On the Hive page
      specifically: the weights chart gets an H2 (or joins a section),
      Queen is H2, and "Hive Activity" is either a real H2 wrapping
      H3 sub-lists or removed so its lists become H2s. Pick whichever
      matches the visual grouping.
- [ ] `hivelog-list-heading` titles (embedded list headings) follow the
      rule for their context: H2 at page top level, H3 when nested.
- [ ] Submodule pages (sensor device, API client, AI provider config,
      Insights panels via `hook_hivelog_*` panel builders) follow it.
      Panel builders receive or assume the correct level, not a
      hard-coded one.
- [ ] CSS that targets `h3` inside hivelog sections (e.g.
      `collective.api-client.css`, `hivelog.tables.css`) is updated so
      the visual appearance doesn't change unintentionally.
- [ ] Verified by re-running the heading outline on all canonical,
      collection and sub-pages on `cms2`: no skipped levels.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Kernel tests asserting on `#tag => 'h3'` for section headings need
  updating. `grep -rn "'h3'" tests modules/*/tests`.
- Beeswax styles hivelog headings too. Check
  `deburca/beeswax`'s `src/hivelog.css` for `h3`-specific selectors and
  coordinate a beeswax release if needed (AGENTS.md "Theming HiveLog").

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0060-visual-identity-in-site-theme]]
- Tasks:: [[0125-shared-detail-page-builder]]
- Commits::
