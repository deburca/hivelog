---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `navigation-and-page-layout.md` rewritten against the current code:
      entry points (dashboard, nav strip, derived menu links), URL
      structure including the submodule routes, breadcrumb rules per
      [[0013-breadcrumb-policy]] as amended by
      [[0057-dashboard-information-architecture]] and
      [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]], and
      the navigation graph. `updated:` bumped.
- [ ] Its "Observations & gaps" section reconciled: items fixed since
      2026-09-07 removed or marked resolved; anything
      [[0121-reachability-of-orphaned-collection-pages]] /
      [[0122-top-level-entity-breadcrumb-threading]] deliberately left
      as-is noted as intentional.
- [ ] AGENTS.md's description of the breadcrumb `build()` shape (under
      "Services") matches the builder after [[0116-breadcrumb-builder-parent-map-refactor]] and
      [[0117-breadcrumb-terminal-crumb-on-form-pages]]: parent map, the
      terminal-crumb rule, and "adding a new entity type = one map
      entry" replacing the "mirror the product / inventory_item loop"
      instruction.
- [ ] `roadmap.md`'s [[breadcrumb-consistency]] line updated (it still
      says "planning; 0013 audit queued").
- [ ] Every wikilink in the touched files resolves.

## Implementation notes
- Docs only. No release or version bump needed for this task alone.
- `navigation-and-page-layout.md` is a loose file at the vault root.
  Leave it there unless the user wants reference docs collected into a
  directory; moving it is out of scope here.

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
