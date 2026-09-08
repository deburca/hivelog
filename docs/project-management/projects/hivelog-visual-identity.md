---
type: project
tags: [hivelog/project]
status: done
target:
created: 2026-09-08
completed: 2026-09-08
---
# Project: HiveLog visual identity (themed presentation)

## Outcome
Done. Every HiveLog surface on kbg — `/hivelog` dashboard through the
financial reports, list pages, canonical pages and entity edit forms —
now carries the Fraunces / IBM Plex + beeswax / pine identity, light and
dark, realised entirely in the **beeswax** site theme
(`drupal/beeswax`, kbg default via `config/kbg/sync/system.theme.yml`).

- **[[0061-beeswax-hivelog-skin]]** — the theme: self-hosted Fraunces /
  IBM Plex Sans / IBM Plex Mono; the `--bw-*` palette on `:root` / `.dark`
  in `src/theme.css`; `src/hivelog.css` skin loaded from the theme's
  `global` library (not `libraries-extend` — it doesn't reach the module
  CSS the entity-list SDCs pull in transitively); `--hivelog-btn-*` token
  override; Tom Select filter selects; vertical-tabs skin;
  `ThemeHooks` scoping.
- **[[0062-module-themeability-tokens]]** — the module (hivelog 1.8.3):
  a `--hivelog-*` surface / ink / line / severity token surface on
  `:root` (values unchanged on a stock theme), consumed by the
  dashboard / tables / filter-form / forms / stat-tile / entity-table
  CSS; `hivelog_preprocess_html()` adds the `hivelog-page` body class on
  every `/hivelog…` route. beeswax then collapsed its ~40 colour-only
  restatements to a single `body.hivelog-page { --hivelog-*: var(--bw-*) }`
  bridge block and dropped its own body-class copy. "Theming HiveLog"
  section added to `AGENTS.md`.

Carried forward (small, not blocking): `.hivelog-cbr-summary` in
`css/hivelog.buttons.css` still hard-codes two greys — fold into the
token surface next time buttons.css is touched.

## Goal
Give the HiveLog UI on the kbg site a single, coherent visual identity —
the Fraunces / IBM Plex typography and beeswax / pine-green palette from
`projects/dashboard-landing-page-mockup.html` — applied consistently from
the `/hivelog` dashboard down through every list page, canonical page,
and the financial reports. The identity is realised in a **site theme**
(`beeswax`, a Mercury starterkit in the cms2 / `verdigris` project), not
in the module: the module stays theme-neutral by design
([[0011-responsive-design-strategy]], [[0012-action-button-design-system]])
and the mockup's look has always been "the mockup's own proposal", not
what the module ships.

## Why now
The dashboard shipped ([[dashboard-landing-page]]) deliberately without
the mockup's fonts/palette; review confirmed that was the right call for
the module but left the live pages looking generic on whatever admin
theme is active. `beeswax` was generated (`dr generate-theme beeswax
--starterkit=mercury`) to carry the identity. This project wires it up.

