---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0088: AI insights — implementation (`HiveInsight`, `InsightAgent`, API, UI)

## Status
accepted. This is the "proper follow-up ADR"
[[0083-ai-assisted-apiary-insights]] explicitly deferred everything
concrete to, now unblocked by
[[0087-ai-insights-hosting-and-privacy-model]] (hosting/privacy) and
[[0085-sensor-less-insight-prototype]] (validated that sensor-less
reasoning works). Real implementation task files get broken out from
this ADR's Decision section as a separate follow-on step, the same way
[[0074-sensor-data-ingestion-architecture]] led to
[[0076-sensor-device-and-reading-entities]] onward — not part of this
document.

## Context
Three prior decisions converge here and each leaves this ADR a specific
job:
- [[0083-ai-assisted-apiary-insights]] fixed the *shape*: additive to
  the existing threshold alerts, an external daily-batch agent
  provisioned like a `SensorDevice`, a new entity (not an overload of
  `SensorReading` or the alert queue), mandatory explainability,
  advisory-only, and — this ADR's job — the exact schema and UI surface
  it deliberately left open.
- [[0087-ai-insights-hosting-and-privacy-model]] fixed *what may cross
  the boundary*: a hosted API by default, a per-field data-minimisation
  table (no raw location, no names, derived sensor summaries only,
  inspection notes/harvest data excluded from the default payload), and
  a new `Apiary.ai_insights_enabled` opt-in consent field — this ADR's
  job is to wire that consent field into an actual enforcement point,
  not just declare it.
- [[0085-sensor-less-insight-prototype]] validated that
  inspection/calendar-only reasoning already produces genuinely useful,
  well-cited output, using the real `HiveInspection`/`CalendarAction`/
  `Queen` structured fields — but flagged two gaps this ADR must carry
  forward as pre-launch checks, not drop: output quality was validated
  with a large model, not the cheaper tier
  [[0087-ai-insights-hosting-and-privacy-model]] recommends starting at;
  and no scenario tested ambiguous/sparse data.

### What was still genuinely open
1. **`HiveInsight`'s real schema** — [[0083-ai-assisted-apiary-insights]]
   §3 only sketched `hive`/`apiary`, `verdict`, recommendation text,
   cited signals, a timestamp.
2. **How the external agent both reads a hive's context and writes an
   insight back** — [[0083-ai-assisted-apiary-insights]] §2 said "reuses
   the device-token auth pattern" but never specified the actual
   request/response shapes, and — a gap that ADR didn't fully close —
   never specified how the agent *reads* a hive's context in the first
   place. `SensorDevice`'s token authenticates a *write* (a sensor
   pushing a reading); an insight agent needs to *read* aggregated
   context for potentially many hives before it can write anything back.
3. **Whether the insight-agent credential is itself apiary/hive-scoped
   like `SensorDevice`, or something different** — a single external
   process generates insights for every opted-in apiary in one daily
   run; it isn't physically tied to one hive the way a sensor is.
4. **Hive-scoped vs. apiary-scoped `HiveInsight`s** — left open by both
   [[0083-ai-assisted-apiary-insights]] and the project file.
5. **Where an insight surfaces in the UI**, and specifically how the
   "all clear" verdict — a genuinely new *positive* state nothing else
   in hivelog has — gets shown as a confirmation rather than disappearing
   the way a resolved alert does.
6. **Enforcement of `Apiary.ai_insights_enabled`** — where exactly does
   this gate anything?

## Decision

### 1. Two new entities: `InsightAgent` (the credential) and `HiveInsight` (the record)
Deliberately **not** the same shape as `SensorDevice`/`SensorReading`
([[0074-sensor-data-ingestion-architecture]] §1), because the two
things being modelled are different: a `SensorDevice` is one physical
node permanently tied to one hive or apiary; an insight-generating
agent is a single external software process that, once a day, reasons
about *every* opted-in hive/apiary in one run. Forcing it into
`SensorDevice`'s per-hive/per-apiary `scope` shape would be a category
error.

**`InsightAgent`** (site-level credential — normally exactly one row):
- `label` (`string`, required) — e.g. "Daily Insight Agent".
- `token` (`string`, server-generated, stored hashed, shown once) — same
  mechanism as `SensorDevice.token`
  ([[0074-sensor-data-ingestion-architecture]] §3).
