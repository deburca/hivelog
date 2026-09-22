---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: ui
created: 2026-09-20
completed: 2026-09-22
branch: feature/0092-hive-apiary-ai-insight-panel
release:
depends-on: ["[[0102-nexus-scaffold-and-ai-provider-config]]"]
blocked-by: ["[[0102-nexus-scaffold-and-ai-provider-config]]"]
---
# Task: "AI Insight" panel on the hive/apiary canonical pages

> **Dependency repointed 2026-09-22** per
> [[0100-nexus-in-process-ai-synthesis]]: `HiveInsight` now lives in
> `nexus` ([[0102-nexus-scaffold-and-ai-provider-config]]), not
> `collective`/[[0089-insight-agent-and-hive-insight-entities]] — schema
> and this task's own acceptance criteria are otherwise unchanged.

## Context
Read-only surfacing of `HiveInsight` where a beekeeper already looks,
per [[0088-ai-insights-implementation]] §4 — the explainability
requirement rendered, not just the verdict.

## Acceptance criteria
- [x] `HiveController::view()` gains an "AI Insight" panel, rendered
      only when the hive's apiary has `ai_insights_enabled = TRUE`,
      showing the latest `HiveInsight` for that hive: `verdict`,
      `recommendation`, the `signals` list rendered via
      `SimpleBulletText::render()` (no new rendering mechanism),
      `confidence`, and how long ago it was `generated`.
- [x] An insight older than roughly 48 hours is visibly flagged as
      stale ("last checked 3 days ago") rather than presented as current
      advice with no caveat — the agent may not have run, or failed.
- [x] `ApiaryController::view()` gains the equivalent panel for
      apiary-scoped insights — ships now even though
      [[0088-ai-insights-implementation]] §5 defers actually *producing*
      apiary-scoped rows to Phase 2, so the panel simply stays empty
      until then rather than needing a second implementation later.
- [x] A hive/apiary with `ai_insights_enabled = FALSE`, or with no
      `HiveInsight` yet, shows no panel at all (not an empty state) —
      this is an opt-in feature, and a beekeeper who hasn't turned it on
      shouldn't see a UI stub for it.
- [x] No add/edit UI anywhere on this panel — strictly read-only,
      matching `HiveInsight`'s "machine-written only" design.
- [x] Kernel tests: panel renders latest insight + signals list for a
      hive with one; hidden entirely when `ai_insights_enabled = FALSE`;
      hidden when no insight exists yet; staleness flag appears past the
      threshold; respects `ApiaryAccessTrait` (a beekeeper without
      access to the hive/apiary cannot see its insight).
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **Neither `HiveController` nor `ApiaryController` needed any code
  change at all** — both already invoke
  `hook_hivelog_hive_view_panels()`/`hook_hivelog_apiary_view_panels()`
  (ADR-0099, built for exactly this "optional submodule extends a core
  canonical page" shape in task 0080). `nexus.module` implements both
  hooks, delegating to the new `HiveInsightPanelBuilder` service —
  exactly mirroring `nanoprobe_hivelog_hive_view_panels()` →
  `SensorPanelBuilder`. Corrected this task's own acceptance-criteria
  wording after the fact — it was written before ADR-0099 existed and
  reads as if core controller changes were expected.
- **`ai_insights_enabled` is checked before querying `HiveInsight` at
  all**, not just before rendering — a deliberate choice: once disabled,
  the panel disappears immediately even if a stale `HiveInsight` row is
  still sitting in the database from before it was turned off. An
  opt-in feature that's currently off shouldn't keep showing old output.
- **Verdict severity maps onto Drupal core's three `messages--*`
  classes**: `act_now` → `error` (most urgent, despite not being an
  actual error — no fourth severity exists in core to reach for
  instead), `inspect_soon` → `warning`, `all_clear` → `status`. No new
  CSS component was worth inventing for exactly three fixed values.
- **Panel weight is 6** — between the hive's own fields (5) and the
  weight histogram (7)/`nanoprobe` Sensors panel (7.5)/Queen section
  (8), not appended after everything else. Per
  [[0083-ai-assisted-apiary-insights]]'s whole premise (one synthesised
  recommendation from many signals), the recommendation itself is the
  most actionable thing on the page and reads best first, ahead of the
  raw signal data (histogram, sensor readings) that fed into it — a
  deliberate placement decision, not a default.
- Verified live in a browser (real `cms2` hive page, real `HiveInsight`
  row), not just via kernel tests — confirmed panel position, all
  fields, and bullet-list signal rendering match expectations; then
  removed the manually-created test data.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0099-submodule-canonical-page-panel-hook]]
- Commits::
