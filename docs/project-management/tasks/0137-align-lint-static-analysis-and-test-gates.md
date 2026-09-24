---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[page-structure-consistency]]"
area: tests
created: 2026-09-23
branch: feature/0137-align-lint-static-analysis-and-test-gates
release:
depends-on:
blocked-by:
---
# Task: Align local lint, static analysis and CI test gates

## Context
From the 2026-09-23 gap analysis. What runs locally and what runs in
CI have drifted, and the submodules get less checking than core:

| Check | Local (`composer lint` / `stan`) | CI (`.github/workflows/ci.yml`) |
|---|---|---|
| phpcs | `phpcs.xml.dist`: `src/`, `tests/`, `hivelog.module`, `hivelog.install`. **No `modules/`** | explicit paths including `modules/` (hard gate) |
| phpstan | `phpstan.neon`: `src/`, `tests/`. **No `modules/`** | same config, `continue-on-error: true` |
| Kernel + Unit | n/a | every `*/tests/src/Kernel` + `Unit` dir via `find` (hard gate) |
| Functional | n/a | **core module's `tests/src/Functional` only**, `continue-on-error: true` |

So a submodule change can pass `composer lint` locally and then fail
phpcs in CI, and submodule code never gets phpstan at all. More
importantly, the only route-level access coverage
(`PermissionMatrixTest`) is a functional test, which is advisory. That's
part of why [[0133-route-level-entity-access]]'s bug shipped unnoticed.

## Acceptance criteria
- [x] `phpcs.xml.dist` includes `modules/` (and the submodules'
      `.module` / `.install` files), so `composer lint` == CI phpcs.
      Switch CI's phpcs step to `phpcs` with no path arguments (read the
      config) so the two can't drift again.
- [x] `phpstan.neon` includes `modules/*/src` and `modules/*/tests`. Fix
      or baseline the resulting findings (`phpstan-baseline.neon`) so
      the step can become a **hard gate**. Remove
      `continue-on-error: true`, or record in Implementation notes why
      it must stay.
- [x] Functional tests: the CI step discovers `*/tests/src/Functional`
      the same way Kernel / Unit do (ADR-0098 §7 pattern), so submodule
      functional tests run.
- [x] Access-critical assertions don't depend on the advisory
      functional job. [[0133-route-level-entity-access]]'s
      `RouteEntityAccessTest` is a **kernel** test, so it's in the hard
      gate. Confirm that, and move any other access assertions that
      only exist in `PermissionMatrixTest` into kernel tests.
- [x] AGENTS.md "CI Pipeline" section
      updated to match.
- [x] CI green on a branch with the changes.

## Implementation notes
**Implemented 2026-09-24.**
- **phpcs was already effectively aligned in content** — `composer.json`'s
  own `lint` script already passed `modules/` explicitly on the CLI, so
  `composer lint` and CI's own hardcoded path list already checked the
  same files. The real drift was structural: two independent hardcoded
  path/extension lists (composer.json's script, the CI step) that could
  silently diverge the next time either one changed, plus
  `phpcs.xml.dist`'s own `<file>` defaults (used by a bare `phpcs`, e.g.
  from an IDE) not including `modules/` at all. Fixed by moving
  `modules/` and the extension list into `phpcs.xml.dist` itself and
  simplifying both `composer.json`'s script and the CI step to a bare
  `phpcs` (`--warning-severity=0` in CI only) — one source of truth,
  every invocation reads it. Whole-module run (247 files, all 4
  submodules + hivelog.module/.install): 0 errors, 0 warnings — no code
  changes needed, just the config/CI plumbing.
- **The phpstan baseline (440 findings) was generated against the same
  bare, extension-free phpstan CI actually runs** — not a lighter local
  substitute. Confirmed by resolving `phpstan.neon`'s own `paths:` from
  a *different* working directory than the module root
  (`cd /var/www/html && phpstan analyse -c web/modules/contrib/hivelog/phpstan.neon`),
  matching exactly how CI's `test` job invokes it from `drupal-project`
  — phpstan resolves a config's `paths:` relative to the config file's
  own directory, not the invoking cwd, so this holds regardless of
  where the command runs from. `.gitignore` had `phpstan-baseline.neon`
  listed (predating this task, presumably written when a baseline was
  only ever a local scratch file, never intended to ship) — removed
  that line, since an ignored baseline would leave CI facing all 440
  raw findings with nothing to absorb them.
