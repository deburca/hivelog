---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: routing
created: 2026-09-20
branch: feature/0091-insight-agent-management-ui
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]", "[[0090-insight-agent-api-endpoints]]"]
blocked-by: ["[[0090-insight-agent-api-endpoints]]"]
---
# Task: `InsightAgent` provisioning UI + `ai_insights_enabled` consent toggle

## Context
Without this, nobody can actually turn the feature on or get a
credential to build the external agent against — mirrors
[[0078-sensor-device-configuration-descriptor]]'s role for
`SensorDevice`. `administer hivelog`-gated, since an `InsightAgent`
token is a single, high-trust, site-wide credential
([[0088-ai-insights-implementation]] Consequences flags this explicitly:
a leaked token exposes every opted-in apiary's context at once).

## Acceptance criteria
- [ ] Standard entity add/edit/delete UI for `InsightAgent`
      (`administer hivelog` only, per
      [[0089-insight-agent-and-hive-insight-entities]]'s permissions),
      following the existing `HivelogListBuilder` pattern.
- [ ] Token shown once, immediately after creation or regeneration, with
      a clear "this will not be shown again" notice — mirrors
      `SensorDevice`'s own token-display convention
      ([[0074-sensor-data-ingestion-architecture]] §1).
- [ ] "Regenerate token" action: invalidates the previous token
      immediately (a subsequent request to either endpoint from
      [[0090-insight-agent-api-endpoints]] using the stale token gets
      `401`).
- [ ] The `InsightAgent` canonical/edit page states the two endpoint
      URLs plainly (context-read, write-back) so whoever builds the
      external agent has everything needed in one place.
- [ ] `Apiary.ai_insights_enabled` exposed on the existing apiary edit
      form, defaulting `FALSE`. Turning it on shows a one-time, explicit
      disclosure of exactly what will be sent — the
      [[0087-ai-insights-hosting-and-privacy-model]] data table in
      plain language, not buried in a tooltip — before the toggle takes
      effect.
- [ ] Kernel tests: token shown once and not recoverable in plaintext
      afterward; regenerate invalidates the old token (a follow-up API
      call with the stale token is rejected); `ai_insights_enabled`
      toggle persists and defaults correctly; disclosure copy appears
      when enabling.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0019-authorisation-model]]
- Commits::
