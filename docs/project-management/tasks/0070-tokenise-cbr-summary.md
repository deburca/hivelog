---
type: task
tags: [hivelog/task]
status: in-progress
priority: low
project: "[[hivelog-visual-identity]]"
area: theme
created: 2026-09-08
branch: feature/0070-tokenise-cbr-summary
release:
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
- [ ] PR against `main`, squash-merge once CI is green (lint +
      Kernel/Unit are the hard gates; phpstan + Functional are advisory).

## Then: beeswax + release
Mirrors how [[0062-module-themeability-tokens]] finished.

1. Cut a hivelog release: bump `hivelog.info.yml`, add
   `docs/project-management/releases/X.Y.Z.md`, tag, `gh release create`.
   Pin cms2 to it.
2. In beeswax (`/Users/paddy/Development/beeswax`, `deburca/beeswax`),
   `src/hivelog.css` has an explicit `.hivelog-cbr-summary` block
   restating border / border-radius / background / color in `--bw-*`
   terms — redundant once the module reads `--hivelog-border` /
   `--hivelog-surface-2`, because the `body.hivelog-page { --hivelog-*:
   var(--bw-*) }` bridge already maps both
   (`--hivelog-border: var(--bw-hairline)`,
   `--hivelog-surface-2: var(--bw-surface-2)`), and
   `--hivelog-btn-primary-bg` already resolves to `--bw-accent`. Delete
   the border/background/`a` colour rules; keep only anything genuinely
   additive (box-shadow, a radius the module doesn't set).
3. Commit + push beeswax, tag `1.0.2`, `gh release create` on
   `deburca/beeswax`.
4. cms2: `ddev composer update deburca/beeswax hivelog/hivelog`,
   `ddev drush cr`, verify the dashboard renders (authenticated:
   `ddev drush uli`, curl `/hivelog`), commit `composer.json` /
   `composer.lock`.
5. Close this task (`status: done`, record the commits). This clears
   the last carry-forward from the `hivelog-visual-identity` project.

## Implementation notes
- Token-only, no brand in the module ([[0060-visual-identity-in-site-theme]]).
- Key file: `css/hivelog.buttons.css` (`.hivelog-cbr-summary` block).

## Related
- Project:: [[hivelog-visual-identity]]
- Follows:: [[0062-module-themeability-tokens]]
- Decisions:: [[0060-visual-identity-in-site-theme]], [[0012-action-button-design-system]]
- Commits::
