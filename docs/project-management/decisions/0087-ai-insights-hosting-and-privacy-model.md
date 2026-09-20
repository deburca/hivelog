---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0087: AI insights — hosting choice, data minimisation & consent model

## Status
accepted. Resolves [[0084-ai-insights-hosting-and-privacy-decision]], the
single most gating open question flagged in
[[0083-ai-assisted-apiary-insights]]. Unblocks
[[0086-ai-insights-implementation-adr]].

## Context
[[0083-ai-assisted-apiary-insights]] §6/§7 left two things explicitly
unresolved: which model/hosting approach produces the daily per-hive
insight, and — if a hosted third-party API is involved — exactly what
data is safe to send off-site, under what consent. Both had to be
answered with real numbers and a real data classification, not guessed.

### Self-hosted vs. hosted API
A self-hosted open model needs real inference hardware (GPU, or a slow
CPU-bound response) kept running or cold-started for a workload that, per
[[0083-ai-assisted-apiary-insights]] §7, only needs to run **once a day
per hive**. Hivelog's whole existing character is a single self-hosted
Drupal site run by one beekeeper with no dedicated ops team
([[0006-contrib-dependency-policy]]'s minimal-footprint spirit, the
"hobbyist-scale tool" framing already used elsewhere in this project) —
standing up and maintaining GPU infrastructure for a once-daily,
per-hive batch job is a real mismatch between ops burden and benefit,
unless a beekeeper already happens to run spare GPU/home-lab hardware
they want to put to use instead of paying a third party at all.

A hosted API needs no infrastructure of its own, gets materially better
reasoning quality without a hardware investment, and — given the very
low call volume this feature actually needs — costs very little (see
below). The real cost of the hosted path isn't money; it's the data
leaving the building, which is a privacy question, not a technical one.

### Cost, with real numbers
Anthropic's current published API pricing (per 1M tokens, snapshot
2026-09-20 — re-verify before implementation, pricing changes over
time):

| Model | Input | Output |
|---|---|---|
| Claude Haiku 4.5 | $1.00 | $5.00 |
| Claude Sonnet 5 | $2.00 | $10.00 |

A daily per-hive insight request is small: a well-summarised context
(recent sensor trend as derived deltas, not raw readings; recent
inspection/calendar status; a fixed system prompt) is generously
~3,000 input tokens; a short recommendation + cited reasoning is
generously ~300 output tokens.

- **Haiku 4.5**: (3,000 ÷ 1,000,000 × $1.00) + (300 ÷ 1,000,000 × $5.00)
  ≈ **$0.0045/hive/day** → ~$1.64/hive/year → **~$6.57/year for the
  4-hive Vand Værk apiary this module already tracks real data for**,
  or ~$33/year at a serious 20-hive hobbyist scale.
- **Sonnet 5** (a higher-quality tier, if Haiku's reasoning proves
  insufficient once tested): (3,000 ÷ 1,000,000 × $2.00) +
  (300 ÷ 1,000,000 × $10.00) ≈ **$0.009/hive/day** → ~$13/year for 4
  hives, ~$66/year for 20.
- Both estimates ignore prompt caching, which would meaningfully cut the
  shared system-prompt portion of the input cost further since it's
  identical across every hive and every day.

**Finding: cost is not the deciding factor at hivelog's scale, at either
model tier.** The real trade-off is entirely about what data crosses the
boundary and under what consent — which is what the rest of this ADR
actually decides.

### Data classification
What a daily insight's context could plausibly include, and what's
actually safe to send to a hosted third-party API, by field:

