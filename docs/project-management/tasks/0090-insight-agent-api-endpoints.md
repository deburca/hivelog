---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: routing
created: 2026-09-20
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

## Acceptance criteria
- [ ] New route `GET /hivelog/api/hive-insights/contexts` (optional
      `?hive=` filter), `Authorization: Bearer <InsightAgent token>`.
      Returns one context object per hive belonging to an apiary where
      `ai_insights_enabled = TRUE` — a hive under an apiary that hasn't
      opted in must never appear in this response, under any query
      parameter combination.
- [ ] Each context object matches
      [[0085-sensor-less-insight-prototype]]'s validated shape exactly:
      `hive_id`, `current_week`, `calendar_status`
      (`due_this_week`/`overdue`/`recently_done`), `inspections` (recent
      N, structured fields only: `week`, `population`, `brood_pattern`,
      `honey_stores`, `pollen_stores`, `queen_seen`, `queen_cells`,
      `varroa_check`, `varroa_count`, `disease_signs`, `supers`,
      `weight_kg`), `queen` (`queen_year`, `status`). **No** hive/apiary
      name (id only), **no** location, **no**
      `HiveInspection.notes`/`action_taken` free text, **no**
      `HarvestYield`/`InventoryUsage` data, **no** raw sensor readings —
      per [[0087-ai-insights-hosting-and-privacy-model]]'s table.
- [ ] New route `POST /hivelog/api/hive-insights`, same bearer token.
      Accepts `hive` or `apiary` + `scope`, `verdict`, `recommendation`,
      `signals`, `confidence`, `generated`, optional
      `context_snapshot`. `201` with the created id on success.
- [ ] Both routes: `401` missing/invalid token; `403` disabled agent;
      `422` unknown `verdict` / missing required fields / an
      unparseable `generated` timestamp; the write endpoint additionally
      `404`s if the referenced hive's apiary does not have
      `ai_insights_enabled = TRUE` — server-side defence-in-depth even
      though the agent should never have received that hive from the
      read endpoint.
- [ ] Both routes are `_access: 'TRUE'` at the route level; the
      controller performs its own bearer-token lookup against
      `InsightAgent`, exactly mirroring
      [[0077-sensor-ingestion-endpoint-and-device-auth]]'s pattern. No
      CSRF token required — same documented exception to
      [[0018-csrf-and-safe-http-methods]] as that endpoint (a stateless,
      credentialed request has no ambient session to forge).
- [ ] Kernel tests: context-read returns only opted-in hives and the
      exact minimised shape (assert absent fields stay absent, not just
      present fields are correct); `?hive=` filter behaves; write
      endpoint accepts a valid payload and creates the right
      `HiveInsight`; missing/invalid token → `401` on both routes;
      disabled agent → `403`; unknown `verdict` → `422`; write to a
      non-opted-in hive → `404`.
- [ ] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- Rate-limiting is not designed here — same deferred status as
  [[0077-sensor-ingestion-endpoint-and-device-auth]]'s own note; flagged
  so it isn't forgotten, not blocking this task.
- The context-read endpoint's response shape is the single place
  [[sensor-data-collection]]'s derived sensor-trend summaries get added
  later, once that project has real pilot data — additive to this
  endpoint, not a new one.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0074-sensor-data-ingestion-architecture]],
  [[0018-csrf-and-safe-http-methods]]
- Commits::
