---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
branch: feature/0089-insight-agent-and-hive-insight-entities
release:
depends-on:
blocked-by:
---
# Task: Define the `InsightAgent` and `HiveInsight` entities

## Context
Foundational pair for [[ai-apiary-insights]] — the external agent's
credential and the recommendation it produces, per
[[0088-ai-insights-implementation]] §1. Nothing else in this project can
be built until these exist, mirroring how
[[0076-sensor-device-and-reading-entities]] founded
[[sensor-data-collection]].

## Acceptance criteria
- [ ] `src/Entity/InsightAgent.php` — `ContentEntityBase`, base table
      `hivelog_insight_agent`, entity keys (`id`, `label` → `label`,
      `uuid`, `owner` → `uid`).
- [ ] `InsightAgent` fields: `label` (required string), `token` (string,
      server-generated on insert, stored hashed, never returned in
      plaintext except immediately after generation/regeneration),
      `enabled` (boolean, default `TRUE`), `last_run` (timestamp,
      optional, updated on every successful batch completion), plus
      `uid`/`created`/`changed`. **Deliberately no `apiary`/`hive`/
      `scope` field** — per
      [[0088-ai-insights-implementation]] §1, this is a site-level
      credential (normally exactly one row), not tied to one hive/apiary
      the way `SensorDevice` is.
- [ ] `src/Entity/HiveInsight.php` — base table `hivelog_hive_insight`,
      entity keys (`id`, `uuid`); no `uid`/owner (machine-written).
- [ ] `HiveInsight` fields: `apiary` (required entity_reference →
      `apiary`), `hive` (entity_reference → `hive`, required only when
      `scope = hive`), `scope` (required list_string: `apiary` | `hive`
      — reuses the `CalendarAction`/`SensorDevice` duality), `verdict`
      (required list_string, code-defined via a `HiveInsight::VERDICTS`
      constant: `act_now`, `inspect_soon`, `all_clear`), `recommendation`
      (required string — the short imperative sentence), `signals`
      (required string_long — stored as `- `-prefixed bullet lines, per
      the existing `CalendarAction.description` convention, so
      `SimpleBulletText::render()` renders it with no new rendering
      code), `confidence` (optional list_string: `high`, `medium`,
      `low`), `context_snapshot` (optional string_long — a copy of what
      the context-read endpoint returned for this hive at generation
      time), `generated` (required timestamp, agent-supplied), plus
      `created`/`changed` (automatic).
- [ ] Validation (`preSave()`, throws): `HiveInsight.hive` required when
      `scope = hive`, unset when `scope = apiary` — same shape as
      `SensorDevice.hive`'s guard
      ([[0076-sensor-device-and-reading-entities]]).
- [ ] Access control: extend `ApiaryAccessTrait::resolveApiary()` with
      `HiveInsight` → `apiary` directly, or → `hive` → `apiary` — no
      branch needed for `InsightAgent` itself, since it has no
      apiary/hive to resolve (see Implementation notes).
- [ ] `hivelog.permissions.yml`: standard `view own/any`,
      `edit own/any`, `delete own/any` for `insight_agent` (no `add`
      beyond `administer hivelog` — this is a rare, high-trust
      credential, not something every beekeeper self-serves). `view
      own/any` + `delete own/any` only for `hive_insight` — machine-
      written, no add/edit permissions at all.
- [ ] New `Apiary.ai_insights_enabled` field (`boolean`, default
      `FALSE`) per [[0087-ai-insights-hosting-and-privacy-model]] —
      added to `Apiary`'s own `baseFieldDefinitions()`, exposed on the
      apiary edit form.
- [ ] Update hook(s) in `hivelog.install`: install both new tables
      (net-new); add the `ai_insights_enabled` field to the existing
      `apiary` table. Both new entity type IDs added to
      `hivelog_uninstall()`'s child-first cleanup list (`hive_insight`
      before `insight_agent`/`apiary`/`hive`).
- [ ] Kernel tests (`InsightAgentTest`, `HiveInsightTest`): CRUD, field
      defaults, the scope/hive validation guard, apiary-scoped access
      parity for `HiveInsight` (mirroring `SensorDeviceTest`'s pattern),
      token generation/regeneration behaviour, `Apiary.
      ai_insights_enabled` defaults to `FALSE` on a new apiary.
- [ ] `ddev drush updb -y && ddev drush cr` clean, verified against
      `cms2`.
- [ ] Full kernel + unit suite (`--group hivelog`) re-run against
      `cms2`, no regressions.

## Implementation notes
- `InsightAgent` is intentionally **not** resolved through
  `ApiaryAccessTrait` — it has no apiary/hive to resolve to. Its own
  access is a plain `administer hivelog`-gated entity, same shape as any
  other site-level configuration-like entity.
- No routes, controllers, or UI in this task — deferred to
  [[0090-insight-agent-api-endpoints]] (the API) and
  [[0091-insight-agent-management-ui]] (provisioning/consent UI),
  mirroring how [[0076-sensor-device-and-reading-entities]] deferred its
  own routing layer.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0003-code-defined-entity-schema]], [[0019-authorisation-model]]
- Commits::
