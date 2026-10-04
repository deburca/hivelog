---
type: roadmap
tags: [hivelog/roadmap]
status: living
updated: 2026-10-04
---
# 🐝 Hivelog Roadmap
A living roadmap derived from the projects, tasks, decisions, and releases in
this vault. Sequencing is driven by dependencies and completed work rather than
fixed dates. Last refreshed **2026-10-04**.
## Where things stand
183 tasks: **142 done**, 2 in-progress, 1 review, 36 backlog, 2 dropped —
none in `todo`. 24 projects: **11 done**, 4 active, **8 planning**, 1
dropped. 46 decisions: 45 accepted, 1 proposed.
The latest release is **2.4.0** (2026-10-02). The 8 planning projects and
their 24 backlog tasks (0174–0197) came out of the
[[2026-10-04-beekeeping-software-market-survey]]. See "Planned projects"
below.
## Release timeline
### ✅ Released
26 versions, 1.1.0 through **2.4.0** (the latest tag). Each has its own
note in `releases/`.
- **Since 2.0.0:** [[2.1.0]] (2026-09-28) two-tier in-app navigation
  (ADR-0104); [[2.2.0]] (2026-09-29) hive component weight tracking
  (ADR-0106), with the [[2.2.1]] form fix; [[2.2.2]] (2026-10-02) nav
  submenu on touch devices; [[2.3.0]] (2026-10-02) action log list
  filters, with the [[2.3.1]] date-range and [[2.3.2]] queen-button fixes;
  [[2.4.0]] (2026-10-02) sortable columns on collection lists.