- `enabled` (`boolean`, default `TRUE`).
- `last_run` (`timestamp`, optional, updated on every successful batch
  completion) — mirrors `SensorDevice.last_seen`'s role: powers an
  "insight agent hasn't run in N days" health check (§6 below), the same
  way a stale sensor is detected today.
- Standard `uid`/`created`/`changed`.
- **No `apiary`/`hive`/`scope` field** — this is the deliberate
  departure from `SensorDevice`'s shape, and the point of giving it a
  different name rather than reusing that entity type.

**`HiveInsight`** (one row — one daily recommendation for one hive or
apiary):
- `apiary` (`entity_reference` → `apiary`, required).
- `hive` (`entity_reference` → `hive`, required only when `scope =
  hive`) — same "exactly one of two references, gated by a sibling
  field" validation shape as `SensorDevice`
  ([[0076-sensor-device-and-reading-entities]]).
- `scope` (`list_string`: `apiary` | `hive`, required) — **reuses the
  same duality `CalendarAction` and `SensorDevice` already established**,
  resolving open question §4. Phase 1 (§8 below) only ever produces
  `scope = hive` rows; the field exists from day one so apiary-scoped
  insights are a cheap future addition, not a schema change, mirroring
  how `CalendarAction`/`HiveActionLog` gained apiary scope later via
  [[0027-apiary-vs-hive-scoped-calendar-items]] without redesigning the
  hive-scoped half.
- `verdict` (`list_string`, code-defined per
  [[0003-code-defined-entity-schema]]: `act_now` | `inspect_soon` |
  `all_clear`, required) — the exact taxonomy
  [[0085-sensor-less-insight-prototype]] tested.
- `recommendation` (`string`, required) — the short imperative sentence
  (e.g. "Add a super — honey stores have reached 'abundant'...").
- `signals` (`string_long`, required) — the cited data points behind the
  verdict, **stored and rendered as a bulleted list reusing the existing
  `- `-prefix convention and `SimpleBulletText::render()` utility**
  already used for `CalendarAction.description`
  ([[0025-seasonal-calendar-and-hive-action-tracking]]) — no new
  rendering mechanism, and satisfies
  [[0083-ai-assisted-apiary-insights]] §4's "must name the specific
  signals" requirement as a real, visible list rather than a paragraph.
- `confidence` (`list_string`, optional: `high` | `medium` | `low`) —
  [[0085-sensor-less-insight-prototype]]'s scenarios all produced this
  naturally; capturing it structurally satisfies
  [[0083-ai-assisted-apiary-insights]]'s "uncertainty surfaced, not
  hidden" framing.
- `context_snapshot` (`string_long`, optional) — a copy of exactly what
  the agent's context read (§2) returned for this hive at generation
  time. Optional, best-effort, but strongly recommended: it's a strict
  subset of data already minimised per
  [[0087-ai-insights-hosting-and-privacy-model]] and already sent
  off-site, so storing a copy inside hivelog adds no new privacy
  exposure, while making a disputed or confusing recommendation fully
  auditable after the fact.
- `generated` (`timestamp`, required, agent-supplied) — when the agent
  produced this, distinct from `created` (server-side, automatic) —
  mirrors `SensorReading.recorded`/`created`
  ([[0074-sensor-data-ingestion-architecture]] §1), cheap to keep and
  gives the same small audit value if a write is ever delayed.
- No `uid`/owner, no add/edit form, no human-facing create path — same
  "machine-written only" shape as `SensorReading`.

**Retention**: at one row per hive per day, `HiveInsight` accumulates
roughly 365 rows/hive/year — two orders of magnitude smaller than
`SensorReading`'s 15–60-minute cadence
([[0074-sensor-data-ingestion-architecture]] §5). **No retention/rollup
job is needed** at this volume for the foreseeable future; do not build
a [[0082-sensor-reading-retention-and-rollup]]-style task for this
entity without a real reason to revisit that.

### 2. Two endpoints: context read, then insight write — both behind the `InsightAgent` token
Closes open question §2 — [[0083-ai-assisted-apiary-insights]] never
fully specified how the agent gets its input, only how it writes output.