## Design reference
`projects/dashboard-landing-page-mockup.html` — the target look, now
synced to the shipped structure (PR #127). Palette and type tokens:

| Role | Light | Dark | Mockup var |
|---|---|---|---|
| Ground | `#faf8f4` | `#181510` | `--ground` |
| Surface | `#ffffff` | `#221e17` | `--surface` |
| Surface 2 | `#f3eee4` | `#2b261d` | `--surface-2` |
| Ink | `#241d15` | `#efe9db` | `--ink` |
| Ink muted | `#6f6555` | `#b4a994` | `--ink-muted` |
| Hairline | `#e7e0d1` | `#39322a` | `--hairline` |
| Accent (pine) | `#2f6b4f` | `#63b389` | `--accent` |
| Critical | `#b3261e` | `#e2685f` | `--critical` |
| Warning | `#a4630a` | `#d99a3c` | `--warning` |

- Display / wordmark / section headings: **Fraunces** (serif).
- Body / UI text: **IBM Plex Sans**.
- Numbers, badges, chips, week labels: **IBM Plex Mono**
  (`font-variant-numeric: tabular-nums`).

## Surfaces to cover
Every page that renders module markup, on the site's **default** theme
(HiveLog routes are not admin routes — they use the front-end theme, not
Gin):

- `/hivelog` dashboard — masthead, header strip, "Needs attention"
  (stripes / chips / count), stat tiles, Upcoming / Recent split.
- `/hivelog/apiaries` and every other entity collection — the
  `hivelog:entity-table` SDC, filter forms, list headings, row buttons.
- Canonical pages (apiary, hive, inspection, queen, …) — headings,
  detail tables (`hivelog/tables`), action button clusters, breadcrumbs.
- Financial reports — `hivelog.apiary.inventory_cost_report` and the
  combined `hivelog.apiaries.financial_report`
  ([[0059-combined-financial-report]]): year selector button group,
  summary / breakdown / trend tables (`hivelog-inventory-report-table`).
- Buttons everywhere — driven by the `--hivelog-btn-*` tokens on `:root`
  ([[0012-action-button-design-system]]), so a token override re-skins
  all of them at once.

## Scope
- **In scope**
  - `beeswax` theme: Fraunces / IBM Plex faces (self-hosted woff2), the
    beeswax / pine palette mapped onto the theme's design tokens, and a
    HiveLog skin (`src/hivelog.css`) that covers every surface above —
    loaded from the theme's `global` library (`.hivelog-*`-scoped, in the
    `theme` CSS group so it lands after the module's CSS) plus a
    `--hivelog-btn-*` token override. Light + dark. (`libraries-extend`
    was tried but does not reach libraries pulled in only transitively,
    e.g. via the `hivelog:entity-table` SDC — see
    [[0060-visual-identity-in-site-theme]].)
  - Set `beeswax` as the kbg site's default theme; verify against the
    running site.
  - Module-side themeability only where the skin genuinely needs it —
    tokenising the dashboard / report / attention colours the way buttons
    are already tokenised, and any page-level hook the theme requires.
- **Out of scope**
  - Any change to module markup or class names beyond what
    [[0062-module-themeability-tokens]] identifies as necessary.
  - Restyling non-HiveLog pages of the kbg site (the rest of `beeswax`
    is Mercury's defaults; this project only touches the HiveLog skin
    and the shared palette/type tokens it rides on).
  - Shipping the identity *in the module* — explicitly rejected, see
    [[0060-visual-identity-in-site-theme]].
  - A dark-mode audit of module markup beyond mapping the mockup's dark
    palette values.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```

- [[0061-beeswax-hivelog-skin]] — the theme work (cms2 / `verdigris`
  repo): faces, palette tokens, the HiveLog skin, wiring, kbg default.
- [[0062-module-themeability-tokens]] — module work (this repo): tokenise
  dashboard / report / attention colours and add any page-level hook the
  skin needs, so `beeswax` overrides tokens instead of duplicating
  selectors. Scope driven by what 0061 finds.

## Decisions
- [[0060-visual-identity-in-site-theme]] — the identity lives in a site
  theme, not the module; `beeswax` is that theme for kbg.

## Notes
- The generated theme currently sits at
  `cms2/web/web/themes/custom/beeswax` (a doubled `web/` — `dr
  generate-theme --path` is relative to the Drupal root, so `themes/custom`
  was the right argument). It must be moved to
  `cms2/web/themes/custom/beeswax` before it can be enabled — first step
  of [[0061-beeswax-hivelog-skin]].

## Related decisions
- [[0011-responsive-design-strategy]] / [[0012-action-button-design-system]]
  (the module stays theme-neutral; this project realises the mockup's
  look in the theme layer, not against those ADRs)
- [[0057-dashboard-information-architecture]] / [[dashboard-landing-page]]
  (the surfaces being skinned)
- [[0059-combined-financial-report]] (the financial-report surface)
