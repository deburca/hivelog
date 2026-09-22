---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-21
supersedes: "[[0083-ai-assisted-apiary-insights]] (partial — see Status), [[0088-ai-insights-implementation]] (partial — see Status)"
---
# ADR-0100: `nexus` — in-process AI synthesis, split from `collective`

## Status
accepted. Partially supersedes
[[0083-ai-assisted-apiary-insights]] §2 and
[[0088-ai-insights-implementation]] §2/§3's "external agent + write-back
API" design — everything else those ADRs decided (the three-way verdict
shape, mandatory explainability, human-in-the-loop, the data-minimisation
table, hive-scoped-only for Phase 1) is unchanged. Triggers redoing
[[0089-insight-agent-and-hive-insight-entities]] through
[[0091-insight-agent-management-ui]] against this ADR, the same way
[[0098-nanoprobe-collective-locutus-submodule-split]] triggered redoing
[[0076-sensor-device-and-reading-entities]].

## Context
`collective` (built across 0089–0091) currently bundles two genuinely
separable things: **gathering** structured hive/apiary context
(`HiveContextBuilder`, the context-read API) and **AI credentialing +
verdict write-back** (`InsightAgent`, `HiveInsight`, the write-back API,
the AI-specific management UI). Some beekeepers want the first without
ever wanting the second — structured data to look at and act on
themselves — mirroring exactly the reasoning that justified splitting
`nanoprobe`/`collective`/`locutus` apart in the first place
([[0098-nanoprobe-collective-locutus-submodule-split]]).

Separately: a beekeeper choosing how their AI-assisted verdicts actually
get generated wants real options — a locally-configured Drupal AI
module, a direct call to a major provider (Anthropic, OpenAI, Google
Gemini, ...), or a fully custom endpoint. **This only makes sense if
hivelog itself calls the provider** — "use the Drupal AI module if
configured" is meaningless for an external, non-Drupal agent process,
since that module only exists inside a Drupal request. This is a real
revision of [[0083-ai-assisted-apiary-insights]] §2's design (an external
process polls a context endpoint and POSTs a verdict back), not just a
new module boundary — that ADR chose an external process specifically to
avoid an in-process, *page-load-synchronous* LLM call, but a
cron-triggered batch job is not a page load, and the original objection
doesn't actually apply to it.

Three questions were put directly to the user and answered:
1. **In-process calling, not external-agent** — `nexus`'s own cron job
   builds context, calls the configured provider, writes `HiveInsight`
   directly. The external-agent + write-back API model is retired, not
   kept as a parallel option.
2. **The Drupal AI module is an optional, soft integration**, not a
   required dependency — consistent with
   [[0006-contrib-dependency-policy]]'s minimal-dependency policy, and
   the same `moduleHandler()`-detected pattern already used for
   `nanoprobe`/`collective`'s own hook integrations
   ([[0099-submodule-canonical-page-panel-hook]]).