**Read** — `GET /hivelog/api/hive-insights/contexts` (optional `?hive=`
filter for testing a single hive), `Authorization: Bearer <InsightAgent
token>`. Returns one context object per hive belonging to an apiary
where `Apiary.ai_insights_enabled = TRUE` — **this is the actual
enforcement point for that consent field** (open question §6): a hive
under an apiary that hasn't opted in simply never appears in this
response, so the external agent has no way to reason about it even if
misconfigured. Each context object is exactly the shape
[[0085-sensor-less-insight-prototype]] validated and
[[0087-ai-insights-hosting-and-privacy-model]]'s data-minimisation table
already governs — this is the single place that table gets enforced in
code:
```json
{
  "hive_id": 42,
  "current_week": 22,
  "calendar_status": {"due_this_week": [...], "overdue": [...], "recently_done": [...]},
  "inspections": [
    {"week": 22, "population": "strong", "brood_pattern": "good", "honey_stores": "abundant", "pollen_stores": "adequate", "queen_seen": true, "queen_cells": false, "varroa_check": false, "varroa_count": null, "supers": 1, "weight_kg": 41.2}
  ],
  "queen": {"queen_year": 2025, "status": "active"}
}
```
No hive/apiary *name* (id only — hivelog's own UI resolves ids back to
names when rendering a `HiveInsight`, per
[[0087-ai-insights-hosting-and-privacy-model]]'s name-never-leaves
rule), no location, no `HiveInspection.notes`/`action_taken` free text,
no `HarvestYield`/`InventoryUsage` data, no raw sensor readings — only
the structured `HiveInspection` fields
[[0085-sensor-less-insight-prototype]] tested. Once
[[sensor-data-collection]] has shipped, this same endpoint is where a
derived sensor-trend summary gets added — an additive field on this
response, not a new endpoint or a redesign.

**Write** — `POST /hivelog/api/hive-insights`, same bearer token,
accepting the fields in §1 (`hive` or `apiary` + `scope`, `verdict`,
`recommendation`, `signals`, `confidence`, `generated`,
`context_snapshot`). `201` with the created id on success; `401`
missing/invalid token; `403` disabled agent; `422` unknown `verdict`,
missing required fields, or a `hive`/`apiary` that doesn't resolve;
`404` if the referenced hive's apiary doesn't have
`ai_insights_enabled = TRUE` — server-side defence-in-depth even though
the external agent should never have gotten this hive from the read
endpoint in the first place.

Both endpoints are `_access: 'TRUE'` at the route level with the
controller performing its own bearer-token check, exactly like
[[0074-sensor-data-ingestion-architecture]] §3's ingestion route — and
the same CSRF reasoning applies unchanged: a stateless, credentialed
request has no ambient browser session to forge, so the bearer-token
check is this route's protection, not a documented gap in
[[0018-csrf-and-safe-http-methods]].

### 3. Access control: reading a `HiveInsight` back out is unchanged from every other entity
Resolves open question §6's other half. `HiveInsight` resolves through
`ApiaryAccessTrait::resolveApiary()` exactly like `SensorDevice` does —
`apiary` directly, or → `hive` → `apiary`. A beekeeper without access to
a hive/apiary cannot see its insights, full stop, matching
[[0019-authorisation-model]] with **zero exception** — the only new
mechanism in this whole feature is the `InsightAgent` token that
authenticates the *write* and *context-read* paths; everything a human
beekeeper does (viewing an insight, deciding whether
`ai_insights_enabled` is on) goes through hivelog's existing,
unmodified permission model.

