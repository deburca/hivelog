---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: routing
created: 2026-09-22
completed: 2026-09-22
branch: feature/0103-nexus-cron-and-provider-calling
release:
depends-on: ["[[0100-nexus-in-process-ai-synthesis]]", "[[0102-nexus-scaffold-and-ai-provider-config]]"]
blocked-by: ["[[0102-nexus-scaffold-and-ai-provider-config]]"]
---
# Task: `nexus_cron()` + the three provider integration modes

## Context
Redoes [[0090-insight-agent-api-endpoints]] as in-process logic instead
of an HTTP write-back API, per [[0100-nexus-in-process-ai-synthesis]]
Decision §2/§3. For each `ai_insights_enabled` apiary/hive, builds
context via `collective.hive_context_builder`, calls the configured
provider, parses the result, writes `HiveInsight` directly — no bearer
token exchange with itself.

## Acceptance criteria
- [x] `nexus_cron()`: iterates enabled `AiProviderConfig` × opted-in
      hives, same due/window shape as `nanoprobe`'s retention cron
      (`SensorReadingRetentionService`) — a real service, not logic
      embedded in the hook, so it's directly unit/kernel-testable.
- [x] Three provider modes, checked in priority order (ADR-0100
      Decision §3): Drupal AI module (`moduleHandler()->moduleExists('ai')`),
      direct API call (`\Drupal::httpClient()`, Anthropic first), custom
      endpoint (user URL + auth header, fixed request/response contract
      matching `HiveInsight`'s fields).
- [x] Provider response parsing reuses
      [[0088-ai-insights-implementation]] §2's write-back validation
      logic (verdict/recommendation/signals/confidence shape) —
      `InsightAgentApiController::validateAndResolve()`'s logic, deleted
      in [[0101-collective-rescope-to-api-client]], is the right starting
      point to adapt here, not reinvent.
- [x] Secret resolution: `\Drupal::service('key.repository')->getKey($id)->getKeyValue()`
      called exactly once per provider call, never logged/cached/persisted.
- [x] Kernel tests for the cron service (provider calls mocked/faked —
      no real outbound HTTP in tests) and for each parsing/validation
      path.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **`nexus_cron()` delegates to `NexusInsightGenerator`**, mirroring
  `nanoprobe_cron()` → `SensorReadingRetentionService` exactly. For each
  enabled `AiProviderConfig`, iterates every `ai_insights_enabled` hive,
  builds context via `collective.hive_context_builder` (a direct service
  call, no HTTP), dispatches to the mode-appropriate
  `ProviderCallerInterface`, validates the response with
  `NexusResponseParser`, and writes `HiveInsight` directly.
- **Provider response contract is simpler than the old write-back body**:
  since `NexusInsightGenerator` already knows which hive/apiary it's
  generating for (it's iterating them itself), a provider's JSON
  response only needs `verdict`/`recommendation`/`signals`/`confidence`
  — no `hive`/`apiary`/`scope` fields to resolve, unlike
  `InsightAgentApiController::validateAndResolve()`'s original body
  shape. `NexusResponseParser` is the adapted subset of that logic.
- **All three `ProviderCallerInterface` modes return raw response
  text uniformly**, parsed/validated by the same `NexusResponseParser`
  step regardless of mode — including `custom_endpoint`, even though
  that endpoint is expected to return the structured JSON directly
  rather than free LLM text wrapping it. One shared validation path,
  not three.
- **`ai_module` mode never type-hints any class from the `ai` contrib
  module** — `AiModuleProviderCaller` only calls
  `\Drupal::service('ai.provider')` dynamically, after confirming
  `moduleHandler()->moduleExists('ai')`, specifically so `nexus` stays
  loadable on a site that never installed the `ai` module at all (a
  typed constructor argument sourced from `nexus.services.yml` would
  have required a Symfony optional-service reference (`@?ai.provider`)
  at minimum, and still risked a class-not-found if the module's code
  isn't even present on disk — the dynamic-lookup approach sidesteps
  both). This class file was checked against the real `drupal/ai`
  module's actual `AiProviderPluginManager`/`ChatInterface`/`ChatOutput`
  API (already present and enabled in `cms2`, alongside its Anthropic
  provider submodule) rather than guessed from memory.
- **Anthropic direct-API mode uses a fixed Phase 1 default model**
  (`claude-3-5-haiku-latest`, matching
  [[0087-ai-insights-hosting-and-privacy-model]]'s costed tier), not yet
  a configurable field on `AiProviderConfig` — OpenAI/Gemini and a
  configurable model field are documented follow-ups, not silently
  missing scope.
- **A single hive's failure never aborts a `nexus_cron()` run**: any
  `NexusProviderException` (call failure) or `\UnexpectedValueException`
  (response didn't parse/validate) is caught per-hive in
  `NexusInsightGenerator::generateForConfig()`, logged, and skipped.
  `AiProviderConfig.last_run` only updates when at least one insight was
  actually written for that config in that run — "last_run" means
  "produced something", not "was attempted".
- Kernel tests construct `NexusInsightGenerator` by hand with a small
  `FakeProviderCaller` test double (`returning()`/`throwing()`) standing
  in for all three caller slots, rather than going through the container
  — no real outbound HTTP anywhere in the suite. `NexusResponseParser`
  additionally gets its own dependency-free Unit test (first `Unit` test
  directory in this project's submodules).

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0100-nexus-in-process-ai-synthesis]],
  [[0087-ai-insights-hosting-and-privacy-model]]
- Supersedes (partially):: [[0090-insight-agent-api-endpoints]]
- Commits::
