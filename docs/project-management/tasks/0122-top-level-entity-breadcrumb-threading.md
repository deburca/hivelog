---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0122-top-level-entity-breadcrumb-threading
release:
depends-on: ["[[0116-breadcrumb-builder-parent-map-refactor]]"]
blocked-by:
---
# Task: Decide how top-level entities thread their breadcrumb

## Context
From the navigation and breadcrumb review of 2026-09-23. Entities with
no apiary / hive ancestor thread their trails in three different ways
today:

| Entity | Canonical trail | Through its collection? |
|---|---|---|
| Sensor device, API client, AI provider config | Home › HiveLog › [Collection] › Entity | yes ([[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]], by user direction) |
| Apiary | Home › HiveLog › Apiary | **no** |
| Queen with no hive | Home › HiveLog › Queen | **no** |

Apiary skips "Apiaries" because of history, not by design. Before
[[0057-dashboard-information-architecture]], the "HiveLog" crumb *was*
the apiary collection (`/hivelog`), so the collection was already in
the trail. 0057 moved the collection to `/hivelog/apiaries` and pointed
"HiveLog" at the dashboard, and the apiary trail never gained the
collection crumb it had lost. [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]]
treated the skip as deliberate ("per ADR-0057"). This task settles it
explicitly.

A related but separate case: **calendar actions** thread
`Apiary › Calendar action`, skipping the apiary's own Calendar page
(`hivelog.apiary.calendar_action.collection`), where users usually open
them from.

**Needs a decision before code**, hence `backlog`.

## Acceptance criteria
- [x] Decision recorded for each of:
      (1) Apiary: add "Apiaries" between HiveLog and the apiary, or
      keep the skip;
      (2) unassigned Queen: add "Queens", or keep the skip;
      (3) Calendar action: thread `Apiary › Calendar › Action`, or keep
      `Apiary › Action`.
- [x] If any change is chosen and it changes [[0013-breadcrumb-policy]]
      rule 1 (the Apiary → Hive → … chain), record it as an amendment
      alongside [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
      (new ADR or an addendum to 0102, whichever is smaller).
- [x] Implemented in the collection-threaded list / parent map from
      [[0116-breadcrumb-builder-parent-map-refactor]]; unit tests updated.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Decisions (confirmed with the user, 2026-09-26) — all three "add it"**,
against this task's own suggested default of keeping the Apiary skip:
1. **Apiary**: add "Apiaries". Every apiary-descended trail is now one
   crumb longer, and the apiary page itself does lose its untruncated
   "HiveLog" link past the theme's 3-crumb cap (confirmed live — see
   Verification below) — accepted knowingly, per the trade-off this
   task's own context already spelled out.
2. **Unassigned Queen**: add "Queens". No trade-off either way, per
   the task's own note — a straightforward consistency win.
3. **Calendar action**: thread `Apiary › Calendar › Action`.

No amendment to ADR-0013/0102 was needed — this doesn't change *how*
the ancestor chain is walked (`PARENT_FIELD` is untouched), only adds
an extra crumb wherever a specific ancestor *type* is rendered, which
is squarely within `HivelogEntityHierarchy`/`HivelogBreadcrumbBuilder`'s
existing declarative-map design (task 0116/0127) rather than a policy
change worth its own ADR.

**Design: three per-ancestor-type insertions in `addAncestryLinks()`'s
existing chain-render loop**, not three different mechanisms:
- `apiary` — always threaded (it has no `PARENT_FIELD` entry, so it's
  always the chain's root once reached).
- `calendar_action` — threaded with its own apiary-scoped Calendar page
  (`hivelog.apiary.calendar_action.collection`) whenever its own apiary
  resolves; applies equally to `CalendarActionItemRequirement` /
  `CalendarActionProductYield` trails (which already thread through
  `calendar_action` as an ancestor) — a deliberate, consistent
  side effect, not something the task named explicitly.
- `queen` (a new `HivelogEntityHierarchy::COLLECTION_FALLBACK_TYPES`)
  — threaded with "Queens" only where a `queen` ancestor's *own*
  `resolveParent()` (its `hive` field) comes up empty.

Checking every ancestor as the chain renders — not just the route's
own subject — turned out to matter for a case the task didn't name
explicitly: `QueenObservation` of an *unassigned* queen. An earlier,
narrower version of this fix only checked whether the *subject's own*
walk came up completely empty, which correctly handled a bare
unassigned-queen page but missed the observation case (its own chain
is `[observation, queen]` — length 2, not 1 — even though the queen
inside it is unassigned). Rewriting the check to run per-ancestor
inside the existing loop (matching how `apiary`/`calendar_action`
already work) fixed both cases with one mechanism instead of two.

**Test ripple was the bulk of the work.** Apiary is the root of nearly
every hivelog trail, so `HivelogBreadcrumbBuilderTest`'s 62 test
methods (120 with data providers) had ~40 assertions shift by one or
two link indices. Went through every failure individually against the
actual new behavior rather than blindly incrementing indices, which is
exactly what caught the observation/unassigned-queen gap above — a
mechanical find-and-shift pass would have "fixed" those two tests
without ever noticing they were testing the wrong thing.

**Verification.** phpcs clean; phpstan clean (module-wide, no baseline
changes). `HivelogBreadcrumbBuilderTest`: 120 tests, 0 failures. Full
kernel/unit/functional suite against `cms2`, core and every submodule:
green (see this task's own commit for the exact count). Live-verified
on `cms2` (`kragebaekgaard.ddev.site`): an apiary's own page now shows
`Forside › ⋯ › Bigårde (Apiaries) › <Apiary>` — confirming the
predicted truncation trade-off actually occurs, not just in theory —
and a calendar action with a real apiary shows the full
`Home › HiveLog › Apiaries › <Apiary> › Calendar › <Action>` chain
(the middle collapsed by the same truncation, "Calendar" and the
terminal crumb visible as the theme's own "last two" rule). Also
discovered, incidentally, that `calendar_action` id 1 on `kbg` is
itself a genuine dangling reference (`apiary` field points at a
deleted apiary) — exactly the kind of orphan
[[0145-orphan-report-and-cleanup-command]]'s own `drush hivelog:orphans`
exists to find; out of scope here, not touched.

- Key files: `src/HivelogEntityHierarchy.php` (new
  `COLLECTION_FALLBACK_TYPES`), `src/Breadcrumb/HivelogBreadcrumbBuilder.php`
  (`addAncestryLinks()` rewritten), `AGENTS.md` (Services section
  updated), `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`
  (~40 assertions updated).

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]],
  [[0057-dashboard-information-architecture]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Tasks:: [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
