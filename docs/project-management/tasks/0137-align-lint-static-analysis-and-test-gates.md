---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] `phpcs.xml.dist` includes `modules/` (and the submodules'
      `.module` / `.install` files), so `composer lint` == CI phpcs.
      Switch CI's phpcs step to `phpcs` with no path arguments (read the
      config) so the two can't drift again.
- [ ] `phpstan.neon` includes `modules/*/src` and `modules/*/tests`. Fix
      or baseline the resulting findings (`phpstan-baseline.neon`) so
      the step can become a **hard gate**. Remove
      `continue-on-error: true`, or record in Implementation notes why
      it must stay.
- [ ] Functional tests: the CI step discovers `*/tests/src/Functional`
      the same way Kernel / Unit do (ADR-0098 §7 pattern), so submodule
      functional tests run.
- [ ] Access-critical assertions don't depend on the advisory
      functional job. [[0133-route-level-entity-access]]'s
      `RouteEntityAccessTest` is a **kernel** test, so it's in the hard
      gate. Confirm that, and move any other access assertions that
      only exist in `PermissionMatrixTest` into kernel tests.
- [ ] AGENTS.md "CI Pipeline" section
      updated to match.
- [ ] CI green on a branch with the changes.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0098-nanoprobe-collective-locutus-submodule-split]]
- Tasks:: [[0133-route-level-entity-access]]
- Commits::
