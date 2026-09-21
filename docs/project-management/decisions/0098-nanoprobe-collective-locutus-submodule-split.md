---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-21
supersedes:
---
# ADR-0098: Split optional capabilities into submodules — `nanoprobe`, `collective`, `locutus`

## Status
accepted. Redoes [[0076-sensor-device-and-reading-entities]] against this
ADR immediately (nothing from its first pass, implemented directly in
`hivelog` core, has been committed to git yet, so this is a move + a
namespace change, not a migration).

## Context
`hivelog` core today is apiaries, hives, inspections, queens, the seasonal
calendar, inventory, and harvest/yield tracking — universally useful to
every beekeeper who installs the module. Three initiatives layered on top
of that are each optional in a way core isn't:

1. **Sensor data collection** ([[0074-sensor-data-ingestion-architecture]]
   through [[0097-hardware-infrastructure-and-component-catalog]]) — needs
   real, purchased hardware (a load cell, an MCU, a radio). A beekeeper
   with no sensors gets zero value from `SensorDevice`/`SensorReading`
   existing in their install.
2. **AI-assisted insights** ([[0083-ai-assisted-apiary-insights]] through
   [[0088-ai-insights-implementation]]) — needs an external, potentially
   paid model/API call and its own consent/privacy handling
   ([[0087-ai-insights-hosting-and-privacy-model]]). [[0083]] itself
   already established this works from manual `HiveInspection`/
   `CalendarAction`/`Queen`/`HarvestYield` data alone — sensor data enriches
   it but was never required.
3. **Outbound reporting/alerting** — pushing what's already in the
   in-app "Needs attention" queue ([[0057-dashboard-information-architecture]])
   to a beekeeper who isn't currently logged in (email, push, webhook).
   No ADR has designed this yet; nothing in hivelog does it today.

Task 0076 implemented (1)'s entities directly in `hivelog` core, in this
same session, before this restructuring was raised. Bundling all three
initiatives into core means every install carries every capability's
schema, permissions, and code regardless of whether it's ever used — a
beekeeper who wants a manual logbook and nothing else still gets
sensor-device tables, AI-insight entities, and (once built) notification
plumbing they'll never touch. This is exactly the situation Drupal's own
submodule convention exists for: optional functionality that depends on,
but does not belong inside, a core module.

### The proposed split
Three new, independently-enableable submodules, named (per the user's own
framing) so the shape of the split is legible from the names alone — many
independent senses feeding one intelligence, which then speaks for itself:

- **`nanoprobe`** — the senses. Sensor device registration and the
  data-ingestion endpoint.
- **`collective`** — the intelligence. Synthesises whatever signals are
  actually available (manual logs alone, or manual logs enriched by
  `nanoprobe` sensor data if that's also installed) into insights.
- **`locutus`** — the collective's voice. Delivers what's already surfaced
  in-app (the "Needs attention" queue, and `collective`'s insights if
  present) to a beekeeper outside the app.

## Decision
1. **`hivelog` core stays exactly what it is today.** No sensor, AI, or
   notification-delivery code lives in core going forward — this ADR adds
   no new core entities, permissions, or routes, only the (already-added,
   see §6) `ApiaryAccessTrait::resolveApiary()` branches needed for
   `nanoprobe`'s entities.
2. **Three new submodules live under `modules/` inside this same git
   repository and composer package** (`hivelog/hivelog`, `type:
   drupal-module`) — `modules/nanoprobe/`, `modules/collective/`,
   `modules/locutus/`. This is the standard "core + submodules in one
   project" Drupal packaging pattern (each a normal `type: module`
   `.info.yml`, discovered automatically by Drupal's module scanner; no
   separate composer package per submodule).
   - **`nanoprobe`** (`modules/nanoprobe/`) — everything
     [[0074-sensor-data-ingestion-architecture]] /
     [[0075-sensor-hardware-and-connectivity-selection]] /
     [[0097-hardware-infrastructure-and-component-catalog]] designed:
     `SensorDevice`/`SensorReading` entities, the ingestion endpoint +
     device-token auth, the configuration-descriptor download. Depends on
     `hivelog:hivelog` only.
   - **`collective`** (`modules/collective/`) — everything
     [[0083-ai-assisted-apiary-insights]] /
     [[0087-ai-insights-hosting-and-privacy-model]] /
     [[0088-ai-insights-implementation]] designed: `HiveInsight`, the
     `InsightAgent` write-back contract, the daily batch job, the hosting/
     privacy/consent model. Depends on `hivelog:hivelog` only — runs from
     `HiveInspection`/`CalendarAction`/`Queen`/`HarvestYield` data with no
     sensors present, exactly as [[0083]] already established.
   - **`locutus`** (`modules/locutus/`) — new, and **not designed by this
     ADR**: outbound delivery (email/push/webhook) of the "Needs attention"
     queue's items and, if `collective` is installed, its insights. Needs
     its own follow-up ADR (channels, send triggers, digest-vs-immediate,
     an opt-in consent field mirroring [[0087]]'s `ai_insights_enabled`
     pattern) before any implementation task opens — the same deliberate
     incompleteness [[0083-ai-assisted-apiary-insights]] used for
     `collective`'s own first pass.
