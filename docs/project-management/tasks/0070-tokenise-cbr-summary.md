---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[hivelog-visual-identity]]"
area: theme
created: 2026-09-08
completed: 2026-09-08
branch: feature/0070-tokenise-cbr-summary
release: 1.8.4
depends-on: ["[[0062-module-themeability-tokens]]"]
blocked-by:
---
# Task: Tokenise the CBR summary banner

## Context
Carry-forward from [[0062-module-themeability-tokens]]. That pass lifted
the non-button HiveLog colours to `--hivelog-*` custom properties on
`:root` in `css/hivelog.responsive.css`, but `css/hivelog.buttons.css`
was out of scope, so `.hivelog-cbr-summary` (the corporate-body-return
summary banner, ~lines 265–278) still hard-coded `#d1d5db` /
`#f9fafb` for its border and background. The beeswax skin (task 0061)
therefore still restated that one block just to recolour it.

`css/hivelog.buttons.css` is in the `hivelog/buttons` library, which
depends on `hivelog/responsive` (where the tokens live), so the tokens
are already in scope — no library change needed.

## Acceptance criteria
- [x] `.hivelog-cbr-summary` border + background now use
      `var(--hivelog-border)` / `var(--hivelog-surface-2)`; no
      hard-coded hex left in that rule block.
      `.hivelog-cbr-summary a { color: var(--hivelog-btn-primary-bg) }`
      was already a token, left as is.
- [x] **Stock render check.** Resolved the two tokens back through
      their `:root` definitions in `css/hivelog.responsive.css`:
      `--hivelog-border: #d1d5db` and `--hivelog-surface-2: #f9fafb`
      — byte-for-byte the hex they replaced. Zero visual change on a
      stock theme; this is a pure indirection, no consolidation.
- [x] phpcs clean:
      `/tmp/phpcs-check/vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php,css --warning-severity=0 css/hivelog.buttons.css`
- [x] Kernel smoke: `DashboardTest` green from cms2
      (`ddev exec … modules/contrib/hivelog/tests/src/Kernel/DashboardTest.php`)
      — 36 tests, 879 assertions, OK (4 pre-existing geofield
      deprecations, unrelated). CSS-only change, no render-array impact.
- [x] PR against `main`, squash-merged once CI was green (lint +
      Kernel/Unit are the hard gates; phpstan + Functional are advisory)
      — PR #137, merge commit `5058d0c`. Shipped in release 1.8.4.

## Then: beeswax + release — done
Mirrored how [[0062-module-themeability-tokens]] finished.

1. [x] hivelog release **1.8.4** — `hivelog.info.yml` bumped,
   `docs/project-management/releases/1.8.4.md` added, tag `1.8.4` +
   GitHub release. cms2 pinned (`hivelog/hivelog: 1.8.4`, commit
   `c37d599`); dashboard verified rendering under beeswax.
2. [x] beeswax **1.0.2** (`deburca/beeswax`, commit `429e959`) —
   dropped the `.hivelog-cbr-summary` `border` / `background` and the
   `.hivelog-cbr-summary a` colour restatements. The
   `body.hivelog-page` token bridge already maps
   `--hivelog-border: var(--bw-hairline)`,
   `--hivelog-surface-2: var(--bw-surface-2)` and
   `--hivelog-btn-primary-bg: var(--bw-accent)`. Kept: `border-radius:
   6px` and `color: var(--bw-ink-muted)` — the module still hard-codes
   a 4px radius and `color: #374151` for that block, so those two stay
   as genuine deviations.
3. [x] Tag `1.0.2` + GitHub release on `deburca/beeswax`.
4. [x] cms2 — `ddev composer update deburca/beeswax hivelog/hivelog`,
   `ddev drush cr`; authenticated `/hivelog` fetch returns 200 with the
   CBR summary, `hivelog-page` body class and stat tiles rendering.
   `composer.lock` committed (`4bae395`); `hivelog` pin bump was
   `c37d599`.
5. [x] Task closed. Clears the last carry-forward from the
   `hivelog-visual-identity` project.

## Implementation notes
- Token-only, no brand in the module ([[0060-visual-identity-in-site-theme]]).
- Key file: `css/hivelog.buttons.css` (`.hivelog-cbr-summary` block).

## Related
- Project:: [[hivelog-visual-identity]]
- Follows:: [[0062-module-themeability-tokens]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0012-action-button-design-system]]
- Commits:: `5058d0c` (PR #137, module side, css tokenisation); `4c9adf9`
  (release 1.8.4); beeswax `429e959` (1.0.2 — drop restatement); cms2
  `c37d599` (pin hivelog 1.8.4) + `4bae395` (update beeswax 1.0.2)
