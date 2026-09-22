---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-22
completed: 2026-09-22
branch: feature/0101-collective-rescope-to-api-client
release:
depends-on: ["[[0100-nexus-in-process-ai-synthesis]]"]
blocked-by:
---
# Task: Rescope `collective` to pure context gathering — `InsightAgent` → `ApiClient`

## Context
First implementation step of [[0100-nexus-in-process-ai-synthesis]]'s
Decision §1: `collective` narrows to pure, AI-agnostic context gathering.
Redoes the credential/API half of
[[0089-insight-agent-and-hive-insight-entities]],
[[0090-insight-agent-api-endpoints]], and
[[0091-insight-agent-management-ui]] — the `HiveInsight` half of those
tasks moves to the new `nexus` submodule in a follow-up task, not here.

`InsightAgent`'s hash-and-verify token shape is exactly right for this
direction (hivelog only ever verifies a presented token, never resends
it) — [[0100-nexus-in-process-ai-synthesis]] Decision §4 says this part
of 0089–0091's work is reused, not rebuilt. What changes is naming and
scope: a site-level credential whose only remaining job is authenticating
`GET` access to `HiveContextBuilder`'s output, independent of whether the
consumer is `nexus` (which actually reads context via a direct PHP
service call, not this HTTP route, per Decision §2), a beekeeper's own
script, or anything else. The insight *write-back* half is retired
outright, not moved — `nexus` writes `HiveInsight` itself, in the same
process that read the context, so no bearer-token write endpoint exists
anywhere after this task.

## Acceptance criteria
- [x] `InsightAgent` entity renamed to `ApiClient` (`api_client`,
      `collective_api_client`) — same fields (`label`, `token`,
      `enabled`, `last_run`, `uid`), same `generateToken()`/
      `verifyToken()`/`getPlainTextToken()` mechanism, unchanged.
- [x] `HiveInsight` entity, `HiveInsightAccessControlHandler`, and
      `HiveInsightTest` removed from `collective` entirely (relocated to
      `nexus` in a follow-up task, not stubbed out here).
- [x] `InsightAgentApiController` renamed `ApiClientApiController`; its
      `write()` method and every write-only helper
      (`validateAndResolve()`, `parseBody()`, `parseTimestamp()`) are
      deleted, not deprecated. Only `contexts()` remains, now also
      updating `ApiClient.last_run` on a successful read (the write
      endpoint's removal means read is now the only signal of "this
      credential is actually in use").
- [x] Routes renamed off "insight"/"hive-insight" framing:
      `collective.api_client.context` (`GET
      /hivelog/api/collective/context`) replaces both
      `collective.hive_insight.contexts` and `collective.hive_insight.write`
      — the write route is deleted, not redirected.
- [x] Full standard entity UI renamed to match
      (`entity.api_client.collection` at `/hivelog/api-clients`,
      `add_form`/`edit_form`/`delete_form`/canonical at
      `/hivelog/api-client/...`, `collective.api_client.regenerate_token`)
      — same `administer hivelog`-only add, same own/any pattern
      elsewhere, same one-time-plaintext-token UX
      ([[0091-insight-agent-management-ui]]'s own acceptance criteria,
      unchanged in substance).
- [x] Permissions renamed (`view own api client`, etc.); the four
      `hive insight` permissions removed entirely (redefined in `nexus`).
- [x] `hook_form_alter()`'s `ai_insights_enabled` disclosure text no
      longer names "insight agent" as the consumer — describes the data
      as available to whatever reads it via a collective API client
      credential, `nexus` (if installed) included, without collective
      claiming to know what happens to it downstream.
- [x] `collective.info.yml`'s description no longer claims to be "the
      intelligence" — collective only gathers/serves context now.
- [x] phpcs clean. Kernel tests renamed and updated to match (drop every
      write-endpoint-only assertion); full kernel + unit suite re-run
      against `cms2`, no regressions.

## Implementation notes
- **`contexts()` now updates `ApiClient.last_run` on every successful
  read**, not just on a successful write as `InsightAgent` did — a
  deliberate behavioural change, not an oversight, since the write
  endpoint that used to be the thing updating it no longer exists.
  `last_run` still means the same thing conceptually ("this credential
  is actually being used"), just observed at the read side now.
- **Local `cms2` dev DB needed manual reconciliation**, not just a code
  sync: Drupal's entity-rename handling has no built-in path for "this
  entity type id no longer exists, a new one replaces it" — `drush pmu`
  failed outright (`EntityTypeManager` couldn't resolve the now-deleted
  `insight_agent` definition mid-uninstall). Fixed by clearing the
  `entity.definitions.installed` key_value entries for
  `insight_agent`/`hive_insight`, dropping their tables directly via
  `drush sql:query`, then calling
  `\Drupal::entityDefinitionUpdateManager()->installEntityType()`
  directly for `api_client` (neither `drush updb` nor a hypothetical
  `entity:updates` command picked it up automatically — `updb` only
  reports "no pending updates" for hook-based schema changes, not new
  entity type installs from an already-enabled module; the entity
  definition update manager's own `getChangeList()` is what actually
  surfaces it). Purely a disposable-dev-DB concern, not a real deployment
  migration path — noted here since it's a genuinely new class of
  problem this project hadn't hit before (every prior task added new
  entity types to already-enabled modules cleanly; this one renamed one
  away).
- Full regression suite (636 tests / 9916 assertions, run against the
  post-rescope tree before `nexus` existed) confirmed the rescope broke
  nothing in `nanoprobe` or core `hivelog` — only the pre-existing
  geofield deprecation/notice noise.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0100-nexus-in-process-ai-synthesis]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Supersedes (partially):: [[0089-insight-agent-and-hive-insight-entities]],
  [[0090-insight-agent-api-endpoints]],
  [[0091-insight-agent-management-ui]]
- Commits::
