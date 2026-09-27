---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[in-app-navigation-restructuring]]"
area: docs
created: 2026-09-27
branch: feature/0154-breadcrumb-consistency-tests-and-docs
release:
depends-on: ["[[0152-rename-setup-to-insights]]", "[[0153-collection-breadcrumb-ancestor-threading]]"]
blocked-by:
---
# Task: Breadcrumb consistency — final test and docs pass

## Context
The integration/polish pass once both 0152 and 0153 have landed —
mirrors [[0151-two-tier-nav-tests-and-docs]]'s own role at the end of
the original two-tier nav sequence.

## Acceptance criteria
- [ ] Full kernel/unit/functional suite green against `cms2`, core and
      every submodule (`--group hivelog`), using the multi-path
      invocation (see [[0147-app-nav-registry-parent-key]]'s own notes
      on why a single `hivelog/tests/` path under-covers submodules).
- [ ] `AGENTS.md`'s breadcrumb section (`Services` → `hivelog.breadcrumb`)
      updated: documents the new collection-ancestor map alongside the
      existing per-instance `PARENT_FIELD` one, and is explicit that
      they're two independent mechanisms for two different page kinds
      — the exact distinction ADR-0105 itself calls out as a
      documentation debt to not let go stale.
  - [ ] The "In-app navigation" section (rewritten in task 0151) gets
      its `Setup` references updated to `Insights` throughout.
- [ ] `hivelog.api.php` double-checked for any remaining "Setup"
      reference that should now say "Insights".
- [ ] `README.md` re-checked (same "only if it currently describes nav
      layout" rule task 0151 already applied) for any "Setup" mention.
- [ ] phpcs clean; phpstan clean (baseline unchanged or only shrunk).

## Implementation notes
- Key files: `AGENTS.md`, `hivelog.api.php`, `README.md`
  (conditionally). No source changes expected beyond fixing anything
  the full-suite run surfaces.
- No entity schema change → **no update hook required**.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
- Tasks:: [[0151-two-tier-nav-tests-and-docs]]
- Commits::
