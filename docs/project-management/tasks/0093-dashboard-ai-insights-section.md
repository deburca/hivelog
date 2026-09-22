---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: dashboard
created: 2026-09-20
completed: 2026-09-22
branch: feature/0093-dashboard-ai-insights-section
release:
depends-on: ["[[0102-nexus-scaffold-and-ai-provider-config]]", "[[0092-hive-apiary-ai-insight-panel]]"]
blocked-by: ["[[0102-nexus-scaffold-and-ai-provider-config]]"]
---
# Task: Dashboard "AI Insights" section + `AiProviderConfig` staleness alert

> **Dependency repointed 2026-09-22** per
> [[0100-nexus-in-process-ai-synthesis]]: the staleness health check
> below now targets `AiProviderConfig.last_run` (`nexus`,
> [[0102-nexus-scaffold-and-ai-provider-config]]), not
> `InsightAgent.last_run`/[[0089-insight-agent-and-hive-insight-entities]]
> — same threshold rule, same "Needs attention" queue placement, applied
> to the renamed/relocated entity that actually runs the daily job now.

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
- [x] A new `DashboardController` section, rendered only for a user with
      at least one `ai_insights_enabled` apiary — hidden entirely
      otherwise, not an empty state.
- [x] Lists hives with `act_now`/`inspect_soon` verdicts as short rows
      (title + one-line recommendation + link to the hive's own "AI
      Insight" panel from [[0092-hive-apiary-ai-insight-panel]]).
- [x] A single summary line for the rest: "N hives all clear today."
- [x] **`AiProviderConfig` health check**: a stale `AiProviderConfig.last_run`
      (threshold: more than 36h since the last successful run, i.e. the
      daily job missed a day) becomes a new rule in the *existing*
      deterministic "Needs attention" queue — reusing
      [[0074-sensor-data-ingestion-architecture]] §7's device-offline
      pattern rather than inventing a second alerting mechanism. This is
      the one piece of this task that *does* belong in "Needs attention"
      (it's a plain threshold rule about the config's own health, not a
      reasoned recommendation) rather than the new AI Insights section.
- [x] Kernel tests mirroring `DashboardTest`'s existing pattern: section
      hidden with no opted-in apiaries; correct rows for
      act_now/inspect_soon hives; correct "N all clear" count; the
      `AiProviderConfig` staleness rule fires/doesn't fire correctly and
      appears in the merged "Needs attention" queue alongside the
      existing alert sources.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **A new hook, `hook_hivelog_dashboard_sections()`, was added to core**
  (`hivelog.api.php` + one small addition to `DashboardController::view()`)
  — the first genuinely new addition to ADR-0099's mechanism since it was
  written. Unlike `hook_hivelog_needs_attention_alerts()` (feeds into an
  existing merged pass) or the hive/apiary panel hooks (existing
  canonical pages), there was no existing "whole new dashboard section"
  extension point to reuse — ADR-0099 itself anticipated exactly this
  ("collective's future AI Insight panel reuses this exact mechanism"),
  so this is a straightforward application of its established pattern to
  a new page region, not a deviation from it. `nexus_hivelog_dashboard_sections()`
  delegates to the new `DashboardAiInsightsBuilder` service, exactly
  mirroring `nexus_hivelog_hive_view_panels()` → `HiveInsightPanelBuilder`.
- **"All clear today" only counts a fresh `all_clear` insight** — reuses
  `HiveInsightPanelBuilder::STALE_THRESHOLD_SECONDS` (48h)'s exact
  reasoning: a stale all-clear isn't a real current confirmation, so a
  hive whose latest insight has gone stale is silently excluded from the
  count rather than counted as reassurance it can no longer back up. A
  hive with no insight at all is excluded from both the count and the
  action-row list — this task's own acceptance criteria doesn't ask for
  a third "not yet monitored" bucket, so one wasn't invented.
- **The `AiProviderConfig` staleness threshold (36h) is deliberately
  shorter than the insight-staleness threshold (48h)** — different
  questions: "is the daily pipeline itself healthy" (36h — should be
  flagged sooner) vs. "is this specific recommendation still fresh
  enough to act on" (48h). Documented explicitly in
  `ProviderHealthAlertCollector`'s own docblock so the two constants
  aren't mistaken for a copy-paste inconsistency.
- **A config that has never run doesn't false-alarm** — mirrors
  `SensorAlertCollector::checkDeviceOffline()`'s exact reasoning for a
  device that's never reported: a freshly-provisioned `AiProviderConfig`
  hasn't had its first `nexus_cron()` pass yet, and alerting immediately
  on every new config would false-alarm before the daily job has even
  had a chance to run once.
- Verified live end-to-end in a real browser session against `cms2`'s
  actual dashboard (not just kernel tests) — the "Needs attention" queue
  correctly showed "AI provider stale — Stale Test Config — Last run 1
  day 16 hours ago" alongside real seasonal/low-stock alerts, and the
  new "AI Insights" section correctly showed one `act_now` row and "1
  hive all clear today." (excluding the `act_now` hive from that count);
  then all manually-created test data was removed.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0057-dashboard-information-architecture]],
  [[0074-sensor-data-ingestion-architecture]],
  [[0099-submodule-canonical-page-panel-hook]]
- Commits::
