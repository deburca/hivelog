---
type: decision
tags: [hivelog/decision]
date: 2026-09-08
supersedes:
---
# ADR-0060: HiveLog visual identity ships in a site theme, not the module

## Status
accepted

## Context
`projects/dashboard-landing-page-mockup.html` proposed a distinct look
for HiveLog — Fraunces / IBM Plex typography, a beeswax / pine-green
palette, lifted surfaces with hairline dividers. The dashboard
([[dashboard-landing-page]], [[0056-dashboard-landing-page]]) shipped
**without** it, and the follow-up review ([[0058-dashboard-visual-polish]])
confirmed that was right: the module carries structure and its own small
hex palette only, and inherits the active theme's typography
([[0011-responsive-design-strategy]], [[0012-action-button-design-system]]).
Both the project note and 0058 flagged "a distinct visual identity would
be its own task + an ADR note" — this is that note.

The kbg site now has a `beeswax` theme (a Mercury 1.0.5 starterkit,
`dr generate-theme beeswax --starterkit=mercury`) intended to carry the
identity. The question this ADR settles: **where does the HiveLog look
live** — in the module, or in a theme?

Forces:

- HiveLog routes (`/hivelog…`) are **not** admin routes, so they render
  with the site's *default* (front-end) theme, not Gin.
- The module's button appearance already flows entirely through
  `--hivelog-btn-*` custom properties on `:root`
  ([[0012-action-button-design-system]]); a theme that redefines those
  tokens re-skins every button with no module change.
- The module's CSS uses hard-coded hex for most non-button colours, so a
  theme skin restates a handful of `.hivelog-*` selectors to recolour
  them; the `--hivelog-btn-*` token layer already spares it the buttons.
- The module is distributed on its own (`git@github.com:deburca/hivelog`)
  and installed on sites other than kbg; those sites must keep working on
  their own themes with no beeswax assets.
- Fraunces / IBM Plex are ~5 font files; the beeswax / pine palette is a
  site aesthetic choice, not a functional requirement of the module.

## Decision
1. **The HiveLog visual identity is a theme concern.** The module ships
   no fonts, no palette beyond its existing functional hex values, and no
   "brand" styling. This reaffirms [[0011-responsive-design-strategy]] /
   [[0012-action-button-design-system]] rather than amending them.
2. **`beeswax` is the identity theme for kbg.** It provides the Fraunces
   / IBM Plex faces and the beeswax / pine palette (light + dark), and a
   **HiveLog skin** that covers every module surface — dashboard, list
   pages, canonical pages, financial reports — to match the mockup.
3. **The skin loads from the theme's `global` library**, as a single
   plain-CSS sheet of `.hivelog-*`-scoped rules plus a `--hivelog-btn-*`
   token override. It sits in the `theme` CSS group, so it always lands
   after the module's own `component`-group CSS. No
   `hook_page_attachments` route-sniffing.

   > Originally specified as `libraries-extend` over each module CSS
   > library. In practice `libraries-extend` only fires for a library a
   > controller **attaches directly** — the dashboard attaches
   > `hivelog/dashboard`, so it worked there, but the entity-list pages
   > pull hivelog's CSS only transitively through the
   > `hivelog:entity-table` SDC's dependencies, which `libraries-extend`
   > does not reach. Loading globally is reliable; the `.hivelog-*`
   > scoping keeps it inert on non-HiveLog pages.
4. **The module may add themeability affordances** — but only token
   surface, never brand styling: tokenising the dashboard / report /
   attention colours the way buttons are already tokenised, and at most a
   stable page-level class for the theme to scope against. Tracked as
   [[0062-module-themeability-tokens]], scoped by what
   [[0061-beeswax-hivelog-skin]] actually needs.
5. **Other installs are unaffected.** With no beeswax theme, the module
   renders exactly as it does today on any theme. The skin is additive
   and lives entirely in the `verdigris` (cms2) repo.

## Consequences
- Positive:
  - The module stays portable and theme-neutral; the identity is opt-in
    per site by installing/enabling a theme.
  - A single global sheet + token overrides mean most of the skin is CSS
    against existing classes — little or no module change.
  - kbg gets one coherent look across every HiveLog page, including the
    financial reports, without the module taking on font hosting or a
    brand palette.
- Negative / trade-offs:
  - The skin duplicates selector structure from the module's CSS wherever
    a colour isn't tokenised yet; [[0062-module-themeability-tokens]]
    reduces but will not eliminate that.
  - Two repos move together for this feature — a module class rename now
    has to be mirrored in the beeswax skin. Mitigated by keeping the skin
    to documented, stable class names and by [[0062]]'s token layer.
  - The identity is only present where `beeswax` (or another skin theme)
    is enabled; a site on a stock theme sees the plain module.
- Follow-up: [[0061-beeswax-hivelog-skin]] (the theme work),
  [[0062-module-themeability-tokens]] (module token surface), both under
  [[hivelog-visual-identity]].
