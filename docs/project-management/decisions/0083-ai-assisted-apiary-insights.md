---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-09-20
supersedes:
---
# ADR-0083: AI-assisted apiary insights (future phase)

## Status
proposed — deliberately under-specified. A beekeeper-facing feature this
speculative needs its own follow-up round of decisions (model/hosting
choice, cost, privacy handling) before any implementation task is
opened. Recorded now so a later decision doesn't drift from what's
already been built in
[[0074-sensor-data-ingestion-architecture]]/
[[0075-sensor-hardware-and-connectivity-selection]], and so the
explainability/human-in-the-loop requirements are locked in before any
implementation pressure could erode them.

## Context
[[0074-sensor-data-ingestion-architecture]]'s Context already cited
Apiculture.ai's four-stage management loop: continuous monitoring →
anomaly detection → targeted inspection → intervention. That ADR's Phase
2 (§7) implements the first two stages as **fixed thresholds on one
signal at a time** — a weight drop, an offline device, an out-of-range
temperature. The user's ask goes further: synthesise **several signals
at once** (weight trend, temperature, season, inspection history,
calendar status) into one explained, three-way recommendation per hive:

1. an action to take now (e.g. "add a super" — a resourcing/capacity
   call),
2. a reason to go inspect soon (e.g. "possible swarm risk" — a
   targeted-inspection trigger),
3. or an explicit **"all clear, no inspection needed"** — a genuinely
   new state hivelog doesn't have anywhere today. Everywhere else in the
   module, "nothing to report" just means an empty queue; there is no
   positive confirmation that monitoring is actually working and found
   nothing wrong.

This is a materially different kind of feature from everything else in
hivelog so far:
- Every existing alert mechanism (seasonal calendar, low stock, the
  sensor thresholds in [[0074-sensor-data-ingestion-architecture]] §7)
  is a **fixed, deterministic rule over one signal** — auditable by
  reading the code, no external dependency, no cost per evaluation. An
  agent reasoning over several signals at once to produce a
  natural-language recommendation is neither fixed nor (without extra
  work) fully auditable, and very likely calls an external service with
  a real per-call cost.
- It needs **more data than sensors alone provide**. Weight/temperature
  trend is one input among several — inspection history
  (`HiveInspection`), the seasonal plan and what's already been reported
  (`CalendarAction`/`HiveActionLog`), queen status
  (`Queen`/`QueenObservation`), and recent harvests/feeding
  (`HarvestYield`/`InventoryUsage`) are all context a beekeeper would
  themselves weigh when deciding "does this hive need me today." This
  means the feature's value does **not** strictly require
  [[sensor-data-collection]] to ship first — a sensor-less hive with
  inspection and calendar history alone is already a plausible, if
  thinner, input — though it clearly gets richer once sensor data
  exists.

### Scoping questions
1. **Does this replace or sit alongside the Phase 2 threshold alerts?**
   Alongside. The fixed-threshold rules in
   [[0074-sensor-data-ingestion-architecture]] §7 stay as the always-on,
   dependency-free baseline every beekeeper gets regardless of whether
   they ever enable this feature. An AI layer is additive — a richer,
   optional synthesis on top, not a replacement for something cheap and
   reliable already decided.
2. **Where does it run?** Not inside hivelog's own PHP request cycle —
   calling an LLM synchronously on a page load is the wrong shape for a
   Drupal module. The natural fit reuses the shape
   [[0074-sensor-data-ingestion-architecture]] already established for
   sensor ingestion: an external process (a scheduled job, run **daily
   per hive/apiary**, not per reading) reads a hive's aggregated context
   through hivelog's normal, access-controlled read path, calls whatever
   model/service does the reasoning, and writes its result back through
   a narrow, authenticated **write** endpoint — reusing the device-token
   auth pattern from that ADR's §3 (an "insight agent" is provisioned
   exactly like a `SensorDevice`: a token, a small config), rather than
   inventing a second, different credential mechanism.
3. **What does it write back?** Not a `SensorReading` (that's a numeric
   metric, not a reasoned recommendation), and not directly into the
   existing "Needs attention" queue either — that queue's row shape
   (chip + title + a Done/Ignored report action tied to a specific
   `CalendarAction`/`InventoryItem`) doesn't fit a free-text explanation
   or the new "all clear" positive state. A new entity — provisionally
   `HiveInsight` — is the more honest fit: `hive` (or `apiary`), a
   `verdict` (`act_now` / `inspect_soon` / `all_clear`), a short
   recommendation string, the reasoning/citations behind it, a generated
   timestamp. Left to a real implementation ADR to fully specify —
   recorded here only to establish that it is a new, distinct entity,
   not an overload of an existing one.
