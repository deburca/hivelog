---
type: project
tags: [hivelog/project]
status: backlog
target:
created: 2026-10-30
completed:
---
# Project: HiveLog frontend polish

## Goal
Raise the visual ceiling of HiveLog's web interface from "functional admin" to an experience
that beekeepers recognize as **theirs**. This means moving beyond neutral grays and generic
rounded cards toward a warm, apiary-specific aesthetic (in the beeswax theme) while making the
operational surfaces — the hive page, inspection history, sensor data — genuinely useful at a
glance.

This project is about **refinement, not rewrite**. The module's CSS architecture, SDC component
library, responsive strategy, and token surface are all solid (see 0060, 0062). The work here
is: make the beeswax palette warmer and more distinctive, improve data density on dense tables,
restructure the hive page as an operational dashboard rather than a form, and turn empty states
from "nothing found" messages into invitations to act.

## Scope
- **0250** — Warm palette in beeswax (honey/amber ground, warmer grays, new `--border` /
  `--ink-faint` tokens)
- **0251** — Inspection list visual grouping by date, better empty states
- **0252** — Weight histogram and sensor chart improvements (severity encoding, baselines, emphasis)
- **0253** — Canonical hive page restructure (severity banner → stat tiles → operational context → condensed details → activity)
- **0254** — Empty states across all surfaces (guidance + CTA for every zero-data moment)

## Design principles for this project
1. **The beeswax palette should feel warm, not neutral.** Honey tones on ground and surface-2
   are the single biggest visual change. Beekeeping is tactile; the UI should echo that warmth.
2. **Data density ≠ clutter.** Grouping inspections by date, wider columns with tabular numerals,
   empty-cell placeholders ("—"), severity-encoded bars — all improve scanning without adding noise.
3. **The hive page is an operational overview, not a form.** Beekeepers visit the hive page to
   answer "Is everything okay?" and "What do I need to do?" before they fill in data. Structure
   should reflect that priority.
4. **Empty states guide; they don't just report.** Every zero-data moment is an opportunity for
   direction — a single CTA, a sentence of context, nothing else.

## Out of scope
- Dark mode implementation (handled by beeswax's `.dark` palette in 0061)
- Mobile app / Vinculum UI (separate project)
- Font face changes beyond the current Fraunces / IBM Plex pairing
- New entity types or API endpoints
- CMS-level theming framework (Drupal libraries, `hivelog-page` body class) — these are
  infrastructure; beeswax is where the identity lives

## Related decisions
- [[0060-visual-identity-in-site-theme]] — identity in a theme, not the module
- [[0062-module-themeability-tokens]] — token surface for the dashboard, tables, reports
- [[0011-responsive-design-strategy]] — breakpoints, responsive strategy
