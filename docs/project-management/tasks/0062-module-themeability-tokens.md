---
type: task
tags: [hivelog/task]
status: todo
priority: low
project: "[[hivelog-visual-identity]]"
area: theme
created: 2026-09-08
branch:
release:
depends-on: ["[[0061-beeswax-hivelog-skin]]"]
blocked-by:
---
# Task: Module themeability — tokens + page hook for the skin

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
- [ ] **Tokenise the non-button HiveLog colours** the 0061 skin had to
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
- [ ] **Move the `hivelog-page` body class into the module.** 0061
      needed it (the plain `EntityListBuilder` collections carry no
      `.hivelog-*` hook) and added it *theme-side* in
      `beeswax_preprocess_html()`, matched on route path `/hivelog…`.
      Reproducing it in every skin theme is duplication — a
      `hook_preprocess_html()` in the module adding `hivelog-page` on a
      `hivelog.` route-name match **plus** the `entity.*.collection`
      routes whose path is under `/hivelog` (route name is not
      `hivelog.*` there — same gap `HivelogBreadcrumbBuilder` has). Then
      beeswax drops its copy. Low urgency: the theme-side class works.
- [ ] **Document the theming contract.** A short "Theming HiveLog"
      section in `CLAUDE.md` / `AGENTS.md` (or an ADR note): the stable
      class names, the token list, and the `libraries-extend` seam, so a
      future theme (or a module refactor) knows the supported surface.
- [ ] phpcs clean; kernel tests still green (no behavioural change).
- [ ] No visual change on a stock theme — diff the rendered dashboard /
      a list page / a report before and after.

## Implementation notes
- Keep it minimal and token-only. Anything that looks like "the module
  now has a brand" is out of scope by [[0060]].
- If 0061 ends up needing nothing here, close this as *wontfix* with a
  one-line note rather than inventing work.

## Related
- Project:: [[hivelog-visual-identity]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0012-action-button-design-system]], [[0009-render-cacheability-discipline]]
- Depends on:: [[0061-beeswax-hivelog-skin]]
- Commits::
