---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
completed: 2026-09-21
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

**Lives in a new `collective` submodule, not `hivelog` core** — this
task's acceptance criteria predates
[[0098-nanoprobe-collective-locutus-submodule-split]] and reads as if
`InsightAgent`/`HiveInsight` belonged in core (`src/Entity/...`,
`hivelog.install`, `hivelog.permissions.yml`). They don't: `collective`
is the second of the three optional submodules that ADR named, scaffolded
here for the first time (mirroring `nanoprobe`'s own shape from
[[0076-sensor-device-and-reading-entities]] exactly). The one field that
*does* belong in core either way — `Apiary.ai_insights_enabled` — is a
genuine property of the (core) `Apiary` entity itself, not
`collective`-specific schema, so it stays in `hivelog` core, added via a
new core update hook.

## Acceptance criteria
- [x] `modules/collective/src/Entity/InsightAgent.php` —
      `ContentEntityBase`, base table `collective_insight_agent`, entity
      keys (`id`, `label` → `label`, `uuid`, `owner` → `uid`).
- [x] `InsightAgent` fields: `label` (required string), `token` (string,
      server-generated on insert, stored hashed, never returned in
      plaintext except immediately after generation/regeneration —
      mirrors `nanoprobe`'s `SensorDevice` token mechanism exactly, own
      independent implementation per submodule),
      `enabled` (boolean, default `TRUE`), `last_run` (timestamp,
      optional), plus `uid`/`created`/`changed`. **No `apiary`/`hive`/
      `scope` field** — a site-level credential, not tied to one
      hive/apiary the way `SensorDevice` is.
- [x] `modules/collective/src/Entity/HiveInsight.php` — base table
      `collective_hive_insight`, entity keys (`id`, `uuid`); no
      `uid`/owner (machine-written).
- [x] `HiveInsight` fields: `apiary` (required entity_reference →
      `apiary`), `hive` (entity_reference → `hive`, required only when
      `scope = hive`), `scope` (required list_string: `apiary` | `hive`),
      `verdict` (required list_string, code-defined via
      `HiveInsight::VERDICTS`: `act_now`, `inspect_soon`, `all_clear`),
      `recommendation` (required string), `signals` (required
      string_long, `- `-prefixed bullet convention — renders via
      `\Drupal\hivelog\Utility\SimpleBulletText::render()`, core's
      existing `CalendarAction.description` utility, no new rendering
      code), `confidence` (optional list_string via
      `HiveInsight::CONFIDENCE_LEVELS`: `high`, `medium`, `low`),
      `context_snapshot` (optional string_long), `generated` (required
      timestamp, agent-supplied), plus `created`/`changed` (automatic —
      `EntityChangedTrait` added for `changed` to actually update on
      save, since this entity is legitimately re-writable, unlike
      `SensorReading`, which has no `changed` field at all).
- [x] Validation (`preSave()`, throws): `HiveInsight.hive` required when
      `scope = hive`, unset when `scope = apiary`; also validates
      `verdict` is a recognised `VERDICTS` key (mirrors `SensorReading`'s
      own metric-validation guard).
- [x] Access control: extended `ApiaryAccessTrait::resolveApiary()` (in
      `hivelog` core, per ADR-0098 §6's established precedent) with
      `HiveInsight` → `apiary` directly, or → `hive` → `apiary`. No
      branch for `InsightAgent` — see Implementation notes for its
      genuinely different, non-apiary-scoped access model.
- [x] `modules/collective/collective.permissions.yml`: `view own/any`,
      `edit own/any`, `delete own/any` for `insight_agent` (no `add`
      permission at all — only `administer hivelog` may create one).
      `view own/any` + `delete own/any` only for `hive_insight`.
- [x] New `Apiary.ai_insights_enabled` field (`boolean`, default
      `FALSE`), added to core's `src/Entity/Apiary.php`, exposed on the
      apiary edit form.
- [x] Update hooks: `hivelog_update_10028()` (core) installs the
      `ai_insights_enabled` field storage on the existing `apiary` table.
      `InsightAgent`/`HiveInsight` need no update hook — `collective` is
      a brand-new module, so Drupal installs their schema automatically
      on first enable, exactly like `nanoprobe`'s own entities in
      [[0076-sensor-device-and-reading-entities]]. `collective.install`
      only needs `collective_uninstall()`, cleaning up `hive_insight`
      before `insight_agent`.
- [x] Kernel tests (`InsightAgentTest`, `HiveInsightTest`, plus one new
      test in core's own `ApiaryTest`): CRUD, field defaults, the
      scope/hive validation guard (both directions), the `VERDICTS`
      validation guard, apiary-scoped access parity for `HiveInsight`
      (mirroring `SensorDeviceTest`'s pattern), token generation/
      regeneration behaviour, `InsightAgent`'s ownership-only access
      model (own vs. any, no apiary dimension, `administer hivelog`-only
      create), and `Apiary.ai_insights_enabled` defaults to `FALSE` —
      tested in core's own `ApiaryTest.php`, not `collective`'s tests,
      since the field itself belongs to core.
- [x] `ddev drush updb -y && ddev drush cr` clean, verified against
      `cms2`.
- [x] Full kernel + unit suite (`--group hivelog`) re-run against
      `cms2`, no regressions.

## Implementation notes
- **`InsightAgent`'s access control is genuinely novel for this
  codebase**: every other entity type resolves access through
  `ApiaryAccessTrait` (apiary-membership-based); `InsightAgent` has
  nothing to resolve to, so `InsightAgentAccessControlHandler` implements
  a plain ownership check instead (`view/edit/delete any` OR (`.../own`
  permission AND the current user is the entity's owner)) — no shared
  trait, since this shape doesn't recur anywhere else yet.
- **A real testing pitfall caught and fixed while writing these tests**:
  the first user created within a single kernel test method becomes uid
  1, which bypasses Drupal's permission checks entirely — this would
  have made `InsightAgentTest`'s "owner can" and "only admin can create"
  assertions pass for the wrong reason (the uid-1 bypass, not the actual
  ownership/permission logic). Fixed by creating a throwaway first user
  in the affected test methods to consume uid 1 before the real test
  users — the same class of bug first caught in
  [[0082-sensor-reading-retention-and-rollup]]'s own test suite.
- **A stale local-dev-DB registry entry, not a code bug**: `cms2`'s
  update-hook registry still remembered a *different*,
  since-reverted `hivelog_update_10028()` (the original sensor-entity
  install hook from earlier `nanoprobe` work, before
  [[0098-nanoprobe-collective-locutus-submodule-split]] moved those
  entities out of core) as already run, so the real, current
  `hivelog_update_10028()` (this task's `ai_insights_enabled` field) was
  never offered as pending. Fixed locally via
  `update.update_hook_registry`'s `setInstalledVersion()` — a
  disposable-dev-DB-only fix, not something a real deployment would ever
  hit (a real site only ever sees the current code's actual 10028).

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0003-code-defined-entity-schema]], [[0019-authorisation-model]]
- Commits::