- **[[1.8.9]]** (2026-09-24) — the two security fixes:
  [[0133-route-level-entity-access]] (closed an IDOR — no hivelog route
  except one checked per-entity access, so "own"-only permissions passed for
  another user's apiary or hive on view, edit, delete and Insights) and
  [[0124-list-page-row-access-filter]] (stopped collection pages listing
  other users' rows), plus [[0114-apiary-page-remove-ai-insights-indicator-add-sensor]].
- **[[2.0.0]]** (2026-09-26, major bump — see below) — everything from the
  2026-09-23 navigation and page-structure reviews, plus the delete-policy
  work: [[0103-delete-policy-for-records-with-children]]
  ([[0134-delete-dependency-framework]] and its four treatment tasks
  [[0141-delete-block-relationships]], [[0142-delete-cascade-owned-records]],
  [[0143-delete-detach-optional-references]],
  [[0144-delete-warn-historical-references]], plus
  [[0145-orphan-report-and-cleanup-command]]'s `drush hivelog:orphans`);
  shared page-kind implementations
  ([[0125-shared-detail-page-builder]], [[0126-unified-list-page-base]],
  [[0127-shared-delete-form-base]], [[0128-page-heading-hierarchy]],
  [[0129-cancel-link-on-add-edit-forms]],
  [[0131-single-detail-table-css-class]],
  [[0132-filters-on-hive-inspection-observation-lists]]); navigation
  consistency ([[0116-breadcrumb-builder-parent-map-refactor]],
  [[0117-breadcrumb-terminal-crumb-on-form-pages]],
  [[0118-page-owned-edit-delete-then-retire-local-tasks]],
  [[0119-single-source-navigation-registry]],
  [[0120-app-nav-active-state-and-grouping]],
  [[0121-reachability-of-orphaned-collection-pages]],
  [[0122-top-level-entity-breadcrumb-threading]]); and housekeeping
  ([[0135-submodule-create-permissions]],
  [[0136-missing-collection-link-templates]],
  [[0137-align-lint-static-analysis-and-test-gates]],
  [[0138-refresh-agents-md]], [[0139-submodule-packaging-hygiene]],
  [[0123-refresh-navigation-reference-docs]],
  [[0140-controller-and-form-test-gaps]],
  [[0130-shared-calendar-checklist-helpers]]).
  [[0135-submodule-create-permissions]] removes the dead `edit own`/
  `delete own` permissions on `api_client`/`ai_provider_config`, which is
  why 2.0.0 is a major rather than minor bump per
  [[0010-semantic-versioning-and-releases]].

**beeswax paired release: 1.0.3** (github.com/deburca/beeswax, no PM
vault of its own to link to), shipped 2026-09-26.
Checked against all three tasks that flagged a beeswax follow-up in their
own notes: [[0131-single-detail-table-css-class]] needed **no change** —
correcting an earlier assumption here, it does not rename any class; the
13 per-entity classes are kept alongside the new generic
`hivelog-detail-table`/`hivelog-detail-section` per that task's own
acceptance criteria, and beeswax's existing `[class^="hivelog-"]
[class$="-table"]` wildcard selectors already matched the new class
automatically. [[0128-page-heading-hierarchy]]'s h3→h2 change on the
Financial Report headings did leave one beeswax selector dead, but with
no visual regression (a generic `h2` rule already produced identical
styling) — removed for cleanliness.
[[0118-page-owned-edit-delete-then-retire-local-tasks]]'s deleted
`hivelog.links.task.yml` / `hivelog.links.action.yml` left one more dead
selector (`.action-links`, never reachable once no page renders local
actions) — also removed.
## Active projects
- **[[sensor-data-collection]]** — 13 tasks done. The software chain ships;
  what's left is physical. [[0079-pilot-weight-sensor-hardware-build]] is
  `in-progress` and is the only substantive non-release task ready to pick up
  — its stale `blocked-by` (naming the already-`done`
  [[0078-sensor-device-configuration-descriptor]]) has been cleared. Backlog:
  [[0096-off-grid-gateway-power-design]],
  [[0115-varroa-camera-sensor-hardware-build]].
- **[[seasonal-calendar-and-hive-action-tracking]]** — all 11 build tasks done,
  including [[0027-apiary-vs-hive-scoped-calendar-items]] (the `ApiaryActionLog`
  work an earlier roadmap listed as "designed but not started"). Only the
  umbrella [[0026-post-testing-refinements]] is open; its single recorded item
  is done, so it is idle until more hands-on feedback arrives — worth either
  adding items or closing.
- **[[inventory-and-yield-improvements]]** — 8 done, 7 in backlog
  ([[0046-real-sales-ledger]], [[0047-cross-apiary-aggregate-views]],
  [[0048-unit-conversion]], [[0050-fifo-lot-costing]],
  [[0051-expected-unit-price-audit-trail]],
  [[0052-merge-usage-and-yield-form-traits]],
  [[0053-product-category-field]]). All `low` priority; the sales ledger is the
  one that would unlock true profitability rather than potential income.
- **[[ai-apiary-insights]]** — 14 done across `collective`, `nexus` and the
  dashboard. [[0094-ai-insights-prelaunch-validation]] remains in backlog and
  is the gate before pointing this at real beekeepers.
## Planned projects (from the 2026-10-04 market survey)
All 8 are `planning`; every task is `backlog`. Any task that adds an entity
or changes access starts with an ADR task, and the tasks after it depend on
that ADR.
- **[[qr-hive-labels]]** (B, high) — [[0177-hive-quick-access-page]] →
  [[0178-printable-qr-label-sheet]]. Smallest win; the QR helper is reused by
  G.
- **[[data-export-and-printable-records]]** (A, high) —
  [[0174-collection-csv-export]], [[0175-printable-hive-record]],
  [[0176-apiary-export-bundle]]. 0174 feeds D's register report.
- **[[treatment-register]]** (D, high) — [[0182-treatment-register-adr]] →
  [[0183-treatment-entity-and-ui]] → [[0185-treatment-register-report]];
  [[0184-varroa-trend-and-threshold-alert]] can start straight away.
- **[[sensor-event-detection]]** (H, medium, nanoprobe) —
  [[0195-weight-step-change-detection]] →
  [[0196-sensor-event-alerts-and-confirmation]];
  [[0197-hive-opened-detection]]. Tune against real data from
  [[0079-pilot-weight-sensor-hardware-build]].
- **[[weather-aware-inspection-planning]]** (C, medium) —
  [[0179-weather-forecast-adr-and-client]] →
  [[0180-inspection-window-panel]], [[0181-inspection-weather-snapshot]].
  Sends apiary coordinates to a third party, so it's opt-in.
- **[[colony-lifecycle-events]]** (E, medium) — [[0186-colony-lifecycle-adr]]
  → [[0187-colony-event-entity-and-timeline]],
  [[0188-hive-lineage-and-moves]].
- **[[honey-sales-and-batch-provenance]]** (G, low) —
  [[0192-sales-and-batch-adr]] (resolves the ADR prerequisite on
  [[0046-real-sales-ledger]]) → [[0193-honey-batch-entity]] →
  [[0194-public-batch-provenance-page]].
- **[[shared-apiaries-and-team-roles]]** (F, medium, largest and riskiest) —
  [[0189-apiary-membership-access-adr]] →
  [[0190-apiary-member-entity-and-ui]] →
  [[0191-membership-aware-access-handlers]]. Touches every access check;
  may need a major version bump.
## Completed projects
[[mobile-ux-improvements]], [[action-button-consistency]],
[[breadcrumb-consistency]], [[page-structure-consistency]],
[[dashboard-landing-page]], [[hivelog-visual-identity]],
[[inventory-tracking-and-depreciation]],
[[honey-wax-propolis-yield-and-potential-income]],
[[in-app-navigation-restructuring]], [[hive-component-weight-tracking]] and
[[collection-page-filter-coverage]] are all `done`.
## Dropped projects
**[[queen-observation-enhancements]]**, 2026-09-26. Its only undelivered goal
(CSV export, [[0001-queen-observation-csv-export]]) was judged unnecessary on
review and dropped; its other task
([[0002-breadcrumb-queen-canonical]]) was already satisfied by existing code.
## Decision gate
**45 accepted · 1 proposed.** Nothing blocks execution.
[[0057-dashboard-information-architecture]] and
[[0060-visual-identity-in-site-theme]] were accepted in body `## Status` all
along but were missing the `status:` frontmatter field Dataview reads — now
added.
The one proposed ADR is [[0083-ai-assisted-apiary-insights]], deliberately
under-specified as a future-phase umbrella. It has since been overtaken in
practice: [[0100-nexus-in-process-ai-synthesis]] records it as *partially*
superseded, and the insights feature shipped via
[[0087-ai-insights-hosting-and-privacy-model]] /
[[0088-ai-insights-implementation]] / ADR-0100. Worth deciding whether it
should now read `superseded`.
## Unassigned tasks
Two backlog tasks belong to no project: [[0003-apiary-map-marker-clustering]]
(a future map-UX initiative) and
[[0055-embed-required-items-and-expected-yield-in-calendar-action-edit-form]].
## Recommended sequence
1. Finish [[0079-pilot-weight-sensor-hardware-build]] — unblocked now that its
   stale `blocked-by` is cleared — the last step in
   [[sensor-data-collection]]'s original chain.
2. Decide the fate of the idling [[0026-post-testing-refinements]] umbrella
   task — add another item or close it.
3. Re-status [[0083-ai-assisted-apiary-insights]], then run
   [[0094-ai-insights-prelaunch-validation]] before any real-world AI rollout.
4. Pick up [[inventory-and-yield-improvements]]' backlog only when a real need
   surfaces — every item there is `low` and speculative.
5. Close out [[0172-decide-nav-reachability-of-calendar-logs-and-report]]
   (in `review`).
6. Start the planned projects in the survey's recommended order:
   [[qr-hive-labels]] → [[data-export-and-printable-records]] →
   [[treatment-register]] → [[sensor-event-detection]], then
   [[weather-aware-inspection-planning]], [[colony-lifecycle-events]],
   [[honey-sales-and-batch-provenance]] and
   [[shared-apiaries-and-team-roles]].
## Current state
```mermaid
flowchart LR
  Rel(["2.4.0 released<br/>2026-10-02"])
  Survey(["market survey<br/>2026-10-04"]) --> Plan["8 planning projects<br/>tasks 0174–0197"]
  Plan --> B["B QR labels"] --> A["A export"] --> D["D treatments"] --> H["H sensor events"]
  T79["0079 pilot hardware<br/>in-progress, unblocked"] --> Sensors(["sensor-data-collection<br/>chain complete"])
  T26["0026 refinements<br/>idle"] --> Decide{{"add items<br/>or close"}}
```
## Live snapshot — open tasks by project
```dataview
TABLE status, priority, project
FROM #hivelog/task
WHERE status != "done" AND status != "dropped"
SORT project asc, file.name asc
```
## Related
- Dashboard: [[index]]
- Conventions: [[README]]
