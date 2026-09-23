---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[sensor-data-collection]]"
area: entity
created: 2026-09-23
completed: 2026-09-23
branch: feature/0114-apiary-page-remove-ai-insights-indicator-add-sensor
release:
depends-on:
blocked-by:
---
# Task: Apiary page — remove AI insights enabled indicator, Add Sensor button, Sensors heading

## Context
User request, same day as [[0113-destructive-action-styling-sensor-device-api-client]]
and the apiary-scoped stat tile verification work: on `/hivelog/apiary/43`,
remove the "AI insights enabled: Yes/No" field indicator (rendered by
the Apiary entity's default view-mode field list) and the "Add Sensor"
button (in the apiary-scoped Sensors panel's heading, task 0106).
Same-turn follow-up, once the Add Sensor button was gone: remove the
now-purposeless bare "Sensors" title row too — with the sole other
element also gone, a heading row was left with nothing to justify
itself.

## Acceptance criteria
- [x] `Apiary::baseFieldDefinitions()`'s `ai_insights_enabled` field no
      longer carries `->setDisplayOptions('view', ...)` /
      `->setDisplayConfigurable('view', TRUE)` — matches the established
      "omit view display entirely for a field that shouldn't show"
      pattern already used by e.g. `ApiClient::token`. The `form`
      display (the edit-form checkbox) is untouched — this only hides
      the read-only indicator, not the setting itself.
- [x] `SensorPanelBuilder::buildPanel()`'s `$add_url` parameter is now
      nullable; `buildApiaryPanel()` passes `NULL`, so its heading never
      offers an "Add Sensor" action. `buildHivePanel()` (the Hive
      Insights page's own copy of this panel) is unaffected — it still
      passes a real `$add_url` and still offers the action, since the
      user's request was scoped to the apiary page only.
      `nanoprobe.sensor_device.add_for_apiary` itself is unchanged and
      still reachable directly; only the link to it was removed.
- [x] `buildPanel()` also gained a `bool $show_heading = TRUE` parameter;
      `buildApiaryPanel()` passes `FALSE`, so the panel renders straight
      into its device sections (or the "No sensors are registered here
      yet." empty state) with no title row above them at all.
      `buildHivePanel()` keeps the default (`TRUE`) — its "Sensors"
      title still earns its place there since it sits alongside the
      still-offered Add Sensor action.
- [x] An apiary with no accessible devices and no "Add Sensor" action to
      offer now gets no panel at all (previously it could still render
      an empty-state message + Add Sensor button for a user with add
      access) — new `testApiaryWithNoDevicesHasNoPanel()` covers this;
      `testApiaryPanelHasNoHeading()` covers the whole heading row
      (title and action both) being absent when devices *do* exist.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.
- [x] Verified live against `cms2`: `/hivelog/apiary/43` no longer shows
      "AI insights enabled" anywhere, nor an "Add Sensor" button, nor a
      "Sensors" title; the stat tile row and the full Sensors panel
      content (device list, per-metric tabs) — both added/verified
      earlier the same day — are unaffected. Confirmed the other
      heading rows still on the page (Hives, Seasonal Calendar,
      Inventory, Products) are untouched — the `$show_heading` flag is
      local to `SensorPanelBuilder::buildPanel()`, not a shared
      convention those sections use.

## Implementation notes
- Considered post-processing the render array in `ApiaryController::view()`
  (e.g. `unset($build['apiary']['ai_insights_enabled'])` after calling
  the view builder) instead of editing the field definition. Rejected:
  the field-definition-level fix is the actual source of truth for
  every future render of this entity (any view mode, any future
  controller), not just this one call site, and matches how `token`-like
  fields already declare themselves display-hidden in this codebase —
  a render-array `unset()` would be a workaround duplicating knowledge
  the field definition should own outright.
- `buildPanel()`'s `$add_url` becoming nullable, rather than adding a
  separate boolean flag (`$show_add_action`), keeps the "is there
  actually a link to offer" question answered by a single value instead
  of two that could disagree with each other.
- The heading title and the add action are two independent concerns
  (a bare title row is meaningful on its own on pages that don't offer
  an add action, e.g. a future read-only summary section), so
  `$show_heading` is its own parameter rather than being inferred from
  `$add_url === NULL` — that inference would have been correct today by
  coincidence (apiary happens to want both gone together) but would
  silently couple two decisions that don't actually depend on each
  other.

## Related
- Project:: [[sensor-data-collection]]
- Tasks:: [[0106-sensor-device-management-ui]] (introduced the "Add
  Sensor" action this removes from the apiary panel only),
  [[0113-destructive-action-styling-sensor-device-api-client]] (same
  day, same pages)
- Commits::
