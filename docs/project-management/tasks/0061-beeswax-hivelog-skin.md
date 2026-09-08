---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[hivelog-visual-identity]]"
area: theme
created: 2026-09-08
branch:
release:
depends-on:
blocked-by:
---
# Task: beeswax theme — HiveLog visual skin

> **Repo:** this work lands in the **cms2 / `verdigris`** project
> (`web/themes/custom/beeswax`), not the hivelog module repo. Only this
> tracking note and [[0062-module-themeability-tokens]] live here.

## Context
[[0060-visual-identity-in-site-theme]]: the HiveLog look lives in a site
theme. `beeswax` (Mercury 1.0.5 starterkit) is that theme for kbg. Make
every HiveLog surface — `/hivelog` dashboard through the financial
reports — match `projects/dashboard-landing-page-mockup.html`: Fraunces /
IBM Plex type, beeswax / pine palette, light + dark.

Mercury starterkit facts:
- Tailwind v4; build with `npm run build` (→ `build/main.min.css`).
- `src/theme.css` — shadcn-style CSS vars on `:root` / `.dark`
  (`--background`, `--foreground`, `--card`, `--primary`, `--muted`,
  `--accent`, `--destructive`, `--border`, `--font-sans/serif/mono/body`,
  `--radius`, shadows). `preprocess: false` — edit without rebuild.
- `src/fonts.css` — `@font-face` block (currently Outfit + Inter,
  self-hosted woff2 in `fonts/`). `preprocess: false`.
- `src/main.css` — Tailwind entry + `@theme` token mapping + custom
  `@layer` rules.
- HiveLog routes are **not** admin routes → they render with the site's
  **default** theme. `beeswax` must become the kbg default.

## Acceptance criteria
- [ ] **Fix location.** Move
      `web/web/themes/custom/beeswax` → `web/themes/custom/beeswax`
      (`dr generate-theme --path` is relative to the Drupal root, so the
      theme was written one level too deep). `ddev drush cr` and confirm
      it appears on `admin/appearance`.
- [ ] **Faces.** Replace Outfit / Inter with **Fraunces** (serif display),
      **IBM Plex Sans** (body / UI), **IBM Plex Mono** (numbers, chips,
      badges), self-hosted woff2 under `fonts/`. Update `src/fonts.css`
      (`@font-face`, `unicode-range`, `font-display: swap`) and the
      `--font-sans` / `--font-serif` / `--font-mono` / `--font-body`
      values in `src/theme.css`. Body = IBM Plex Sans; a heading /
      wordmark utility uses Fraunces.
- [ ] **Palette.** Map the mockup's beeswax / pine values (see the table
      in [[hivelog-visual-identity]]) onto the theme's `:root` and
      `.dark` token blocks — ground/surface/surface-2 → background / card
      / muted, ink → foreground, pine → primary & accent, critical →
      destructive, plus hairline / warning as their own vars. Keep the
      rest of Mercury's scale.
- [ ] **`--hivelog-btn-*` override.** A `:root` block in the skin sheet
      redefining the module's button tokens
      ([[0012-action-button-design-system]]) to the beeswax palette
      (default = surface-2 / ink, primary = pine, danger = critical) so
      every HiveLog button re-skins with no per-selector rules.
- [ ] **HiveLog skin sheet(s).** Plain CSS targeting the module's classes,
      matching the mockup surface by surface:
  - [ ] Dashboard shell + masthead — `.hivelog-dashboard`,
        `.hivelog-masthead__mark` (pine hexagon), `__word` (Fraunces),
        `__sub`; `.hivelog-dashboard__header` right-aligned strip,
        `.hivelog-dashboard__week` (mono badge), `.hivelog-cbr-summary`.
  - [ ] "Needs attention" — `.hivelog-attention` panel + shadow,
        `__header` / `__count` (`--danger` / `--warning`), `__row`
        severity stripes, `__chip` (`--critical` / `--warning`, mono,
        uppercase), `__row-title`, `__ctx`.
  - [ ] Stat tiles — `.hivelog-stat-tiles` hairline grid, the
        `hivelog:stat-tile` SDC (`__value` mono, `__label` uppercase +
        tracking, `__sublabel` `--critical` / `--warning`).
  - [ ] Upcoming / Recent — `.hivelog-dashboard__split`,
        `.hivelog-upcoming__*`, `.hivelog-recent__*` (mono wk / date
        columns, hairline row rules).
  - [ ] Entity tables — the `hivelog:entity-table` SDC and
        `hivelog/tables` detail tables: header emphasis, hairline rows,
        mono numerics, surface-2 header fill.
  - [ ] Financial reports — `hivelog-inventory-report-table` /
        `-breakdown` / `-trend` on both
        `hivelog.apiary.inventory_cost_report` and
        `hivelog.apiaries.financial_report`; the year-selector
        `hivelog:button-group`; the "All apiaries" total row.
  - [ ] Filter forms, list headings, breadcrumb trail.
- [ ] **Wire it up** in `beeswax.info.yml` via `libraries-extend`: one
      beeswax library per module library it decorates (`hivelog/dashboard`,
      `hivelog/tables`, `hivelog/buttons`, `hivelog/forms`,
      `hivelog/filter_form`, `hivelog/activity-columns`). No route
      sniffing.
- [ ] **kbg default.** Set `system.theme` `default: beeswax` (config
      export in the cms2 repo), keep `admin: gin`. `npm run build`,
      `ddev drush cr`.
- [ ] **Verify** every surface in the list above against the mockup on
      `kragebaekgaard.ddev.site`, light and dark, at desktop and ≤768px.
- [ ] Feed anything that needed a module change back to
      [[0062-module-themeability-tokens]] rather than patching the module
      from the theme.

## Implementation notes
- Prefer decorating via `libraries-extend` + token overrides; only write
  a per-selector rule where the module hard-codes a colour that isn't a
  token yet (those are the candidates for [[0062]]).
- The module's `@media` breakpoints are 768 / 480px
  ([[0011-responsive-design-strategy]]); keep the skin on the same
  breakpoints.
- Fonts self-hosted, not Google-hosted — this is a logbook / admin tool
  (offline use, no third-party requests). `woff2` only.
- Keep the skin sheet(s) out of the Tailwind build if practical
  (`preprocess: false`, plain CSS) so palette tweaks don't need `npm run
  build`.

## Related
- Project:: [[hivelog-visual-identity]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0011-responsive-design-strategy]], [[0012-action-button-design-system]], [[0005-sdc-component-library]]
- Follows:: [[0058-dashboard-visual-polish]], [[0059-combined-financial-report]]
- Feeds:: [[0062-module-themeability-tokens]]
- Commits::
