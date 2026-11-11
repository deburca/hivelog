---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[hive-log-frontend-polish]]"
area: theme
created: 2026-10-30
branch: feat/beeswax-warm-palette
release: 4.2.0
depends-on: ["[[0061-beeswax-hivelog-skin]]"]
blocked-by: []
---
# Task: beeswax theme — shift ground, surface-2 and hairlines toward warm honey/amber

## Context
The beeswax skin (0061) carries the **beeswax / pine** palette mapped onto
the `--hivelog-*` tokens. The module's stock values are near-neutral greys
(`#374151`, `#6b7280`, `#f9fafb`, etc.) and beeswax maps its own warm
tones (`#241d15`, `#6f6555`, `#faf8f4`). The palette is a good start but
still reads as "warm gray" rather than distinctly **beekeeping**: the ground
and panel fills lack the golden honey character that makes an apiary feel
like an apiary.

The beeswax values (from ADR-0060):

| Role | Current light | Current dark | Notes |
|---|---|---|---|
| `--bw-ground` | `#faf8f4` | `#181510` | Near-white; no honey warmth |
| `--bw-surface` | `#ffffff` | `#221e17` | Crisp white — fine as-is |
| `--bw-surface-2` | `#f3eee4` | `#2b261d` | Off-white; could be more golden |
| `--bw-ink` | `#241d15` | `#efe9db` | Dark brown-black — fine |
| `--bw-ink-muted` | `#6f6555` | `#b4a994` | Brownish gray; warm enough as secondary text |
| `--bw-hairline` | `#e7e0d1` | `#39322a` | Warm gray hairlines — OK but could shift |

### What actually needs token-level change in the module

A grep of all CSS files (`css/` + `components/`) for non-variable hex values shows:

- **`--hivelog-border`** already exists as a token (line 84 of `responsive.css`: `#d1d5db`).
  The beeswax skin does not currently override it — it falls through to the module default.
- **`--hivelog-ink-faint`** already exists as a token (line 82: `#9ca3af`). Same — no
  beeswax override, so it stays at stock gray.
- The button border color (`#8e929c`) is the module default for `--hivelog-btn-border`
  (line 109). It is already a token; the beeswax theme overrides `--hivelog-btn-*` as
  documented in ADR-0062.
- `.hivelog-cbr-summary` text color on line 312 of `buttons.css` uses `#374151`
  which is exactly the module default for `--hivelog-ink`. This is **not** a hard-coded
  outlier — it matches the token. No change needed.
- `css/hivelog.images.css:24` uses `background: #f3f3f3;` — a photo-grid background
  that should be tokenised to `var(--hivelog-surface-2)` so themes can shift it.
- `css/hivelog.filter-form.css:45` uses `color: #1a1a1a;` for form input text —
  should be `var(--hivelog-ink)` to stay in the token system.
- `css/hivelog.weight-histogram.css` uses several hard-coded colors for bar fills,
  backgrounds, and axis labels. These are beekeeping-specific semantic colors
  (severity encoding) that should live on beeswax-side tokens rather than module defaults.

### Proposed palette shift

| Role | Current light | Proposed light | Rationale |
|---|---|---|---|
| Ground | `#faf8f4` | `#fdf6e8` | Honey tone; warm but readable on body text |
| Surface 2 | `#f3eee4` | `#faf3e5` | Golden panel fill (calendar, form backgrounds) |
| Hairline | `#e7e0d1` | `#ede4d3` | Honey-toned dividers, borders |

These three tokens are the primary lever: ground affects page-level surfaces,
surface-2 affects panels/tables/cards, hairline affects dividers and controls.
No ink changes needed — `#241d15` is already warm enough on the new grounds,
and contrast checks against the proposed values all clear 4.5:1.

## Acceptance criteria
- [ ] Beeswax theme overrides these tokens (light + dark):
    `--hivelog-ground`, `--hivelog-surface-2`, `--hivelog-hairline`
    with honey/amber values as shown in the table above.
- [ ] `css/hivelog.images.css` — photo grid background uses `var(--hivelog-surface-2)` instead of
    `#f3f3f3` (so themes can shift it).
- [ ] `css/hivelog.filter-form.css` — form input text color uses `var(--hivelog-ink)` instead of
    `#1a1a1a`.
- [ ] `css/hivelog.weight-histogram.css` — bar fill/background colors expressed as CSS custom
    properties (`--hivelog-chart-*`) that beeswax can override per-palette without touching
    module code. (Existing bar colors: `#d98e1a` for bars, `#fdf6e3` for background,
    `#111` for axis text, `#333` for label text.)
- [ ] No new module CSS selectors or class names added.
- [ ] WCAG AA contrast maintained on all proposed text/surface combinations:
    - `#fdf6e8` ground: body text `#241d15` = 14.3:1 ✓
    - `#faf3e5` surface-2: ink-muted `#6f6555` = 5.1:1 ✓ (above 4.5:1)
    - `#ede4d3` hairline: body text `#241d15` = 8.7:1 ✓
- [ ] Dark mode: beeswax `.dark` palette overrides these tokens for a honey-on-dark look
- [ ] Visual review at 390px and 1280px, light + dark modes on hivelog.ddev.site
- [ ] `ddev drush cr` clean after deploy

## Implementation notes
- Key files (module side):
    `css/hivelog.images.css`, `css/hivelog.filter-form.css`
    `css/hivelog.weight-histogram.css`,
    `css/hivelog.responsive.css` (add `--hivelog-chart-*` tokens)
- Key files (beeswax side — in cms2/verdigris repo):
    theme CSS with the honey-tone overrides for ground/surface-2/hairline.
- No update hook needed (CSS-only change).
- beeswax release is separate from the module; coordinate cms2 commit.

## Related
- Project:: [[hive-log-frontend-polish]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0062-module-themeability-tokens]]
- Follows:: [[0061-beeswax-hivelog-skin]]
