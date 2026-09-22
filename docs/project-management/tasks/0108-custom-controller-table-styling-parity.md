---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[sensor-data-collection]]"
area: routing
created: 2026-09-22
completed: 2026-09-22
branch: feature/0108-custom-controller-table-styling-parity
release:
depends-on:
blocked-by:
---
# Task: Canonical-page summary table styling parity

## Context
Caught by direct user report while testing the new in-app nav from
[[0105-submodule-navigation-menu-links]]: `/hivelog/ai-provider-config/3`
and `/hivelog/api-client/1` didn't look "configured as per other Hivelog
tables." Root cause, confirmed by comparing rendered markup: every other
custom-controller canonical page's summary table (e.g.
`HiveController::buildQueenSection()`'s `.hivelog-queen-table`) sets a
`#header`, a dedicated `hivelog-*-table` CSS class, and attaches the
`hivelog/tables` library (`css/hivelog.tables.css`). `ApiClientController`,
`AiProviderConfigController` — and `SensorDeviceController`, same root
cause, same fix, not separately reported but caught while fixing the
other two — all used a bare `'#type' => 'table'` with none of that:
correct data, zero styling, no header row.

## Acceptance criteria
- [x] `ApiClientController::view()`, `AiProviderConfigController::view()`,
      `SensorDeviceController::view()` summary tables each gain
      `'#header' => [Field, Value]`, their own dedicated
      `hivelog-api-client-table` / `hivelog-ai-provider-config-table` /
      `hivelog-sensor-device-table` class, and
      `'#attached' => ['library' => ['hivelog/tables']]` — matching
      `buildQueenSection()`'s exact shape, no new table-styling pattern
      invented for three entities.
- [x] `css/hivelog.tables.css`: the three new classes added to every one
      of the file's existing shared selector groups (full-width/
      border-collapse, row dividers, header border, and all three
      responsive `@media` groups) — the established convention here is
      one shared stylesheet listing every participating class, not a
      per-module stylesheet, so extended in place rather than
      duplicated.
- [x] Verified live against `cms2`: both reported pages
      (`/hivelog/api-client/1`, `/hivelog/ai-provider-config/3`) now
      render the correct `<table class="hivelog-*-table">` markup with a
      `Field`/`Value` header row.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0004-custom-controllers-over-view-builders]]
- Commits::
