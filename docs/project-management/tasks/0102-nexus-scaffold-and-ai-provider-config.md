---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-22
completed: 2026-09-22
branch: feature/0102-nexus-scaffold-and-ai-provider-config
release:
depends-on: ["[[0100-nexus-in-process-ai-synthesis]]", "[[0101-collective-rescope-to-api-client]]"]
blocked-by: ["[[0101-collective-rescope-to-api-client]]"]
---
# Task: `nexus` module scaffold — `AiProviderConfig` entity + relocated `HiveInsight`

## Context
Redoes [[0089-insight-agent-and-hive-insight-entities]]'s entity half
inside the new `nexus` submodule, per
[[0100-nexus-in-process-ai-synthesis]] Decision §2. `nexus` depends on
`hivelog` core, `collective` (for `HiveContextBuilder`, read via direct
PHP service call), and `key:key` (hard dependency, per ADR-0100 §Context
point 3 and its Implementation notes).

## Acceptance criteria
- [x] `nexus.info.yml`: `dependencies: [hivelog:hivelog, collective:collective, key:key]`.
- [x] `HiveInsight` entity relocated from `collective` (schema, `VERDICTS`,
      `CONFIDENCE_LEVELS`, `preSave()` invariants, access control handler
      all unchanged) — machine-written only, no add/edit form.
- [x] `AiProviderConfig` entity — **not** a renamed `ApiClient`. Fields:
      integration mode (`ai_module` | `direct_api` | `custom_endpoint`,
      per ADR-0100 Decision §3), provider identifier (direct-API mode),
      custom endpoint URL (custom-endpoint mode), a `key` field using
      Key's `key_select` form element (referencing a `Key` config entity
      by id — never the secret itself), `enabled`, `last_run`.
- [x] `hivelog.install`/`ApiaryAccessTrait`: confirm the existing
      `hive_insight` branch in core's `ApiaryAccessTrait::resolveApiary()`
      (added in 0089, dormant since 0101) still resolves correctly for
      the relocated entity — no code change expected, verify with a test.
- [x] Kernel tests: `HiveInsightTest` moved and adapted; new
      `AiProviderConfigTest` covering CRUD, the `key` reference field, and
      access control (administer-hivelog-gated, same shape as
      `ApiClient`'s).
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`
      (requires the `key` module enabled in `cms2` — confirm it's
      available/installable there first).

## Implementation notes
- **`key` and `ai` (Drupal AI module) were both already present and
  enabled in `cms2`** — no composer work needed to unblock this task or
  its `ai_module` integration mode (task 0103). Genuinely convenient,
  not something this task had to arrange.
- **The `key` field is a plain `string` base field for now**, not yet
  wired to Key's own `key_select` form element — that widget is a Form
  API render element (`'#type' => 'key_select'`), which only matters
  once `AiProviderConfigForm` exists in task 0104. Until then the field
  holds the same Key config-entity id either way; only the *form
  widget* is deferred, not the data model or the "never store the
  secret itself" guarantee, which is enforced by `preSave()` and by
  `DirectApiProviderCaller`/`CustomEndpointProviderCaller` (task 0103)
  resolving it via `key.repository` only at call time.
- **`AiProviderConfig.key` is required for `direct_api`/`custom_endpoint`
  mode but NOT `ai_module` mode** — a mode-conditional invariant enforced
  in `preSave()`, since a Drupal-AI-module-backed config delegates
  credential storage to that module's own provider plugins (which
  typically use `Key` themselves, just not through this entity).
- `ApiaryAccessTrait::resolveApiary()`'s `hive_insight` branch needed no
  code change, as expected — it only inspects `getEntityTypeId()`, never
  which module defines the class. Verified directly in
  `HiveInsightTest::testApiaryScopedAccess()`'s docblock and assertions,
  not just asserted in this note.
- Core's `ApiaryAccessTrait` docblock was updated (one line) to say
  `ApiClient`/`AiProviderConfig` instead of the now-nonexistent
  `InsightAgent` — a documentation-accuracy fix riding along with this
  task since it touches the same paragraph, not a functional change.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0100-nexus-in-process-ai-synthesis]],
  [[0098-nanoprobe-collective-locutus-submodule-split]]
- Supersedes (partially):: [[0089-insight-agent-and-hive-insight-entities]]
- Commits::