3. **No hard dependencies between the three submodules.** Each declares
   `dependencies: [hivelog:hivelog]` and nothing else. Where one benefits
   from another being present — `collective` enriching its synthesis with
   `nanoprobe` sensor trends; `locutus` delivering `collective`'s insights
   — that is a **soft, runtime-detected integration**
   (`\Drupal::service('module_handler')->moduleExists('nanoprobe')`),
   never a declared dependency. This is what structurally delivers "install
   `collective` without sensors and reporting, working just from
   beekeeper-provided data" — there is no dependency edge to violate, not
   just a design intention to honour. Every such check gets a short comment
   pointing back to this ADR, since a soft dependency is less discoverable
   than one Drupal's own dependency system enforces.
4. **Existing ADRs are re-homed, not rewritten.**
   [[0074-sensor-data-ingestion-architecture]] through
   [[0097-hardware-infrastructure-and-component-catalog]] and
   [[0083-ai-assisted-apiary-insights]] through
   [[0088-ai-insights-implementation]] remain the technical decisions for
   `nanoprobe` and `collective` respectively; this ADR relocates where
   their entities/code live, it does not reopen or contradict their
   content. Their own text is not retroactively edited to say
   "nanoprobe"/"collective" — this ADR, and any *new* task file, is what
   says so going forward.
5. **[[0076-sensor-device-and-reading-entities]] is redone against this
   ADR**, in this same session: `SensorDevice`/`SensorReading`, their
   access control handlers, and their kernel tests move from `hivelog`
   core into `modules/nanoprobe/` under the `Drupal\nanoprobe\...`
   namespace, with their own `nanoprobe.info.yml` / `.install` /
   `.permissions.yml`. `hivelog` core's copies (entities, handlers, tests,
   the `hivelog.install` update hook, the `hivelog.permissions.yml`
   entries) are removed — the first pass was never committed, so this is a
   move, not a deprecation.
6. **`ApiaryAccessTrait::resolveApiary()` stays in `hivelog` core**, rather
   than being duplicated per submodule. Core's trait keeps its
   `sensor_device`/`sensor_reading` branches (added while implementing
   0076 the first time) even when `nanoprobe` isn't installed — a small,
   permanent, harmless piece of string-matched dead code in core when the
   submodule is absent (an entity-type-id string comparison that simply
   never matches; no class-existence or autoload dependency on
   `nanoprobe`). Traded deliberately against every submodule needing its
   own copy of the apiary-resolution walk. `collective`'s future
   `HiveInsight` branch follows the same pattern. Revisit only if this
   trait grows unwieldy.
7. **CI and local lint/test tooling must cover submodule paths, not just
   `hivelog` core's own `src/`/`tests/`.** `.github/workflows/ci.yml`'s
   phpcs, phpstan, and PHPUnit (kernel/unit) steps currently glob only
   `src/`, `tests/`, and the resolved `$MODULE_PATH` (hivelog core);
   `composer.json`'s `lint`/`stan` scripts do the same locally. Both must
   be extended to also walk `modules/*/src`, `modules/*/tests`,
   `modules/*/*.module`, `modules/*/*.install` — otherwise a submodule's
   code ships with zero CI coverage, silently. This is a correctness gap
   to close as part of redoing 0076, not a future nice-to-have.

## Consequences
- Positive: a beekeeper who wants a manual logbook installs `hivelog`
  alone and gets none of `nanoprobe`/`collective`/`locutus`'s schema,
  permissions, or code — genuinely optional functionality is genuinely
  optional, matching Drupal's own idiom for exactly this situation.
  `collective`'s "works without sensors" requirement becomes structural
  (no dependency edge exists to violate) rather than a design intention
  that could quietly erode. Already-accepted ADRs (0074–0097, 0083–0088)
  need no rewriting, only re-homing. Scoping `locutus` out as its own,
  not-yet-designed module means its unresolved delivery-channel questions
  don't block `nanoprobe`/`collective` from proceeding now.
- Negative / trade-offs: three new `.info.yml`/`.install`/
  `.permissions.yml` files and three new PHP namespaces where one existed
  before — real packaging overhead for a project with one maintainer.
  Soft, runtime-detected integration between submodules is less
  discoverable than a declared dependency; mitigated by a standing
  comment convention at each check site, not eliminated. `locutus` is
  named and scoped but not designed — nothing can be built against it yet,
  deliberately. CI/tooling changes (§7) are new surface area that must be
  kept in sync as further submodules (`collective`, later `locutus`) are
  added, or their code will silently stop being checked.
- Follow-up tasks: redo [[0076-sensor-device-and-reading-entities]]
  against `modules/nanoprobe/` (this session). Update
  [[sensor-data-collection]]'s own docs to reference the `nanoprobe`
  module name for tasks 0077–0082/0096. Update [[ai-apiary-insights]] to
  reference `collective` before its first implementation task is opened.
  `locutus` needs its own ADR (channels, triggers, consent model) before
  any task file exists for it — no project tracks it yet.
