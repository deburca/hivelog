---
type: project
tags: [hivelog/project]
status: active
target:
created: 2026-09-20
---
# Project: AI-assisted apiary insights

## Goal
Synthesise multiple existing signals per hive — inspection history, the
seasonal calendar's plan/status, queen status, and (once
[[sensor-data-collection]] exists) sensor trends — into one explained,
three-way daily recommendation: an action to take now (e.g. add a
super), a reason to inspect soon (e.g. possible swarm risk), or an
explicit "all clear, no inspection needed." Design is now largely
settled: [[0083-ai-assisted-apiary-insights]] (the shape),
[[0087-ai-insights-hosting-and-privacy-model]] (hosting/privacy),
[[0085-sensor-less-insight-prototype]] (validated the approach works),
and [[0088-ai-insights-implementation]] (the real schema/API/UI design)
— all landed 2026-09-20. Next step is breaking
[[0088-ai-insights-implementation]] into implementation task files, the
same way [[0074-sensor-data-ingestion-architecture]] became
[[0076-sensor-device-and-reading-entities]] onward.

## Scope
- In scope, decided (per [[0083-ai-assisted-apiary-insights]]):
  - Additive to, not a replacement for, the existing fixed-threshold
    "Needs attention" alerts.
  - Runs as an external, scheduled agent (daily per hive/apiary), not
    in-process inside a Drupal request.
  - Provisioned and authenticated the same way a `SensorDevice` is
    ([[0074-sensor-data-ingestion-architecture]] §3) — reuses that
    pattern rather than inventing a second credential mechanism.
  - Writes to a new entity (provisionally `HiveInsight`), not into
    `SensorReading` or the existing alert queue.
  - Every insight must cite the specific signals behind it — no bare
    verdicts.
  - Advisory only: never marks anything done, never places an order,
    never acts on the beekeeper's behalf.
