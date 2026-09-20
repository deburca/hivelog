---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: ui
created: 2026-09-20
branch: feature/0092-hive-apiary-ai-insight-panel
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]"]
blocked-by: ["[[0089-insight-agent-and-hive-insight-entities]]"]
---
# Task: "AI Insight" panel on the hive/apiary canonical pages

## Context
Read-only surfacing of `HiveInsight` where a beekeeper already looks,
per [[0088-ai-insights-implementation]] §4 — the explainability
requirement rendered, not just the verdict.

## Acceptance criteria
- [ ] `HiveController::view()` gains an "AI Insight" panel, rendered
      only when the hive's apiary has `ai_insights_enabled = TRUE`,
      showing the latest `HiveInsight` for that hive: `verdict`,
      `recommendation`, the `signals` list rendered via
      `SimpleBulletText::render()` (no new rendering mechanism),
      `confidence`, and how long ago it was `generated`.
- [ ] An insight older than roughly 48 hours is visibly flagged as
      stale ("last checked 3 days ago") rather than presented as current
      advice with no caveat — the agent may not have run, or failed.
- [ ] `ApiaryController::view()` gains the equivalent panel for
      apiary-scoped insights — ships now even though
      [[0088-ai-insights-implementation]] §5 defers actually *producing*
      apiary-scoped rows to Phase 2, so the panel simply stays empty
      until then rather than needing a second implementation later.
- [ ] A hive/apiary with `ai_insights_enabled = FALSE`, or with no
      `HiveInsight` yet, shows no panel at all (not an empty state) —
      this is an opt-in feature, and a beekeeper who hasn't turned it on
      shouldn't see a UI stub for it.
- [ ] No add/edit UI anywhere on this panel — strictly read-only,
      matching `HiveInsight`'s "machine-written only" design.
- [ ] Kernel tests: panel renders latest insight + signals list for a
      hive with one; hidden entirely when `ai_insights_enabled = FALSE`;
      hidden when no insight exists yet; staleness flag appears past the
      threshold; respects `ApiaryAccessTrait` (a beekeeper without
      access to the hive/apiary cannot see its insight).
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0004-custom-controllers-over-view-builders]]
- Commits::
