---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] Decision recorded for each of:
      (1) Apiary: add "Apiaries" between HiveLog and the apiary, or
      keep the skip;
      (2) unassigned Queen: add "Queens", or keep the skip;
      (3) Calendar action: thread `Apiary › Calendar › Action`, or keep
      `Apiary › Action`.
- [ ] If any change is chosen and it changes [[0013-breadcrumb-policy]]
      rule 1 (the Apiary → Hive → … chain), record it as an amendment
      alongside [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
      (new ADR or an addendum to 0102, whichever is smaller).
- [ ] Implemented in the collection-threaded list / parent map from
      [[0116-breadcrumb-builder-parent-map-refactor]]; unit tests updated.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Trade-off for (1): adding "Apiaries" makes every apiary-descended
  trail one crumb longer. Because the theme truncates to
  `Home … <last two>` above 3 crumbs, the apiary page itself would go
  from `Home › HiveLog › Apiary` (untruncated) to `Home … Apiaries ›
  Apiary`, losing the visible "HiveLog" link. Deeper pages are already
  truncated and see no difference. Weigh this against
  [[0120-app-nav-active-state-and-grouping]]'s Dashboard nav item,
  which would restore a visible way back to the dashboard.
- (2) has no such trade-off (unassigned queens are shallow) and is the
  most straightforward consistency win.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]],
  [[0057-dashboard-information-architecture]],
  [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]]
- Tasks:: [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
