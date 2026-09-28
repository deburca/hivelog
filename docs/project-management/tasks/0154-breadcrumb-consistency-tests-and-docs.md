---
type: task
tags: [hivelog/task]
status: done
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
- [x] Full kernel/unit/functional suite green against `cms2`, core and
      every submodule (`--group hivelog`), using the multi-path
      invocation (see [[0147-app-nav-registry-parent-key]]'s own notes
      on why a single `hivelog/tests/` path under-covers submodules).
- [x] `AGENTS.md`'s breadcrumb section (`Services` → `hivelog.breadcrumb`)
      updated: documents the new collection-ancestor map alongside the
      existing per-instance `PARENT_FIELD` one, and is explicit that
      they're two independent mechanisms for two different page kinds
      — the exact distinction ADR-0105 itself calls out as a
      documentation debt to not let go stale.
  - [x] The "In-app navigation" section (rewritten in task 0151) —
      already fully updated in task 0152's own drive-by fix (it
      renamed Setup to Insights everywhere in that section the same
      day it renamed the code); re-checked here, no drift found.
- [x] `hivelog.api.php` double-checked — already fully updated in task
      0152's own drive-by fix; re-checked here, no drift found (the
      one remaining "setup" mention is the deliberate historical note
      "task 0152 renamed it from `'setup'`").
- [x] `README.md` re-checked — no "Setup" mention anywhere in the file,
      and its "Navigation" section's breadcrumb description ("reflect
      the full entity hierarchy … all ancestor crumbs are navigable
      links") is a general statement that stays accurate under the new
      collection-ancestor behaviour too, not a claim of flatness this
      task would contradict — left untouched per task 0151's own
      "only if it currently describes nav layout" rule.
- [x] phpcs clean; phpstan clean (module-wide; baseline unchanged — no
      source files changed in this task at all).

## Implementation notes
- **Most of this task's own acceptance criteria were already satisfied
  before it started.** Tasks 0152 and 0153 each did their own
  "drive-by" documentation fixes as they went (0152 renamed every
  Setup reference in AGENTS.md's nav section and hivelog.api.php the
  same day it renamed the code, rather than leaving core docs wrong
  for a whole extra task cycle) — this task's own scan confirmed both
  were already clean, and made the one substantive change actually
  left: the breadcrumb section's `build()` bullet list still described
  every collection as ending in a single flat terminal crumb, stale
  since task 0153. Rewrote that bullet (and the sibling "site-wide add
  form" bullet, which had the same staleness) to document
  `COLLECTION_ANCESTOR_ROUTE` / `addCollectionAncestryLinks()` /
  `collectionCrumbLabel()` alongside the existing `PARENT_FIELD`
  description, explicit that the two are independent mechanisms for
  collection pages vs. per-instance pages respectively.
- **No source changes** — this task, as scoped, only touched
  `AGENTS.md`. phpcs/phpstan re-run anyway for the module-wide
  confirmation the acceptance criteria ask for; both clean, baseline
  untouched.
- This closes out [[in-app-navigation-restructuring]]'s current scope
  (ADR-0104's two-tier nav + ADR-0105's breadcrumb consistency and
  Insights rename) — tasks 0146 through 0154, all `status: review`
  and pushed.
- Key files: `AGENTS.md`.
- No entity schema change → **no update hook required**.
- Full kernel/unit/functional suite against `cms2`, core and every
  submodule: **1,076 tests, 0 failures**.

## Related
- Project:: [[in-app-navigation-restructuring]]
- Decisions:: [[0105-collection-breadcrumb-ancestry-and-insights-rename]]
- Tasks:: [[0151-two-tier-nav-tests-and-docs]]
- Commits::
