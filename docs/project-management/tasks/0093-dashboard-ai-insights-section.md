---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: dashboard
created: 2026-09-20
branch: feature/0093-dashboard-ai-insights-section
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]", "[[0092-hive-apiary-ai-insight-panel]]"]
blocked-by: ["[[0089-insight-agent-and-hive-insight-entities]]"]
---
# Task: Dashboard "AI Insights" section + `InsightAgent` staleness alert

## Context
The concrete answer to the user's original "no inspection currently
needed" example — a positive, visible confirmation that monitoring ran
and found nothing wrong, which nothing else in hivelog currently
provides — per [[0088-ai-insights-implementation]] §4. Kept as its own
dashboard section, **never merged** into "Needs attention": that queue's
row shape (chip + title + a Done/Ignored report action) doesn't fit a
reasoned recommendation or the "all clear" state, per
[[0083-ai-assisted-apiary-insights]] §3.

## Acceptance criteria
- [ ] A new `DashboardController` section, rendered only for a user with
      at least one `ai_insights_enabled` apiary — hidden entirely
      otherwise, not an empty state.
- [ ] Lists hives with `act_now`/`inspect_soon` verdicts as short rows
      (title + one-line recommendation + link to the hive's own "AI
      Insight" panel from [[0092-hive-apiary-ai-insight-panel]]).
- [ ] A single summary line for the rest: "N hives all clear today."
- [ ] **`InsightAgent` health check**: a stale `InsightAgent.last_run`
      (proposed threshold: more than ~36h since the last successful
      run, i.e. the daily job missed a day) becomes a new rule in the
      *existing* deterministic "Needs attention" queue — reusing
      [[0074-sensor-data-ingestion-architecture]] §7's device-offline
      pattern rather than inventing a second alerting mechanism. This is
      the one piece of this task that *does* belong in "Needs attention"
      (it's a plain threshold rule about the agent's own health, not a
      reasoned recommendation) rather than the new AI Insights section.
- [ ] Kernel tests mirroring `DashboardTest`'s existing pattern: section
      hidden with no opted-in apiaries; correct rows for
      act_now/inspect_soon hives; correct "N all clear" count; the
      `InsightAgent` staleness rule fires/doesn't fire correctly and
      appears in the merged "Needs attention" queue alongside the
      existing alert sources.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0057-dashboard-information-architecture]],
  [[0074-sensor-data-ingestion-architecture]]
- Commits::