### 4. UI surface
Resolves open question §5.
- **Hive canonical page**: a new "AI Insight" panel (only rendered when
  the hive's apiary has `ai_insights_enabled = TRUE`) showing the latest
  `HiveInsight` — verdict, recommendation, the `signals` list rendered
  via `SimpleBulletText::render()`, confidence, and how long ago it was
  generated. An insight older than roughly 48 hours (the agent hasn't
  run, or failed) is visibly flagged as stale ("last checked 3 days
  ago") rather than presented as current advice with no caveat.
- **Dashboard**: a new section, separate from "Needs attention" (never
  merged into it — that queue's row shape doesn't fit a reasoned
  recommendation or the positive "all clear" state, per
  [[0083-ai-assisted-apiary-insights]] §3) and only rendered for a user
  with at least one `ai_insights_enabled` apiary. Lists hives with
  `act_now`/`inspect_soon` verdicts as short rows (title + one-line
  recommendation + link), plus a single summary line for the rest —
  "N hives all clear today." This is the concrete answer to the user's
  original ask: a positive, visible confirmation that monitoring ran and
  found nothing wrong, which nothing else in hivelog currently provides.
- **Apiary canonical page**: the same panel shape as the hive page, only
  ever populated once a Phase 2 apiary-scoped insight actually exists
  (§8) — the panel itself can ship in Phase 1 and simply stay empty
  until then.
- **`InsightAgent` health**: a stale `last_run` (mirroring
  `SensorDevice.last_seen`) becomes a new rule in the *existing*
  deterministic "Needs attention" queue
  ([[0074-sensor-data-ingestion-architecture]] §7's device-offline
  pattern, reused) — the reliable, rule-based mechanism monitors the
  health of the less-deterministic one, rather than the AI layer being
  asked to monitor itself.

### 5. Hive-scoped only for Phase 1
Resolves open question §4 concretely: the schema supports both scopes
(§1), but Phase 1 only ever produces `scope = hive` rows — every
scenario [[0085-sensor-less-insight-prototype]] validated, and the
user's own three original examples, were all hive-level. Apiary-scoped
insights (e.g. synthesising apiary-level calendar/inventory context —
"CBR registration renewal due") are deferred until a concrete use case
exists, the same sequencing [[0025-seasonal-calendar-and-hive-action-tracking]]
→ [[0027-apiary-vs-hive-scoped-calendar-items]] already followed for the
seasonal calendar.

### 6. Pre-launch validation — carried forward from the prototype, not dropped
Per [[0085-sensor-less-insight-prototype]]'s own flagged gaps, Phase 1
does not ship until both are actually checked, not merely noted:
- **Model-tier validation**: re-run the prototype's three scenarios (or
  equivalents) against the actual production model tier
  ([[0087-ai-insights-hosting-and-privacy-model]] recommends starting at
  Haiku-class) — the prototype used a larger model and does not by
  itself prove the cost-optimised tier produces equally good output.
- **Ambiguous/sparse-data behaviour**: test at least one hive with a
  near-empty history (e.g. one inspection ever) and one with genuinely
  conflicting signals, and confirm the agent hedges appropriately
  (per its system prompt's own instruction to do so) rather than
  overclaiming — the prototype's three scenarios were all deliberately
  clear-cut and didn't exercise this path.

### 7. Retention
Covered in §1 — no rollup/purge job needed at this entity's volume.

### 8. Phasing
- **Phase 1**: `InsightAgent` + `HiveInsight` entities, both endpoints
  (§2), access control (§3), the hive-page panel + dashboard section
  (§4), hive-scoped only (§5) — gated on §6's two checks actually
  passing before shipping to real data.
- **Phase 2**: apiary-scoped insights, once a concrete use case exists.
- **Phase 3 (not designed here)**: fold [[sensor-data-collection]]'s
  derived sensor-trend summaries into the context-read endpoint's
  response (§2) once that project has real pilot data — additive, not a
  redesign.

## Consequences
- Positive: closes every open question [[0083-ai-assisted-apiary-insights]]
  deliberately left for this ADR, using patterns already proven
  elsewhere in the module (the `scope` duality, code-defined
  vocabularies, `SimpleBulletText` bulleted rendering, the
  device-token-but-route-level-access-bypass shape, reusing the
  existing "Needs attention" queue to monitor the new system's own
  health) rather than inventing new mechanisms. The context-read
  endpoint is the single, auditable enforcement point for
  `ai_insights_enabled` and for every data-minimisation rule
  [[0087-ai-insights-hosting-and-privacy-model]] decided — a future
  privacy-rule change touches one place, not a scattered set of ad hoc
  checks. The dashboard's "all clear" summary is a genuinely new,
  positive UX pattern hivelog hasn't had before, directly answering the
  user's original ask.
- Negative / trade-offs: two new entities and two new authenticated
  endpoints is real surface area for a feature whose core value (a
  non-deterministic recommendation) still can't be verified by reading
  fixed code — `context_snapshot` mitigates this but doesn't eliminate
  it. `InsightAgent` being a single site-level credential (unlike
  `SensorDevice`'s per-device tokens) means a compromised or leaked
  token exposes every opted-in apiary's context at once, not just one
  device's data — a real, accepted trade-off given there's normally
  exactly one such agent, but worth flagging explicitly rather than
  glossing over. Phase 1 is not ready to ship until §6's two checks
  actually pass — this ADR does not by itself constitute permission to
  start building without doing them.
- Follow-up tasks: to be broken out from this ADR's Decision section as
  numbered implementation tasks under [[ai-apiary-insights]], the same
  way [[0074-sensor-data-ingestion-architecture]] led to
  [[0076-sensor-device-and-reading-entities]] through
  [[0082-sensor-reading-retention-and-rollup]] — not part of this
  document.