3. **`nexus` takes a hard dependency on the `Key` contrib module**
   (drupal.org/project/key) for `AiProviderConfig`'s own credential
   storage — see Decision §2 and Implementation notes. A deliberate,
   narrow exception to [[0006-contrib-dependency-policy]]'s spirit, not
   a reversal of it: that policy has held for `hivelog` core and every
   submodule so far because nothing genuinely needed a contrib
   dependency yet; storing a real, billable third-party API key
   securely is exactly the kind of concrete, specific need that policy
   was always written to allow an exception for ("a concrete, in-module
   use" + "why core/CSS/a small patch is insufficient"), and "don't roll
   your own crypto" already governs every other credential this project
   has built (`SensorDevice`/`InsightAgent` tokens reuse core's
   `password_hash()` rather than a custom hash). The dependency is
   scoped to `nexus` alone — `hivelog` core, `nanoprobe`, and
   `collective` stay exactly as dependency-free as they are today, since
   only a beekeeper who opts into `nexus` picks it up.

## Decision
1. **`collective` narrows to pure, AI-agnostic context gathering.**
   Keeps `HiveContextBuilder` and the context-read API. Loses
   `HiveInsight`, `InsightAgent`, the write-back API, and the AI-specific
   management UI — these move to, or are replaced within, the new
   `nexus` submodule (§2).
2. **New `nexus` submodule**, depending on both `hivelog` core and
   `collective` — reading context via a direct PHP service call
   (`collective.hive_context_builder`), not an HTTP round-trip, since
   both live in the same Drupal codebase. Owns:
   - **`HiveInsight`** (relocated, schema unchanged) — the verdict
     entity, exactly as [[0088-ai-insights-implementation]] §1 specified.
   - **A new `AiProviderConfig` entity — not a renamed `InsightAgent`.**
     `InsightAgent`'s token was deliberately one-way-hashed, correct for
     *verifying* a credential presented *to* hivelog. `AiProviderConfig`
     needs the opposite: a credential hivelog sends *to* a third-party
     provider, which must be recoverable, not just verifiable. **It does
     not store or encrypt that credential itself** — it stores which
     mode (§3), a provider identifier (direct-API mode), an optional
     custom endpoint URL, and a `key` field referencing a `Key` module
     entity (via `Key`'s own `key_select` form element). The actual
     secret is resolved only at call time,
     `\Drupal::service('key.repository')->getKey($id)->getKeyValue()`,
     never persisted by `nexus` in any form. This fully delegates the
     "how is this actually encrypted/stored" question to `Key` — a
     beekeeper picks environment variable, file, or `Key`+`Encrypt`
     database storage through `Key`'s own admin UI, per their own
     deployment, and `nexus` never has to know or decide which. See
     Implementation notes.
   - **`nexus_cron()`**: for each `ai_insights_enabled` apiary/hive,
     builds context, calls the configured provider (§3), parses the
     result, writes `HiveInsight` directly. No bearer token, no
     write-back API — the write happens in the same PHP process that
     read the context.
3. **Three provider integration modes**, checked in this priority order
   at call time:
   1. **Drupal AI module**, if installed and configured — detected via
      `\Drupal::moduleHandler()->moduleExists('ai')`, an optional soft
      dependency (§ Context, point 2).
   2. **Direct provider API calls** — `nexus`'s own minimal HTTP
      client(s). Anthropic first (the model tier
      [[0087-ai-insights-hosting-and-privacy-model]] already priced out
      and recommended starting at); OpenAI/Gemini are straightforward
      follow-ups against the same internal interface, not required for
      Phase 1.
   3. **Custom endpoint** — a user-supplied URL + auth header; `nexus`
      POSTs a fixed request shape (system prompt + context object) and
      expects a fixed response shape (verdict/recommendation/signals/
      confidence, matching `HiveInsight`'s own fields directly) — a real,
      documented contract, not a vague "bring your own integration."
4. **The context-read API stays, decoupled from AI-specific naming and
   auth.** Its access credential is renamed and re-scoped, not
   discarded — `InsightAgent`'s hash-and-verify shape is exactly right
   for *this* direction (hivelog only ever verifies a presented token,
   never resends it), so this part of 0089–0091's work is reused within
   `collective` as a general-purpose API-access credential, independent
   of whether the consumer is `nexus`, a beekeeper's own script, or
   anything else. The route path/name moves away from "hive-insight"
   framing to reflect the broader purpose.
5. **`Apiary.ai_insights_enabled` is unchanged** — stays on core
   `Apiary`, continues to gate both the context-read API and `nexus`'s
   own processing. Not split into separate "data sharing" vs. "AI
   processing" consent — no real use case demands that distinction yet;
   revisit only if one actually emerges, not speculatively now.

## Consequences
- Positive: a beekeeper who wants structured data without AI installs
  `collective` alone and never carries `nexus`'s schema — the same
  genuine "optional means optional" property
  [[0098-nanoprobe-collective-locutus-submodule-split]] established for
  the first three submodules. In-process, cron-triggered calling is
  materially simpler to deploy than requiring a beekeeper to host a
  separate external process — no separate script, no separate credential
  to keep in sync. Three real provider options (not a single hard-coded
  vendor) avoids the exact lock-in [[0083-ai-assisted-apiary-insights]]
  and [[0087-ai-insights-hosting-and-privacy-model]] were already
  written to avoid. Most of 0089–0091's work is renamed/relocated, not
  wasted — the hash-and-verify credential shape, the management-UI
  pattern, and `HiveContextBuilder` all carry over directly.
- Negative / trade-offs: real rework of already-shipped, already-pushed
  work — not free, and the same kind of "we changed our mind" cost
  [[0098-nanoprobe-collective-locutus-submodule-split]] itself
  represented for `nanoprobe`'s entities. `nexus` is hivelog's first
  module with a real contrib dependency — a deliberate, narrow, scoped
  exception to [[0006-contrib-dependency-policy]] (§ Context, point 3),
  not a precedent that other submodules should now feel free to follow
  without their own equally concrete justification. `nexus`'s own HTTP
  client(s) for direct-API mode are new code this project didn't need
  before (hivelog has made outbound HTTP calls nowhere else yet).
- Follow-up tasks: redo [[0089-insight-agent-and-hive-insight-entities]]
  (as `AiProviderConfig` + relocated `HiveInsight`),
  [[0090-insight-agent-api-endpoints]] (as `nexus_cron()` + the
  provider-calling logic, dropping the write-back API), and
  [[0091-insight-agent-management-ui]] (as `AiProviderConfig`'s own
  management UI) against this ADR. `collective`'s existing context-read
  API gets a lighter rename/rescope pass, not a full redo.

## Implementation notes
- **Credential-at-rest storage: `Key` module, resolved by reference, not
  by value.** `nexus.info.yml` declares a hard `dependencies: [key:key]`.
  `AiProviderConfig`'s own field is a plain string holding a `Key`
  config-entity id (using `Key`'s `key_select` element on the form, so
  the beekeeper picks from whatever keys already exist in their site's
  `Key` configuration, or creates a new one through `Key`'s own UI
  without `nexus` needing to build any credential-entry form of its
  own). `nexus` calls
  `\Drupal::service('key.repository')->getKey($id)->getKeyValue()`
  exactly once, immediately before making the provider request, and
  never logs, caches, or persists the returned value anywhere. This was
  chosen over a custom `sodium`/`openssl`-backed field specifically to
  avoid hivelog owning key-management correctness itself — a leaked
  Anthropic/OpenAI key is a real-stakes outcome, not a hypothetical one,
  and `Key` is the audited, ecosystem-standard answer other Drupal AI
  tooling (including the Drupal AI module's own provider plugins,
  mode 1) already converges on.
- `nexus`'s direct-API HTTP client(s) should use Drupal core's own HTTP
  client service (`\Drupal::httpClient()`, Guzzle-backed) — no new HTTP
  library dependency needed for that part.
