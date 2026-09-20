---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-09-20
---
# Project: AI-assisted apiary insights

## Goal
Synthesise multiple existing signals per hive — sensor trends (once
[[sensor-data-collection]] exists), inspection history, the seasonal
calendar's plan/status, queen status, and recent harvests/feeding — into
one explained, three-way daily recommendation: an action to take now
(e.g. add a super), a reason to inspect soon (e.g. possible swarm risk),
or an explicit "all clear, no inspection needed." See
[[0083-ai-assisted-apiary-insights]] (proposed) — deliberately
under-specified; most of this project is still open questions, not a
built design.

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
- Explicitly not yet decided (see Open questions):
  - The exact `HiveInsight` schema.
  - Where insights surface in the UI (a hive-page panel, a dashboard
    rollup, both).
  - Hive-scoped vs. apiary-scoped insights.
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
Three investigation/decision tasks — deliberately not implementation
tasks, since [[0083-ai-assisted-apiary-insights]] is intentionally
incomplete and, per its own Consequences, "cannot be picked up as an
implementation task yet":
- [[0084-ai-insights-hosting-and-privacy-decision]] — **done** — output
  is [[0087-ai-insights-hosting-and-privacy-model]] (hosted API by
  default, per-field data minimisation, new `Apiary.ai_insights_enabled`
  consent field).
- [[0085-sensor-less-insight-prototype]] — backlog (independent spike;
  no hard dependency, can run in parallel with/after 0084).
- [[0086-ai-insights-implementation-adr]] — backlog, **no longer
  blocked** now that 0084/0087 have landed — the actual "proper
  follow-up ADR" [[0083-ai-assisted-apiary-insights]] deferred to; real
  implementation tasks (entity, agent endpoint, UI) get broken out from
  *that* ADR once it lands, the same way
  [[0074-sensor-data-ingestion-architecture]] led to
  [[0076-sensor-device-and-reading-entities]] onward.

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
- `HiveInsight` schema and UI surface — deliberately not designed in
  [[0083-ai-assisted-apiary-insights]]; first real design work once the
  above two are answered.
- Should apiary-scoped insights exist too (e.g. "register renewal due,"
  synthesising apiary-level calendar/inventory context), or is this
  hive-only to start? Not addressed yet.
- Does this want [[sensor-data-collection]] to have shipped real pilot
  data first, or is inspection/calendar-only reasoning (no sensors at
  all) worth prototyping independently and sooner? [[0083-ai-assisted-apiary-insights]]
  argues the value doesn't strictly require sensors, but a real
  prototype would clarify whether that's true in practice.

## Related decisions
- [[0083-ai-assisted-apiary-insights]] (proposed — the overall shape;
  hosting/privacy resolved below, schema/UI still open)
- [[0087-ai-insights-hosting-and-privacy-model]] (accepted — hosting
  choice, data minimisation, consent field)
- [[0074-sensor-data-ingestion-architecture]] (the device-token
  provisioning pattern this reuses; §7's threshold alerts this sits
  alongside, not replaces)
- [[0015-apiary-location-privacy]] (the precedent
  [[0087-ai-insights-hosting-and-privacy-model]]'s data-minimisation
  table satisfies)
- [[0006-contrib-dependency-policy]] (written for contrib PHP modules;
  [[0087-ai-insights-hosting-and-privacy-model]] makes the conscious
  call that a hosted API is still the right choice despite that
  minimal-footprint spirit, for reasons specific to this feature)
- [[0025-seasonal-calendar-and-hive-action-tracking]] (one of the several
  existing data sources an insight would reason over)
