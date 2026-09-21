---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: routing
created: 2026-09-20
completed: 2026-09-21
branch: feature/0090-insight-agent-api-endpoints
release:
depends-on: ["[[0089-insight-agent-and-hive-insight-entities]]"]
blocked-by: ["[[0089-insight-agent-and-hive-insight-entities]]"]
---
# Task: Context-read + insight write-back API endpoints

## Context
The two endpoints an external insight-generating agent needs, per
[[0088-ai-insights-implementation]] §2 — a real gap
[[0083-ai-assisted-apiary-insights]] §2 left unaddressed (it only ever
specified writing output, never reading input). Both are the actual
enforcement point for `Apiary.ai_insights_enabled` and every
[[0087-ai-insights-hosting-and-privacy-model]] data-minimisation rule.

**Lives in `collective`**, per
[[0098-nanoprobe-collective-locutus-submodule-split]] — same pattern as
every other post-ADR-0098 task this project. **The exact `calendar_status`
shape differs from this task file's own summary**: this task's acceptance
criteria describes it as a single enum value
(`due_this_week`/`overdue`/`recently_done`), but
[[0088-ai-insights-implementation]] §2 — the actual decision document,
with a worked JSON example — specifies it as an object with three array
keys, each a list of calendar action titles. Implemented against
ADR-0088's example, which is authoritative where the two disagree.

## Acceptance criteria
- [x] New route `collective.hive_insight.contexts` →
      `GET /hivelog/api/hive-insights/contexts` (optional `?hive=`
      filter), `Authorization: Bearer <InsightAgent token>`. Returns one
      context object per hive belonging to an apiary where
      `ai_insights_enabled = TRUE` — verified by a test that a hive under
      a non-opted-in apiary never appears, including when explicitly
      requested via `?hive=` (returns an empty array, not an error — the
      response shape must not let a caller distinguish "wrong id" from
      "not opted in").
- [x] Each context object matches ADR-0088 §2's exact shape: `hive_id`,
      `current_week`, `calendar_status` (`{due_this_week: [...], overdue:
      [...], recently_done: [...]}` — lists of calendar action titles,
      not ids; titles are plan/recipe copy, not personal data, so no
      minimisation concern applies), `inspections` (recent 5, oldest
      first — a natural trend-reading order, matching
      `SensorPanelBuilder`'s own chronological point ordering; structured
      fields only: `week`, `population`, `brood_pattern`, `honey_stores`,
      `pollen_stores`, `queen_seen`, `queen_cells`, `varroa_check`,
      `varroa_count`, `disease_signs`, `supers`, `weight_kg`), `queen`
      (`queen_year`, `status`, or `null` if no active queen). **No**
      hive/apiary name (id only), **no** location, **no**
      `HiveInspection.notes`/`action_taken` free text, **no**
      `HarvestYield`/`InventoryUsage` data, **no** raw sensor readings —
      verified by a test asserting the response's exact top-level and
      inspection-level key sets (nothing extra), plus that free-text
      content and entity names never appear anywhere in the serialised
      payload, not just that the documented fields are individually
      correct.
- [x] New route `collective.hive_insight.write` →
      `POST /hivelog/api/hive-insights`, same bearer token. Accepts
      `hive` or `apiary` (whichever `scope` requires — `apiary` is
      derived automatically from `hive` server-side when `scope =
      hive`, never required redundantly in the body) + `scope`,
      `verdict`, `recommendation`, `signals`, `confidence`, `generated`,
      optional `context_snapshot`. `201` with the created id on success;
      also updates `InsightAgent.last_run`.
- [x] Both routes: `401` missing/invalid token; `403` disabled agent;
      `422` unknown `verdict` / missing required fields / an
      unparseable `generated` timestamp / a `hive`/`apiary` id that
      doesn't resolve to a real entity; the write endpoint additionally
      `404`s if the referenced hive's apiary does not have
      `ai_insights_enabled = TRUE`.
- [x] Both routes are `_access: 'TRUE'` at the route level; the
      controller (`InsightAgentApiController`) performs its own
      bearer-token lookup against `InsightAgent`, mirroring
      [[0077-sensor-ingestion-endpoint-and-device-auth]]'s
      `SensorIngestController` exactly (every device/agent checked, not
      just enabled ones, so a disabled-agent 403 can be told apart from
      an unrecognised-token 401). No CSRF token required, same
      documented exception to [[0018-csrf-and-safe-http-methods]].
- [x] Kernel tests (`InsightAgentApiControllerTest`, 13 tests):
      context-read returns only opted-in hives and the exact minimised
      shape (asserts absent fields stay absent via exact key-set
      comparison, and that free text/names never appear anywhere in the
      serialised response — not just that present fields are correct);
      `?hive=` filter behaves, including for a non-opted-in hive;
      `calendar_status` correctly buckets an overdue, a due-this-week,
      and a recently-done calendar action; write endpoint accepts a
      valid payload, creates the right `HiveInsight`, and updates
      `last_run`; missing/invalid token → `401` on both routes; disabled
      agent → `403` on both routes; unknown `verdict` → `422`; an
      unresolvable `hive` reference → `422`; write to a non-opted-in
      hive → `404`; both routes are registered with `_access: 'TRUE'`
      and no `_permission`.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **The write endpoint takes a single object, not a batch** — unlike
  `nanoprobe`'s sensor ingestion endpoint, an agent produces at most one
  recommendation per hive per run, so there was no batch use case to
  support.
- **`HiveContextBuilder`** (a new service) does the actual context
  assembly, keeping `InsightAgentApiController` a thin
  auth-then-delegate layer — same separation of concerns as
  `nanoprobe`'s `SensorPanelBuilder`/`SensorAlertCollector`.
  `calendar_status` computation reimplements
  `DashboardController::attentionTiming()`/`effectiveWeekEnd()`'s logic
  independently (that method is core-private), covering both hive-scoped
  actions (checked against this hive's own `HiveActionLog`) and
  apiary-scoped actions (checked against the apiary's
  `ApiaryActionLog`) — a hive's context reasonably includes both kinds,
  since either is something a beekeeper visiting that hive would have in
  mind.
- **Two numeric judgment calls, not specified anywhere**: the "recent N"
  inspection count (5) and the "recently done" lookback window (4 weeks)
  are both documented, adjustable class constants on
  `HiveContextBuilder` — no ADR gave an exact figure for either, so
  these are reasonable defaults, not derived values.
- **A real strict-typing bug caught while writing tests**: `Hive::id()`/
  `Apiary::id()` return `string` in this Drupal version, but
  `HiveContextBuilder::indexLogs()`'s `int $parent_id` parameter was
  strictly typed — every context-read test failed with a `TypeError`
  until the call sites added an explicit `(int)` cast. Worth remembering
  for any future strictly-typed helper taking an entity id directly from
  `->id()`.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0018-csrf-and-safe-http-methods]]
- Commits::
