---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[breadcrumb-consistency]]"
area: docs
created: 2026-09-23
branch: feature/0123-refresh-navigation-reference-docs
release:
depends-on: ["[[0117-breadcrumb-terminal-crumb-on-form-pages]]", "[[0118-page-owned-edit-delete-then-retire-local-tasks]]", "[[0119-single-source-navigation-registry]]", "[[0120-app-nav-active-state-and-grouping]]"]
blocked-by:
---
# Task: Refresh the navigation reference docs

## Context
From the navigation and breadcrumb review of 2026-09-23. The vault's
navigation reference map, `navigation-and-page-layout.md` (vault root,
`type: review`, last updated 2026-09-07), predates
[[0057-dashboard-information-architecture]]'s dashboard,
[[0105-submodule-navigation-menu-links]]'s nav strip and all three
submodules' pages. For example, it still says `/hivelog` "*is* the
apiary collection" and there is "no dashboard or landing screen". It
also lists `hivelog.links.task.yml` / `.links.action.yml` as sources,
which [[0118-page-owned-edit-delete-then-retire-local-tasks]] deletes.

Do this last, once the code tasks in [[breadcrumb-consistency]] have
settled the structure, so the reference is written once against the
final state.

## Acceptance criteria
- [x] `navigation-and-page-layout.md` rewritten against the current code:
      entry points (dashboard, nav strip, derived menu links), URL
      structure including the submodule routes, breadcrumb rules per
      [[0013-breadcrumb-policy]] as amended by
      [[0057-dashboard-information-architecture]] and
      [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]], and
      the navigation graph. `updated:` bumped.
- [x] Its "Observations & gaps" section reconciled: items fixed since
      2026-09-07 removed or marked resolved; anything
      [[0121-reachability-of-orphaned-collection-pages]] /
      [[0122-top-level-entity-breadcrumb-threading]] deliberately left
      as-is noted as intentional.
- [x] AGENTS.md's description of the breadcrumb `build()` shape (under
      "Services") matches the builder after [[0116-breadcrumb-builder-parent-map-refactor]] and
      [[0117-breadcrumb-terminal-crumb-on-form-pages]]: parent map, the
      terminal-crumb rule, and "adding a new entity type = one map
      entry" replacing the "mirror the product / inventory_item loop"
      instruction.
- [x] `roadmap.md`'s [[breadcrumb-consistency]] line updated (it still
      says "planning; 0013 audit queued").
- [x] Every wikilink in the touched files resolves.

## Implementation notes

**`navigation-and-page-layout.md` fully rewritten**, not incrementally
patched — the drift was large enough (predates the dashboard, the nav
strip, all three submodules, and both local-task retirement and the
single-source nav registry) that patching section-by-section risked
missing cross-references between sections. Every factual claim was
re-verified against the current code before being written, not carried
over from the 2026-09-07 version on trust — this caught one real, already
existing inaccuracy unrelated to anything this session touched: the old
§3.3 (Hive canonical) described a "Previous Queens" history table
(`buildQueenHistorySection()`) that doesn't exist in the current
`HiveController` at all — `AGENTS.md`'s own "Content entities" section
already correctly says the hive page has no standalone previous-queens
list ("View all Queens" covers that instead) — so this document had
already drifted from `AGENTS.md` itself, not just from the code, before
this refresh. Fixed to match reality.

**§7 Observations & gaps**: two items were removed as resolved rather
than marked historically ("no landing page" — fixed by 0057's dashboard,
well before this session; "inconsistent local-task tab coverage" — fixed
outright by 0118 retiring tabs entirely). One item's own premise (§5's
old note that `applies()` matches by route-name-prefix) was already
false by the time of this rewrite — `applies()` has matched by path since
task 0067 — corrected in §5's prose rather than kept as an "observation."
[[0121-reachability-of-orphaned-collection-pages]] and
[[0122-top-level-entity-breadcrumb-threading]] are both still `backlog`
(no decision made yet), so their two items are worded as "tracked,
pending a decision" rather than "deliberately left as-is" — the latter
would overstate what's actually happened; neither task has been decided.

**AGENTS.md**: rewrote the breadcrumb `build()` shape bullets to
describe the current architecture (`HivelogEntityHierarchy::PARENT_FIELD`
+ `resolveSubject()` + `TERMINAL_CRUMB_PARAM`, task 0116/0117/0127)
instead of the pre-refactor `$leaf_pages`/`$apiary_page_crumbs` maps that
no longer exist in the code, and replaced "mirror the product /
inventory_item / inventory_purchase loop" with the real current
instruction (one `PARENT_FIELD` entry, no per-type `build()` block).
Also refreshed the adjacent "In-app navigation" subsection (not named in
this task's own AC, but the same kind of drift this task exists to fix)
to cover task 0120's additions — `group`/`section` on the item
descriptor, the Dashboard entry, active-state marking — which weren't
there when that subsection was first written two tasks ago. Caught and
fixed one mistake of my own while drafting this: an early version
claimed the Dashboard entry *is* included in the derived menu links
(since `hivelog.admin` already covers that route); the actual code
excludes it deliberately, matching what I'd already verified live during
task 0120.

**`roadmap.md`**: updated only the single `[[breadcrumb-consistency]]`
line the AC names — the file is stale well beyond that one line (last
refreshed 2026-07-03, several other project lines reference tasks long
since completed), but a full roadmap refresh is a different, larger task
than this one; scoped to what was asked.

No code changes, no release/version bump, no tests to run — docs only,
per the task's own implementation notes.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]],
  [[0057-dashboard-information-architecture]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]],
  [[0117-breadcrumb-terminal-crumb-on-form-pages]],
  [[0118-page-owned-edit-delete-then-retire-local-tasks]],
  [[0119-single-source-navigation-registry]],
  [[0120-app-nav-active-state-and-grouping]]
- Commits::