| Data | Sensitivity | Decision |
|---|---|---|
| Apiary GPS/location (`Apiary.geofield`) | High — [[0015-apiary-location-privacy]] already treats this as sensitive for photo EXIF | **Never sent.** Not needed anyway — the existing seasonal-calendar system already reasons about season using the ISO week number alone, with no location input; an insight can do the same. |
| Hive/apiary name | Low-medium — could carry personal/address info depending on how a beekeeper named it | **Never sent as text.** Send only the entity's internal id; HiveLog's own UI resolves the id back to the real name when rendering the response, so the real name never needs to leave at all. |
| Sensor readings | Low, once aggregated | Sent as **derived summaries** (e.g. "weight trend last 7 days: +0.3 kg/day"), never raw per-reading rows — smaller payload and lower sensitivity together. |
| Inspection notes (free text) | Medium — unpredictable beekeeper-written content | **Excluded from the default payload.** A beekeeper who wants notes included must opt in per-field, not get it by default from a blanket "insights on" toggle. |
| Calendar/action status | Low — structural (what's due/done) | Sent as-is. |
| Queen breed/observations | Low | Sent as-is. |
| Harvest yield / inventory usage | Medium — can reveal commercial quantities/income for a beekeeper who sells honey | **Excluded from the default payload**, same reasoning as inspection notes — worth [[0086-ai-insights-implementation-adr]] revisiting per-field once a real prototype shows whether it's actually needed for useful recommendations. |

### Consent model
Hivelog has no data-sharing consent mechanism today — this is the first
feature that needs one, so it needs an explicit design rather than an
assumption that enabling the feature implies consent to everything it
could theoretically use.

## Decision
- **Default path: a hosted API**, not a self-hosted model — for the
  reasoning-quality and zero-ops-burden reasons above, and because the
  real cost at hivelog's scale (§ above) does not justify the
  self-hosting trade-off. **Self-hosting remains a fully supported
  alternative**, not foreclosed: [[0083-ai-assisted-apiary-insights]]
  §2 already made the write-back contract provider-agnostic (any
  process, self-hosted or hosted, posts the same structured result back
  through the same token-authenticated endpoint), so choosing
  self-hosting later is a deployment choice, not a redesign. This ADR
  does not commit to a specific vendor — the architecture stays as
  provider-agnostic as the ingestion side already is
  ([[0075-sensor-hardware-and-connectivity-selection]]'s equivalent
  stance on not locking to one sensor vendor).
- **Start at the cheapest capable tier** (Haiku-class) and only move up
  if [[0085-sensor-less-insight-prototype]] or real usage shows the
  smaller model's reasoning is insufficient — cost headroom is large at
  either tier, so this is a quality decision, not a budget one.
- **Data minimisation is mandatory, not a future hardening pass**: never
  send raw location; send opaque entity ids instead of names, resolved
  back to real names only inside hivelog's own UI; send sensor data as
  derived summaries, never raw readings; exclude inspection notes and
  harvest/inventory figures from the default payload.
- **New consent field**: `Apiary.ai_insights_enabled` (boolean, default
  `FALSE`) — opt-in, not opt-out, mirroring the existing
  `Apiary.visibility` field's shape (a per-apiary `list_string`/boolean
  toggle, not a global site-wide setting) so a beekeeper with several
  apiaries can enable this selectively. The daily agent job never runs
  for an apiary where this is unset. Turning it on shows a one-time,
  explicit disclosure listing exactly what will be sent (the table
  above), not something buried in a settings page.

## Consequences
- Positive: answers [[0083-ai-assisted-apiary-insights]]'s most gating
  question with real numbers rather than a guess — cost turns out not to
  be the constraint, which reframes the remaining work around data
  handling and consent, where it belongs. The self-hosting path stays
  open without needing a second design later. The name-never-leaves
  pattern (send ids, resolve locally) is a reusable technique for future
  outbound integrations too, not just this one.
- Negative / trade-offs: excluding inspection notes and harvest/
  inventory data from the default payload may weaken recommendation
  quality for scenarios that genuinely need them (e.g. "should this hive
  get supered" arguably wants recent harvest history) — deliberately
  conservative for now; [[0086-ai-insights-implementation-adr]] can
  revisit specific fields once real data shows whether the trade-off
  actually costs useful recommendations. Introduces the module's first
  data-sharing consent field, which is new surface area (a new
  disclosure UI, not just a checkbox) that nothing else in hivelog has
  needed before.
- Follow-up tasks: unblocks
  [[0086-ai-insights-implementation-adr]], which must incorporate this
  decision directly (model tier, the data-minimisation table, and the
  `ai_insights_enabled` consent field) into the full `HiveInsight`
  schema and agent write-back contract.
