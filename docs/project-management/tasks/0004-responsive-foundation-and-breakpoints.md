---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[mobile-ux-improvements]]"
area: theme
created: 2026-06-17
branch: feature/0004-responsive-foundation-and-breakpoints
release: 1.4.0
---
# Task: Responsive foundation & breakpoints

## Context
The module has **no responsive CSS** — a repo-wide search for `@media` returns
nothing. Before fixing individual pages we need one agreed strategy so the
per-page tasks ([[0005-responsive-entity-list-tables]] →
[[0008-responsive-map-and-image-grid]]) share breakpoints and conventions
instead of each inventing their own. Foundation task for
[[mobile-ux-improvements]].

## Decision to make
The module currently styles things **two ways**: plain CSS in `css/*.css` and
Tailwind/DaisyUI utility classes inside SDC `.twig` files
(`components/*/**.twig`). This task must pick the responsive mechanism:
- **Option A — plain CSS `@media`** in the existing `css/*.css` files. Simple,
  framework-independent, matches `hivelog.buttons.css` / `hivelog.tables.css`.
- **Option B — Tailwind responsive utilities** (`sm:` / `md:` / `lg:`) inside
  the component `.twig` files. Matches `entity-table.twig` / `button.twig`, but
  depends on the host theme's build scanning the component dir.
Recommendation: **Option A** for module-owned layout CSS (predictable without a
theme build), reserving utilities for components already built that way.

## Resolution
**Option A (plain-CSS `@media`)**, per accepted ADR
[[0011-responsive-design-strategy]]. Mechanics settled by this task:
- Canonical breakpoints: `≤480px` phone, `≤768px` small tablet, `>768px`
  desktop, optional `≥1024px` wide.
- Shared vocabulary lives in `css/hivelog.responsive.css` (new
  `hivelog/responsive` library) which the other module libraries depend on. It
  defines `:root` tokens only — no layout rules — so desktop is unaffected.
- Component-specific responsive rules live in each component's own CSS file as
  per-file `@media` blocks using the breakpoints above (tasks 0005–0008), rather
  than one monolithic file.

## Acceptance criteria
- [x] A documented breakpoint set (`≤480px` phone, `≤768px` small tablet,
      `>768px` desktop, optional `≥1024px` wide) — recorded in
      [[0011-responsive-design-strategy]] and the `css/hivelog.responsive.css`
      header.
- [x] A single place for shared responsive rules — new
      `css/hivelog.responsive.css` + `hivelog/responsive` library in
      `hivelog.libraries.yml`; component rules use per-file `@media` blocks
      (documented convention).
- [x] Decision on Option A vs B captured in the ADR and reflected here
      (Option A).
- [x] No visual change on desktop (regression check at `>768px`) — verified on
      the test site via the dev release; foundation adds only `:root` tokens.

## Implementation notes
- Library wiring lives in `hivelog.libraries.yml` (existing libs: `buttons`,
  `tables`, `filter_form`, `images`, `weight_histogram`). A new `responsive`
  library could be added as a dependency of the others, or each file gets its
  own `@media` block.
- The histogram SVG (`css/hivelog.weight-histogram.css`) already uses
  `aspect-ratio` + `viewBox`, so it is mostly fluid; treat it as a reference.

## Dependencies
- Blocks: [[0005-responsive-entity-list-tables]],
  [[0006-responsive-detail-tables]],
  [[0007-responsive-filter-form-and-heading]],
  [[0008-responsive-map-and-image-grid]].

## Related
- Project:: [[mobile-ux-improvements]]
- Decisions:: [[0011-responsive-design-strategy]] (accepted)
- Commits:: 38c2a8e (PR #81)
