---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[sensor-data-collection]]"
area: ui
created: 2026-09-20
completed: 2026-09-21
branch: feature/0080-hive-apiary-sensors-panel
release:
depends-on: ["[[0076-sensor-device-and-reading-entities]]"]
blocked-by: ["[[0076-sensor-device-and-reading-entities]]"]
---
# Task: "Sensors" panel on the hive/apiary canonical pages

## Context
Read-only surfacing of `SensorReading` data where a beekeeper already
looks, per [[0074-sensor-data-ingestion-architecture]] §7 — extends the
existing weight-histogram pattern rather than adding a new charting
mechanism.

**This task's acceptance criteria predates
[[0098-nanoprobe-collective-locutus-submodule-split]]** and originally
read as if `HiveController`/`ApiaryController` could reference
`SensorDevice`/`SensorReading` directly — they can't, since those
entities live in the optional `nanoprobe` submodule and `hivelog` core
must never depend on it. Implementing this required first deciding *how*
an optional submodule adds a section to a core canonical page without
creating that dependency — see
[[0099-submodule-canonical-page-panel-hook]], a new ADR this task
produced as a prerequisite.

## Acceptance criteria
- [x] `HiveController::view()` gains a "Sensors" section listing each
      attached, `enabled` hive-scoped `SensorDevice` with its latest
      reading per `metric`. `ApiaryController::view()` gains the
      equivalent for apiary-scoped devices. **Implemented via a new hook**
      (`hook_hivelog_hive_view_panels()` / `hook_hivelog_apiary_view_panels()`,
      defined in `hivelog.api.php`, invoked via
      `$this->moduleHandler()->invokeAll(...)`), not a direct reference —
      see [[0099-submodule-canonical-page-panel-hook]]. `nanoprobe`
      implements both hooks (`nanoprobe.module`), delegating to a new
      `SensorPanelBuilder` service
      (`modules/nanoprobe/src/SensorPanelBuilder.php`).
- [x] A trend chart per device/metric, built the same way as
      `HiveController::buildWeightHistogram()` — inline SVG, no
      charting-library dependency — fed by daily-aggregated points
      (min/max/avg) rather than raw readings. **Decision: aggregated on
      read, no persisted rollup** — Phase 1 pilot volume keeps a
      30-day-window query small enough that borrowing
      [[0082-sensor-reading-retention-and-rollup]]'s rollup early wasn't
      justified; revisit if this shows up as slow in practice. Chart
      shows a shaded min/max band with an avg line on top; a metric with
      fewer than two distinct days of data in the window shows its
      latest-reading summary but no chart (a single point isn't a
      trend).
- [x] `HiveInspection.weight`'s existing histogram is left completely
      untouched — no changes to `buildWeightHistogram()` or its call
      site; the Sensors panel is a new, separate section
      (`SensorPanelBuilder::renderTrendSvg()`, independently implemented
      using the same inline-SVG technique, not shared code — the two
      methods live in different modules and have different data shapes).
- [x] No add/edit UI for `SensorReading` anywhere on this panel — the
      panel is built entirely from read-only queries; nothing on it
      links to a form.
- [x] A hive/apiary with no attached devices — or attached devices with
      no accessible reading yet — **omits the section entirely** (the
      task's own "or the section is simply omitted" option), rather than
      showing an empty-state message. `SensorPanelBuilder::buildPanel()`
      returns `[]` in that case, and the hook contract treats an empty
      array as "contribute nothing."
- [x] Kernel tests
      (`modules/nanoprobe/tests/src/Kernel/SensorPanelBuilderTest.php`):
      panel renders the latest reading + chart for a hive with an
      attached device; correct empty behaviour for one without (no
      devices; a device with no readings yet; a disabled device);
      apiary-scoped vs. hive-scoped devices land on the correct page;
      respects `ApiaryAccessTrait` (a user without access to the apiary
      sees no panel at all, even for content that exists); the hook is
      actually dispatched by `\Drupal::moduleHandler()` (not just the
      service in isolation — catches a hook-name/signature typo that
      `invokeAll()` would otherwise silently swallow as "nothing
      implements this"). Also re-ran `hivelog` core's own existing
      `HiveController`/`ApiaryController` kernel tests unmodified (48
      tests) to confirm they still pass with `nanoprobe` in the picture
      but not required — proving the "no hard dependency" property
      [[0099-submodule-canonical-page-panel-hook]] promises, not just
      asserting it.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Implementation notes
- **New pattern for this codebase**: `hivelog`'s first custom hook and
  first `.api.php` file. Full rationale in
  [[0099-submodule-canonical-page-panel-hook]] — short version: the
  standard Drupal answer to "an optional module needs to add a section
  to a page owned by the module it depends on," reused as-is by
  `collective`'s future AI Insight panel
  ([[0092-hive-apiary-ai-insight-panel]]) rather than inventing a second
  mechanism.
- **Access is checked twice, deliberately**: once when loading
  `SensorDevice` entities (`->access('view')`), and again per
  `SensorReading` before including its summary/chart
  (`->access('view')`). Redundant in the common case (a reading's access
  resolves through its device's apiary, so an accessible device implies
  accessible readings) but cheap, and guards against ever assuming one
  entity type's access implies another's just because
  `ApiaryAccessTrait::resolveApiary()` happens to chain through it today.
- **"Latest reading per metric" samples the 50 most recent readings
  across all metrics** for a device (ordered newest-first, first
  occurrence per metric wins) rather than querying per-metric — cheaper
  at Phase 1 scale, where `SensorDevice::DEVICE_TYPE_METRICS` caps a
  device to a handful of distinct metrics anyway.
- The apiary panel shows **only apiary-scoped devices** — a hive-scoped
  device's readings appear on that hive's own page, not rolled up onto
  the apiary page too, matching how the acceptance criteria phrased "the
  equivalent for apiary-scoped devices" rather than "everything under
  this apiary."

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]],
  [[0098-nanoprobe-collective-locutus-submodule-split]],
  [[0099-submodule-canonical-page-panel-hook]],
  [[0004-custom-controllers-over-view-builders]]
- Commits::
