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
- Explicitly not yet decided (see Open questions) — none of these should
  be guessed by picking up an implementation task prematurely:
  - Which model/service does the reasoning, and where it's hosted.
  - How apiary location and other potentially sensitive data are
    handled if a third-party hosted API is ever used
    ([[0015-apiary-location-privacy]] is the precedent this has to
    satisfy, not just resemble).
  - The exact `HiveInsight` schema.
  - Where insights surface in the UI (a hive-page panel, a dashboard
    rollup, both).
  - Cost model / who pays for API calls at what cadence.
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
- [[0084-ai-insights-hosting-and-privacy-decision]] — backlog (do first;
  the ADR's single most gating open question; output is a written
  decision, not code)
- [[0085-sensor-less-insight-prototype]] — backlog (independent spike;
  no hard dependency, can run in parallel with 0084)
- [[0086-ai-insights-implementation-adr]] — backlog, blocked on 0084 —
  the actual "proper follow-up ADR"
  [[0083-ai-assisted-apiary-insights]] deferred to; real implementation
  tasks (entity, agent endpoint, UI) get broken out from *that* ADR once
  it lands, the same way [[0074-sensor-data-ingestion-architecture]] led
  to [[0076-sensor-device-and-reading-entities]] onward.

## Open questions
- Model/hosting choice: a self-hosted open model (no data ever leaves
  hivelog's own infrastructure, but real ops burden and likely lower
  reasoning quality) vs. a hosted third-party API (better quality, real
  per-call cost, and the privacy question below becomes load-bearing).
- Privacy: per [[0083-ai-assisted-apiary-insights]] §6, if a hosted API
  is used, what exactly is safe to send off-site? Apiary GPS/location is
  the clearest example of data that must not travel raw, per
  [[0015-apiary-location-privacy]]'s existing precedent — but inspection
  notes, hive names, and yield figures may carry their own sensitivity a
  beekeeper hasn't been asked about yet. Needs an explicit opt-in model,
  not an assumption that existing data-sharing consent (there isn't
  any, today) extends to this.
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
- [[0083-ai-assisted-apiary-insights]] (proposed — the only decision made
  so far; almost everything else is an open question above)
- [[0074-sensor-data-ingestion-architecture]] (the device-token
  provisioning pattern this reuses; §7's threshold alerts this sits
  alongside, not replaces)
- [[0015-apiary-location-privacy]] (the precedent any third-party data
  sharing has to satisfy)
- [[0006-contrib-dependency-policy]] (written for contrib PHP modules;
  needs a conscious decision about whether/how its spirit extends to an
  external paid API, not an assumption)
- [[0025-seasonal-calendar-and-hive-action-tracking]] (one of the several
  existing data sources an insight would reason over)