4. **Explainability and trust.** A beekeeper acting (or choosing not to
   act) on a real recommendation about a living colony needs to see
   *why*, not just a verdict — every `HiveInsight` must name the
   specific signals that drove it (e.g. "weight dropped 2.1 kg
   overnight; no rain recorded; last inspection 9 days ago showed queen
   cells starting"), not a bare "inspect soon." This is non-negotiable,
   not a nice-to-have: it is the only thing that keeps this feature
   auditable at all, given its output can't be verified by reading fixed
   code the way every other alert in the module can.
5. **Human in the loop, always.** An insight is advisory only. It never
   marks a `CalendarAction`/`HiveActionLog` done, never places an
   inventory order, never sends a message on the beekeeper's behalf — the
   beekeeper always acts, mirroring the module's existing pattern where
   even a one-click "Report Done" is a real, deliberate click, never an
   automatic side effect of something else.
6. **Privacy.** If any hosted, third-party model/API is ever used, an
   apiary's precise location must not be part of whatever leaves the
   building — [[0015-apiary-location-privacy]] already treats this as
   sensitive for photo EXIF data; the same concern applies at least as
   much to an outbound API call carrying structured hive data. This ADR
   does **not** resolve *how* (a self-hosted model, vs. a hosted API with
   location stripped/generalised, vs. explicit per-apiary opt-in) — that
   choice needs its own follow-up before any implementation begins,
   flagged here so it isn't skipped later under feature-shipping
   pressure.
7. **Cost and cadence.** Reasoning on every sensor reading (every 15–60
   minutes, per [[0074-sensor-data-ingestion-architecture]] §5) would be
   wasteful and expensive for no real benefit — a beekeeper wants a daily
   read, not a running commentary. A once-daily batch per hive (or
   apiary, for apiary-scoped signals) is the assumed cadence; still open
   whether every hive is evaluated daily regardless of whether anything
   changed, or only when new data actually arrived since the last run.

## Decision (recommended, and deliberately incomplete)
- **Additive, not a replacement**, for the Phase 2 threshold alerts (§1).
- **An external agent, not in-process** — provisioned like a
  `SensorDevice`, reusing
  [[0074-sensor-data-ingestion-architecture]] §3's token-based auth
  pattern for both reading a hive's aggregated context and writing its
  resulting insight back (§2).
- **A new `HiveInsight` entity** (provisional name/shape) — not an
  overload of `SensorReading` or the "Needs attention" queue (§3).
- **Mandatory explainability** — every insight carries the signals that
  produced it, never a bare verdict (§4).
- **Advisory only, never auto-acting** (§5).
- **Privacy handling is an explicit prerequisite, not solved here** (§6)
  — no implementation task should be opened until this is answered.
- **Daily batch cadence**, not per-reading (§7).

Everything else — the exact `HiveInsight` schema, where it surfaces in
the UI (a new panel on the hive page? a dashboard rollup tile showing
"N hives need attention today, M all clear"? both?), which model/service
actually does the reasoning, and the full privacy-handling design — is
intentionally left to a proper follow-up ADR once
[[sensor-data-collection]] has real pilot data and the privacy question
(§6) has a real answer, rather than guessed now.

## Consequences
- Positive: gives a concrete, honest shape to a feature the user
  explicitly wants to keep in view, without over-committing on details
  (model choice, hosting, exact schema) that aren't genuinely decidable
  yet. Reuses the sensor-ingestion auth pattern rather than inventing a
  second credential mechanism. The explainability and human-in-the-loop
  requirements are locked in now, before implementation could erode
  them under time pressure. The explicit "all clear" verdict answers a
  real, previously-unaddressed gap: a beekeeper currently has no way to
  positively confirm "monitoring is working and found nothing wrong,"
  only the absence of an alert.
- Negative / trade-offs: this is the first hivelog feature whose output
  is not fully deterministic/auditable by reading the code, and likely
  the first to depend on an external paid service — both are real
  departures from the module's established character.
  [[0006-contrib-dependency-policy]]'s minimal-dependency spirit was
  written for contrib PHP modules, not third-party APIs, and needs a
  conscious decision about whether/how it extends to this case, not an
  assumption either way. Left substantially unspecified on purpose,
  which means it **cannot** be picked up as an implementation task yet —
  that's deliberate, not an oversight.
- Follow-up tasks: none opened yet. Tracked under a new
  [[ai-apiary-insights]] project, kept separate from
  [[sensor-data-collection]] since the two have different dependencies,
  cadences, and privacy surfaces, even though the AI layer clearly
  benefits from sensor data once it exists.