- **The `continue-on-error: true` removal only affects the `test` job's
  phpstan step** — the `lint` job's own phpstan step stays a no-op
  placeholder (mglaman/phpstan-drupal, needed for Drupal-aware analysis
  without a full scaffold, isn't installed in that job's isolated tool
  directory) exactly as before; nothing to remove there since it never
  ran real analysis to gate on.
- **`PermissionMatrixTest` vs `RouteEntityAccessTest` audit**: the
  kernel test already discovers every `/hivelog`-prefixed route from
  the live router and checks `access_manager->checkNamedRoute()` for
  an outsider (has every relevant "own" permission, no relationship to
  the fixture — the exact IDOR shape task 0133 fixed), the owner, and
  `administer hivelog` — a strictly stronger, auto-discovered version
  of `PermissionMatrixTest`'s hand-listed view-only/edit-only/admin
  path arrays (same `checkNamedRoute()` call local-task tab visibility
  itself resolves through, so a route-access assertion already implies
  the matching tab-visibility outcome — no separate porting needed for
  that half). The one real gap: `PermissionMatrixTest::testAnonymousHasNoAccess()`
  asserts a truly anonymous account (zero permissions, not just
  "unrelated") is denied everywhere — `RouteEntityAccessTest`'s
  "outsider" fixture deliberately still holds the "own" permissions, so
  it never exercised the no-permissions-at-all case. Added
  `testAnonymousDeniedOnEveryRoute()` (a real `AnonymousUserSession`,
  looped over every route the same way, entity-parameterized or not)
  to close it. `PermissionMatrixTest` itself is untouched and still
  runs in the advisory job as genuine end-to-end HTTP/rendering
  coverage — the point was never to delete it, only to make sure
  nothing access-critical existed *only* there.
- **The baseline's first CI run failed** — real proof the environment
  concern above wasn't hypothetical. `phpstan-baseline.neon` was
  generated against `cms2`'s own `vendor/`, which happens to have the
  contrib `ai` module installed; two files that call
  `Drupal\ai\AiProviderPluginManager` behind a runtime
  `moduleExists('ai')` guard (`AiModuleProviderCaller.php`,
  `AiProviderConfigController.php` — `ai` is not a real Composer
  dependency of `hivelog`/`nexus`, only an optional integration)
  resolved that class fine there, baselining a narrower
  "undefined method" finding. CI's bare `drupal/recommended-project`
  scaffold never installs `ai` at all, so the same two files produced
  a *different* error shape (unknown class) the baseline had no entry
  for, plus a meta-error for the now-unmatched baselined pattern.
  Fixed by excluding both files from phpstan outright
  (`excludePaths`) rather than chasing whichever shape the next
  environment produces, and regenerating the baseline (436 findings,
  down from 440) without their now-moot entries. Pushed as a second
  commit; CI went green on the re-run.
- **Verification**: whole-module phpcs (0 errors/warnings) and phpstan
  (0 errors against the baseline) locally; full `hivelog` kernel/unit
  suite 702 tests (up 1 from the new anonymous test), 11,984
  assertions, only the 3 pre-existing, already-documented, unrelated
  `DashboardTest` Functional errors. **CI verified green on
  [PR #140](https://github.com/deburca/hivelog/pull/140)**: `Lint
  (PHP 8.3)`, `Test (PHP 8.3)`, `Test (PHP 8.4)`, `Test (PHP 8.5)` all
  passing — phpstan included, now a real hard gate for the first time.
- Key files: `phpcs.xml.dist`, `composer.json` (`lint`/`stan` scripts),
  `phpstan.neon` (`modules/` path, `includes: phpstan-baseline.neon`,
  `excludePaths` for the two `ai`-integration files), new
  `phpstan-baseline.neon`, `.gitignore` (dropped the line excluding
  it), `.github/workflows/ci.yml` (phpcs/phpstan/functional-discovery
  steps), `tests/src/Kernel/RouteEntityAccessTest.php`
  (`testAnonymousDeniedOnEveryRoute()`), `AGENTS.md` ("CI Pipeline").

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0098-nanoprobe-collective-locutus-submodule-split]]
- Tasks:: [[0133-route-level-entity-access]]
- Commits:: [PR #140](https://github.com/deburca/hivelog/pull/140)
