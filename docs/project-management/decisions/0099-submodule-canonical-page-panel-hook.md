---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-21
supersedes:
---
# ADR-0099: How optional submodules add sections to `hivelog` core's canonical pages

## Status
accepted. Unblocks [[0080-hive-apiary-sensors-panel]], and is the
mechanism any future submodule (`collective`'s AI Insight panel,
[[0092-hive-apiary-ai-insight-panel]]) uses for the same problem.

## Context
[[0074-sensor-data-ingestion-architecture]] §7 and
[[0080-hive-apiary-sensors-panel]] call for a read-only "Sensors" section
on `HiveController::view()`/`ApiaryController::view()`, showing each
attached `SensorDevice`'s latest reading and a trend chart. Both
`SensorDevice`/`SensorReading` live in `nanoprobe`, per
[[0098-nanoprobe-collective-locutus-submodule-split]] — and that ADR's
whole point was that `hivelog` core must never depend on an optional
submodule. If `HiveController::view()` (core) directly referenced
`Drupal\nanoprobe\Entity\SensorDevice`, core would fail outright whenever
`nanoprobe` isn't installed — exactly the coupling direction 0098 was
written to prevent. 0080's acceptance criteria was drafted before 0098
existed and reads as if the entities were still in core; this ADR
resolves that mismatch.

`collective`'s own AI Insight panel
([[0092-hive-apiary-ai-insight-panel]]) will need the identical thing —
an optional module contributing a section to a core canonical page it
must not be depended on by — so this is worth solving once, generally,
rather than re-deciding per submodule.

## Decision
**A core-defined, module-populated hook**, the standard Drupal pattern
for exactly this shape of problem (the same idea behind
`hook_ENTITY_TYPE_view_alter`, `hook_form_alter`, etc.):

- `hivelog` core defines and documents (in a new `hivelog.api.php`)
  `hook_hivelog_hive_view_panels(\Drupal\hivelog\Entity\Hive $hive):
  array` and `hook_hivelog_apiary_view_panels(\Drupal\hivelog\Entity\Apiary
  $apiary): array`. Each implementation returns a keyed array of render
  arrays (keys must be unique across implementing modules — namespace
  the key by module, e.g. `nanoprobe_sensors`) to splice into the page;
  an empty array contributes nothing.
- `HiveController::view()` / `ApiaryController::view()` call
  `$this->moduleHandler()->invokeAll('hivelog_hive_view_panels', [$hive])`
  (respectively `..._apiary_view_panels`) and merge every returned panel
  into `$build`. This is the **only** change to core — it has zero PHP
  reference to `nanoprobe` or any other submodule, and
  `invokeAll()` simply returns an empty array when nothing implements the
  hook, so a `nanoprobe`-less install renders exactly as it does today.
- Each panel's render array carries its own `#weight` so it lands in the
  right visual position regardless of hook-invocation order or how many
  modules implement it.
- `nanoprobe` implements both hooks in `nanoprobe.module`, delegating to
  a small `SensorPanelBuilder` service that does the actual query/access/
  render-array work — keeping the `.module` file a thin dispatcher, per
  Drupal convention.
- **Access is the implementing module's responsibility, not the hook
  contract's.** `nanoprobe`'s implementation filters to `SensorDevice`/
  `SensorReading` entities the *current* user can actually view via their
  own `->access('view')` (per `ApiaryAccessTrait`) before including
  anything — a user who can see a hive but lacks `view own sensor device`
  must still see no sensor data, exactly the access-parity requirement
  [[0080-hive-apiary-sensors-panel]] already specifies.

## Consequences
- Positive: solves the "optional submodule extends a core page" problem
  once, generally, using a pattern every Drupal developer already
  recognises — no bespoke plugin system, no new dependency direction.
  `collective`'s future AI Insight panel reuses this exact mechanism
  without needing its own design pass. `hivelog` core stays entity-type-
  agnostic about what submodules exist, matching
  [[0098-nanoprobe-collective-locutus-submodule-split]]'s whole premise.
- Negative / trade-offs: a hook contract is a looser, less type-checked
  interface than a direct method call — a typo in a panel's array key
  could silently collide with another module's, caught only by
  convention (module-prefixed keys) and testing, not the type system.
  This is `hivelog`'s first custom hook and first `.api.php` file; a
  small new pattern for future contributors to learn, though a standard
  one to anyone who already knows Drupal.
- Follow-up tasks: [[0080-hive-apiary-sensors-panel]] implements the
  `nanoprobe` side. [[0092-hive-apiary-ai-insight-panel]] should reuse
  the same two hooks when `collective` is built, not invent a second
  mechanism.
