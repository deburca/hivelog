---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[dashboard-landing-page]]"
area: theme
created: 2026-09-08
branch: feature/0058-dashboard-visual-polish
release:
depends-on: ["[[0056-dashboard-landing-page]]"]
blocked-by:
---
# Task: Dashboard visual polish (post-review)

## Context
Hands-on review of the shipped dashboard against
`projects/dashboard-landing-page-mockup.html` (on a plain admin theme,
Mercury) surfaced five gaps between the mockup's layout and the live
module. The module stays theme-neutral on typography and palette by
design (ADR-0011/0012, no font build) — these fixes are structure and the
module's own existing hex palette only, no new tokens or fonts.

## Acceptance criteria
- [x] **Masthead.** A hexagon mark + "HiveLog" wordmark in the page-title
      `<h1>` (via a new `DashboardController::title()` returning
      `Markup`, wired through `_title_callback` on `hivelog.dashboard`),
      with an "Apiary and hive logbook" subtitle line rendered by
      `view()` just below (`.hivelog-masthead__sub`, weight -30).
- [x] **Header strip right-aligned.** `.hivelog-dashboard__header` gets
      `justify-content: flex-end`; the CBR line sizes to content
      (`flex: 0 1 auto`, `max-width: 32rem`) instead of growing, so the
      week badge + CBR sit opposite the masthead.
- [x] **"Needs attention" count.** Right-aligned (`margin-left: auto` +
      `justify-content: space-between` on the header) and coloured — a new
      `--danger` / `--warning` variant class on the count span (danger
      `#b3261e` when any row is overdue, warning `#a4630a` otherwise).
- [x] **Row action buttons render as buttons.** `css/hivelog.buttons.css`
      only styles `.button` inside eight named context wrappers
      (ADR-0012); added `.hivelog-attention__row`,
      `.hivelog-dashboard__section-head` and
      `.hivelog-dashboard__welcome-actions` to every `:is()` list (and
      the header comment) so "Report done" / "Add purchase" / "Add
      Apiary" / the welcome CTA get the token styling.
- [x] **Apiaries table themed.** `hivelog:entity-table` had no desktop
      styling of its own (the SDC relied on a theme's Tailwind/DaisyUI
      build). Added a low-specificity baseline to
      `components/entity-table/entity-table.css` — width, border-collapse,
      cell padding + `border-bottom`, header emphasis — matching
      `hivelog.tables.css`'s detail-table look. **Affects every entity
      list page**, not just the dashboard; a framework theme layers its
      own `.table` rules on top.
- [x] Tests: `DashboardTest` gains `testTitleMarkup`,
      `testMastheadSubtitle`, and count-variant assertions on the
      overdue / due tests. phpcs clean.

## Implementation notes
- Key files: `hivelog.routing.yml`, `src/Controller/DashboardController.php`,
  `css/hivelog.dashboard.css`, `css/hivelog.buttons.css`,
  `components/entity-table/entity-table.css`,
  `tests/src/Kernel/DashboardTest.php`.
- Not addressed here (deliberate): the mockup's Fraunces / IBM Plex fonts
  and beeswax / pine-green palette — the module inherits the active
  theme's typography and keeps to its own hex palette. A distinct visual
  identity for the dashboard would be its own task + an ADR note.
- `hivelog.info.yml` is still `1.7.2`; a version bump + tag is a separate
  release step.

## Related
- Project:: [[dashboard-landing-page]]
- Decisions:: [[0057-dashboard-information-architecture]], [[0011-responsive-design-strategy]], [[0012-action-button-design-system]], [[0005-sdc-component-library]]
- Commits::
