---
type: task
tags: [hivelog/task]
status: done
priority: medium
project:
area: routing
created: 2026-09-28
branch: feature/0159-parent-collection-crosslinks-on-hive-inspection-observation-lists
release:
depends-on:
blocked-by:
---
# Task: "View <parent collection>" cross-links on the Hives, Inspections and Queen Observations lists

## Context
`/hivelog/hives`, `/hivelog/inspections` and `/hivelog/queen-observations`
currently render with no heading at all above their filter form (task
0132/0155-0158 gave them a filter form, but `getHeadingActions()` still
returns `[]` on all three) — every other filtered collection page
(Apiaries, Queens, Inventory Items/Purchases, Products, AI Provider
Configs, Sensor Devices, API Clients) has a heading action row above the
same filter+table layout. AGENTS.md documents this gap as deliberate:
Hive/HiveInspection/QueenObservation have no context-free add route
(always added from an apiary/hive/queen's own page), so an "Add" button
here has nowhere sensible to go.

The request was for an Add button matching `/apiaries`' own layout, but
that would require a new "pick a parent first" route/form per entity
type — a bigger change than the visual gap actually calls for. Settled
on the lighter option instead: a single "View <parent collection>"
cross-link (View Apiaries / View Hives / View Queens respectively) in
the same heading slot, the same kind of shortcut
`InventoryPurchaseListBuilder`'s "View Inventory Items" already is. It
gives the page a heading matching every other collection's layout
without touching the "no context-free add route" constraint at all —
the button navigates to an existing collection, it doesn't open an add
form.

## Acceptance criteria
- [x] `HiveListBuilder::getHeadingActions()` returns a single "View
      Apiaries" button linking to `entity.apiary.collection`.
- [x] `HiveInspectionListBuilder::getHeadingActions()` returns a single
      "View Hives" button linking to `entity.hive.collection`.
- [x] `QueenObservationListBuilder::getHeadingActions()` returns a
      single "View Queens" button linking to `entity.queen.collection`.
- [x] No new routes/forms — these are cross-links to already-existing
      collection routes, matching `render()`'s existing heading-actions
      mechanism unchanged (task 0126).
- [x] `HivelogListBuilder`'s own `getHeadingActions()` docblock and
      AGENTS.md's "Routing, controllers and forms" section both updated
      — the "no add button by design" statement stays true (still no
      Add button on any of the three), but the "no heading action at
      all" framing needed correcting now that all three do carry one.
- [x] Verified live on `cms2`: all three pages show the heading with
      its View button above the filter form, matching `/apiaries`'
      layout; each button navigates to the right collection.
- [x] phpcs clean; phpstan clean.

## Implementation notes
- Key files: `src/HiveListBuilder.php`, `src/HiveInspectionListBuilder.php`,
  `src/QueenObservationListBuilder.php`, `src/HivelogListBuilder.php`
  (docblock only), `AGENTS.md`.
- No entity schema change, no new route → **no update hook required**.
- Button props carry no `variant` key (same as
  `InventoryPurchaseListBuilder`'s "View Inventory Items" precedent) —
  default styling, since this isn't the primary action on the page (it
  has no primary action at all, unlike pages with a real Add button).
- First proposal was an actual "Add Hive"/"Add Inspection"/"Add
  Observation" button matching `/apiaries` exactly — flagged to the
  user first since it conflicts with the documented "no context-free
  add route" constraint (a button would need a new "pick a parent"
  route/form per entity type to go anywhere useful). User's follow-up
  request switched to "View <parent>" cross-links instead, which needs
  none of that — implemented as specified.
- Regression check: `FullListFilterTest` + `HivelogListBuilderPaginationTest`
  + `ListBuilderAccessFilterTest` (58 tests) — all green with the new
  heading wired in.
- Live-verified on `cms2` (`kbg` site): `/hivelog/hives` showed "View
  Apiaries" → `/hivelog/apiaries` immediately; `/hivelog/inspections`
  and `/hivelog/queen-observations` needed an explicit `drush cr`
  before their new heading appeared (a stale render-cache entry from
  before the sync — `/hivelog/hives` happened to not be cached yet, the
  other two were), after which both showed their correct link too.
- Cleanup note (unrelated to this task's own code, caught while
  verifying): found a leftover throwaway apiary + hive ("Filter Verify
  Apiary 0157" / "Filter Verify Hive") still on the `kbg` site from
  task 0157's live verification — that session's fixture script had
  partially executed (apiary/hive saved) before failing on a missing
  `nanoprobe` class, since `kbg` doesn't have that submodule enabled;
  the switch to the `vdg` site for the rest of 0157's fixtures meant
  this partial leftover was never caught at the time. Deleted now,
  confirmed gone via an entity query.

## Related
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]]
- Commits::