- In scope, decided (per [[0087-ai-insights-hosting-and-privacy-model]],
  resolving [[0084-ai-insights-hosting-and-privacy-decision]]):
  - A hosted API by default (not self-hosted) — reasoning quality and
    zero ops burden win, and real cost turned out negligible at
    hivelog's scale (~$6.57/year for the actual 4-hive Vand Værk
    apiary at Haiku-tier pricing). Self-hosting stays a fully supported
    alternative, not foreclosed.
  - Data minimisation is mandatory: apiary GPS/location is never sent;
    hive/apiary names are never sent (opaque ids only, resolved back to
    real names inside hivelog's own UI); sensor data goes as derived
    summaries, never raw readings; inspection notes and harvest/
    inventory figures are excluded from the default payload.
  - A new opt-in consent field, `Apiary.ai_insights_enabled` (default
    `FALSE`), mirroring `Apiary.visibility`'s per-apiary shape — the
    daily job never runs for an apiary where this isn't explicitly
    turned on.
- In scope, decided (per [[0088-ai-insights-implementation]]):
  - Two new entities: `InsightAgent` (a site-level credential — not
    apiary/hive-scoped like `SensorDevice`, since one external process
    serves every opted-in apiary) and `HiveInsight` (the actual
    recommendation, reusing the `CalendarAction`/`SensorDevice`
    apiary/hive `scope` duality).
  - Two endpoints behind the `InsightAgent` token: a context-read
    endpoint (the actual enforcement point for
    `ai_insights_enabled` and every data-minimisation rule) and the
    insight write-back.
  - Hive-scoped only for Phase 1 — apiary-scoped deferred to Phase 2.
  - UI: a hive-page panel (with staleness flagging), and a dashboard
    section kept separate from "Needs attention" with a positive
    "N hives all clear today" summary line.
  - Two explicit, non-optional pre-launch checks carried forward from
    [[0085-sensor-less-insight-prototype]]: re-validate at the actual
    Haiku-class production model tier, and test ambiguous/sparse
    inspection histories.
- Out of scope entirely for now: any auto-acting behaviour (auto-adding
  a super to inventory, auto-marking a calendar action done, sending
  notifications on the beekeeper's behalf) — see
  [[0083-ai-assisted-apiary-insights]] §5. This is a hard boundary, not a
  "maybe later" — if it's ever revisited, it needs its own ADR with its
  own explicit reasoning, not a quiet expansion of this one.

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
All three investigation/decision tasks are done:
- [[0084-ai-insights-hosting-and-privacy-decision]] — **done** — output
  is [[0087-ai-insights-hosting-and-privacy-model]] (hosted API by
  default, per-field data minimisation, new `Apiary.ai_insights_enabled`
  consent field).
- [[0085-sensor-less-insight-prototype]] — **done** — finding:
  inspection/calendar-only reasoning works well for all three verdicts,
  including genuine multi-signal value a fixed threshold rule can't
  produce; two real gaps flagged as pre-launch checks (untested at the
  actually-intended cheaper model tier; untested on ambiguous/sparse
  data), not as blockers.
- [[0086-ai-insights-implementation-adr]] — **done** — output is
  [[0088-ai-insights-implementation]], the real `HiveInsight`/
  `InsightAgent` schema, API, access control, and UI design.

**2026-09-22 architecture pivot**: [[0100-nexus-in-process-ai-synthesis]]
splits AI synthesis out of `collective` into a new `nexus` submodule,
calling providers in-process via cron rather than through an external
agent + write-back API. This partially supersedes
[[0089-insight-agent-and-hive-insight-entities]] through
[[0091-insight-agent-management-ui]] (their `InsightAgent`/`HiveInsight`
design), which stay `done` as a historical record but are no longer the
active design — see each task's own note. Replaced by
[[0101-collective-rescope-to-api-client]] through
[[0104-ai-provider-config-management-ui]] below, **all four now done**
(2026-09-22) — the `nexus` rework itself is complete.
[[0092-hive-apiary-ai-insight-panel]] through
[[0094-ai-insights-prelaunch-validation]] are unaffected in substance
(still read `HiveInsight`, just relocated to `nexus`).
[[0092-hive-apiary-ai-insight-panel]]'s `depends-on` has been repointed
at 0102 and is done; 0093/0094's still name 0089/0090 and should be
repointed when picked up.

Implementation, current (post-ADR-0100):
- [[0101-collective-rescope-to-api-client]] — **done** (2026-09-22):
  narrowed `collective` to context-gathering only
- [[0102-nexus-scaffold-and-ai-provider-config]] — **done** (2026-09-22):
  `nexus` module scaffolded, `AiProviderConfig` + relocated `HiveInsight`
- [[0103-nexus-cron-and-provider-calling]] — **done** (2026-09-22):
  `nexus_cron()` + all three provider integration modes wired
- [[0104-ai-provider-config-management-ui]] — **done** (2026-09-22):
  `AiProviderConfig` add/edit/delete UI, mode-conditional form fields,
  `key_select` integration, canonical page's live AI-module-status check
- [[0092-hive-apiary-ai-insight-panel]] — **done** (2026-09-22):
  read-only "AI Insight" panel on the Hive/Apiary canonical pages, via
  the same ADR-0099 hook mechanism `nanoprobe`'s Sensors panel uses —
  no core controller changes needed
- [[0093-dashboard-ai-insights-section]] — **done** (2026-09-22):
  dashboard "AI Insights" section (new `hook_hivelog_dashboard_sections()`
  — the first genuine extension of ADR-0099's mechanism since it was
  written) plus the `AiProviderConfig` staleness alert, folded into the
  existing "Needs attention" queue rather than a new mechanism
- [[0094-ai-insights-prelaunch-validation]] — backlog, **the release
  gate**: not another design task, running [[0088-ai-insights-implementation]]
  §6's two required checks against the real, nexus-driven pipeline.
  Nothing ships to real beekeepers, and `ai_insights_enabled` is not safe
  to recommend turning on, until this one passes.

Implementation, superseded 2026-09-22 (kept for history, not the active
plan — see above):
- ~~[[0089-insight-agent-and-hive-insight-entities]]~~ — done, superseded
- ~~[[0090-insight-agent-api-endpoints]]~~ — done, superseded
- ~~[[0091-insight-agent-management-ui]]~~ — done, superseded

## Open questions
- ~~Model/hosting choice: a self-hosted open model vs. a hosted
  third-party API.~~ **Resolved 2026-09-20** in
  [[0087-ai-insights-hosting-and-privacy-model]]: hosted API by
  default (cost turned out negligible at hivelog's scale — the deciding
  factor was never money), self-hosting kept as a fully supported
  alternative.
- ~~Privacy: what exactly is safe to send off-site?~~ **Resolved
  2026-09-20** in [[0087-ai-insights-hosting-and-privacy-model]]: a full
  per-field table (location and names never sent; sensor data as
  derived summaries; inspection notes and harvest/inventory excluded by
  default), plus a new opt-in `Apiary.ai_insights_enabled` consent
  field. Worth a second look once [[0086-ai-insights-implementation-adr]]
  is drafted: excluding inspection notes/harvest data by default may
  turn out to cost real recommendation quality, per that ADR's own
  Consequences.
- ~~`HiveInsight` schema and UI surface.~~ **Resolved 2026-09-20** in
  [[0088-ai-insights-implementation]] §1/§4 — see Scope above.
- ~~Should apiary-scoped insights exist too, or is this hive-only to
  start?~~ **Resolved 2026-09-20** in [[0088-ai-insights-implementation]]
  §5: schema supports both, Phase 1 ships hive-scoped only, apiary-scoped
  deferred to Phase 2 until a concrete use case exists.
- ~~Does this want [[sensor-data-collection]] to have shipped real pilot
  data first, or is inspection/calendar-only reasoning worth
  prototyping independently and sooner?~~ **Resolved 2026-09-20** in
  [[0085-sensor-less-insight-prototype]]: yes, worth pursuing
  independently — sensor-less reasoning already produces genuinely
  useful, well-cited output across all three verdicts. Not fully
  closed, though: the prototype used a large model, not the
  cost-optimised tier [[0087-ai-insights-hosting-and-privacy-model]]
  actually recommends starting at, and didn't test ambiguous/sparse
  data — both carried forward as pre-launch checks in
  [[0088-ai-insights-implementation]] §6.
- **Still genuinely open**: [[0088-ai-insights-implementation]] §6's two
  pre-launch checks are *specified*, not yet *run* — Phase 1
  implementation tasks can be broken out and built, but nothing ships to
  real data until both actually pass. Don't treat "the ADR is accepted"
  as "the checks are done."

## Related decisions
- [[0083-ai-assisted-apiary-insights]] (proposed — the overall shape;
  every open question it deliberately left has now been resolved by the
  three decisions below)
- [[0087-ai-insights-hosting-and-privacy-model]] (accepted — hosting
  choice, data minimisation, consent field)
- [[0085-sensor-less-insight-prototype]] — recorded as a task, not an
  ADR, but functions as one: the evidence base for treating sensor-less
  reasoning as in-scope for Phase 1
- [[0088-ai-insights-implementation]] (accepted — the real
  `HiveInsight`/`InsightAgent` schema, API, access control, UI design;
  incorporates both decisions above directly)
- [[0074-sensor-data-ingestion-architecture]] (the device-token
  provisioning pattern `InsightAgent` follows the spirit of; §7's
  threshold alerts this sits alongside, not replaces)
- [[0015-apiary-location-privacy]] (the precedent
  [[0087-ai-insights-hosting-and-privacy-model]]'s data-minimisation
  table satisfies)
- [[0006-contrib-dependency-policy]] (written for contrib PHP modules;
  [[0087-ai-insights-hosting-and-privacy-model]] makes the conscious
  call that a hosted API is still the right choice despite that
  minimal-footprint spirit, for reasons specific to this feature)
- [[0025-seasonal-calendar-and-hive-action-tracking]] /
  [[0027-apiary-vs-hive-scoped-calendar-items]] (the hive-then-apiary
  scoping sequence [[0088-ai-insights-implementation]] §5 deliberately
  repeats)
