---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: theme
created: 2026-09-08
completed: 2026-09-08
branch: feature/0062-module-themeability-tokens
release: 1.8.3
depends-on: ["[[0061-beeswax-hivelog-skin]]"]
blocked-by:
---
# Task: Module themeability — tokens + page hook for the skin

## Progress
- [x] **Token surface.** `--hivelog-*` surface / ink / line / severity
      properties on `:root` in `css/hivelog.responsive.css` (the base
      library every other one depends on), stock values = the hex they
      replaced. `#e0e0e0` / `#eef0f2` → `--hivelog-hairline` and
      `#b3261e` → `--hivelog-critical` consolidated (sub-perceptual on
      1px rules / small text); noted in the `:root` comment.
- [x] **Conversions.** `css/hivelog.dashboard.css`,
      `css/hivelog.tables.css`, `css/hivelog.filter-form.css`,
      `css/hivelog.forms.css`, `components/stat-tile/stat-tile.css`,
      `components/entity-table/entity-table.css` now consume the tokens.
      `#1a1a1a` on `.hivelog-filter-form label` left literal on purpose
      (deliberate a11y contrast override; the skin still restates that
      one selector). `hivelog.weight-histogram.css` / `.images.css` /
      `.map.css` out of scope — the skin doesn't reskin them.
- [x] **`hivelog_preprocess_html()`.** Adds `hivelog-page` on any route
      whose path is under `/hivelog` (path-match, like the breadcrumb
      builder). phpcs clean; `DashboardTest` + `ListCollectionTitleTest`
      green (40 tests, 1029 assertions).
- [x] **AGENTS.md "Theming HiveLog" section** — stable class names, the
      `--hivelog-*` and `--hivelog-btn-*` token lists, the
      `hivelog-page` body class and the `global`-library-not-`libraries-extend`
      attach seam. Points at beeswax as the reference skin.
- [x] **Stock render check.** Rather than a screenshot diff: resolved
      every `var(--hivelog-*)` in the six converted sheets back through
      the `:root` values and diffed against the pre-change files
      (`1865c2d`). Result — 0 unresolved tokens; exactly 8 changed lines,
      all of them one of the 5 documented consolidations
      (`#b3261e`→`#b91c1c` on the danger count; `#e0e0e0`/`#eef0f2`/`#e5e5e5`
      →`#e5e7eb` on 1px rules; `#fafafa`→`#f9fafb` on the filter panel).
      No structural change; nothing else moves on a stock theme.
- [x] **beeswax dropped its copies** (`deburca/beeswax` `a8d4401`, after
      cms2 pinned hivelog to `1.8.3` in `83af952`):
        1. `ThemeHooks::preprocessHtml()` no longer adds `hivelog-page` —
           `hivelog_preprocess_html()` is the only source now (verified on
           kbg: one `hivelog-page`, not two).
        2. ~40 colour-only selector restatements in `src/hivelog.css`
           replaced by one `body.hivelog-page { --hivelog-surface:
           var(--bw-surface); … }` bridge block (13 tokens). Kept: the
           type rules, layout, additive `:hover` affordances the module
           has none of, Tom Select, vertical-tabs, and the deliberate
           deviations — pine hexagon (vs `--hivelog-warning`), muted
           white week badge, fainter stat-tile label, pine "upcoming"
           week number, forced filter-label colour (over the module's
           `#1a1a1a`). `src/hivelog.css` −122 / +69 lines.

## Context
[[0060-visual-identity-in-site-theme]] keeps the HiveLog look in the
`beeswax` site theme, attached by `libraries-extend` + a `--hivelog-btn-*`
token override. Buttons already theme cleanly because every button colour
is a `:root` custom property ([[0012-action-button-design-system]],
`css/hivelog.responsive.css`). The dashboard, "Needs attention",
stat-tile, split and financial-report CSS is **not** tokenised — colours
are hard-coded hex (e.g. `.hivelog-attention__row` `border-left`,
`.hivelog-dashboard__week` background, `.hivelog-inventory-report-table`
borders). So [[0061-beeswax-hivelog-skin]] has to restate selectors just
to recolour them.

This task closes that gap **after** 0061 has shown which overrides were
actually painful — token surface only, no brand styling in the module
([[0060]] point 4).

## Acceptance criteria
- [x] **Tokenise the non-button HiveLog colours** the 0061 skin had to
      override: add `--hivelog-*` custom properties on `:root` in
      `css/hivelog.responsive.css` (or a dedicated `hivelog.tokens.css`)
      for surface / ground / hairline / ink / ink-muted, the
      critical / warning semantic pair, and the report-table borders;
      point the existing rules in `hivelog.dashboard.css`,
      `hivelog.tables.css`, `components/*/**.css` and the
      inventory-report tables at those tokens. Values unchanged — the
      module still renders identically on a stock theme; a theme now
      recolours by redefining ~a dozen tokens instead of duplicating
      rules.
- [x] **Move the `hivelog-page` body class into the module.**
      `hivelog_preprocess_html()` adds it on every route whose path is
      `/hivelog` or under `/hivelog/` — a path match, because the plain
      `entity.<type>.collection` routes are not named `hivelog.*` (same
      gap `HivelogBreadcrumbBuilder` handles). beeswax dropping its
      theme-side copy is release-gated — see Progress.
- [x] **Document the theming contract.** "Theming HiveLog" section in
      `AGENTS.md` (under "CSS and components"): stable class names, the
      `--hivelog-*` / `--hivelog-btn-*` token lists, the `hivelog-page`
      class, and the "load the skin from the theme's `global` library,
      not `libraries-extend`" seam.
- [x] phpcs clean; `DashboardTest` + `ListCollectionTitleTest` green
      (40 tests, 1029 assertions). No behavioural change.
- [x] No visual change on a stock theme — verified by resolving tokens
      back to hex and diffing against `1865c2d` (see Progress): only the
      5 documented sub-perceptual consolidations move.

## Implementation notes
- Token-only, no brand in the module ([[0060]]).
- `.hivelog-cbr-summary` (in `css/hivelog.buttons.css`) was out of scope
  here — buttons.css wasn't touched. Folded into the token surface by
  [[0070-tokenise-cbr-summary]].
- `css/hivelog.map.css`, `.images.css`, `.weight-histogram.css` are not
  tokenised on purpose — chart / media internals, not themed chrome.

## Related
- Project:: [[hivelog-visual-identity]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0012-action-button-design-system]], [[0009-render-cacheability-discipline]]
- Depends on:: [[0061-beeswax-hivelog-skin]]
- Commits:: `92acc3f` (PR #136, module side, 1.8.3); beeswax `a8d4401`
  (token bridge + drop copy); cms2 `83af952` (pin 1.8.3)
