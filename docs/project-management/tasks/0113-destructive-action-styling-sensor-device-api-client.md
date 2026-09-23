---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[action-button-consistency]]"
area: theme
created: 2026-09-23
completed: 2026-09-23
branch: feature/0113-destructive-action-styling-sensor-device-api-client
release:
depends-on:
blocked-by:
---
# Task: Destructive-action styling — Sensor Device config download, API Client token regenerate

## Context
User report: on `/hivelog/sensor-device/{id}`, the warning paragraph
ahead of the "Download Configuration" button had no visual weight, and
the button itself "does not immediately look like a button." User then
asked for "a similar solution" on `/hivelog/api-client/{id}`, whose
"API endpoint" reference block and "Regenerate Token" button had the
same problem.

Two distinct root causes, found in sequence:
1. **Missing warning treatment.** Both pages' explanatory text/endpoint
   reference was a bare, unstyled block despite describing consequential
   information (token regeneration invalidates any previously
   downloaded config / any client still using the current token).
2. **The button system requires a registered "context wrapper."**
   `css/hivelog.buttons.css` (ADR-0012, task 0010 —
   [[action-button-consistency]]'s own foundation task) scopes *every*
   `.button`/`.button--primary`/`.button--danger` rule behind an
   `:is(.hivelog-list-heading, .hivelog-table-actions, …)` allow-list of
   named context-wrapper classes, documented only in that file's own
   header comment. A button rendered anywhere else — however correctly
   classed, even via the `hivelog:button` SDC component — gets *no*
   styling at all, not a fallback/unstyled-but-visible state. First fix
   attempt on the sensor-device page (switching the raw `#type: link` to
   the SDC component with `variant: danger`) still rendered completely
   plain for exactly this reason, until the missing wrapper was found.
   This is precisely the kind of gap [[0012-audit-action-buttons-across-pages]]
   (closed 2026-06-26) was meant to catch, but nanoprobe's and
   collective's own canonical-page actions were added afterwards and
   never went through that audit — the same "submodule ships a UI after
   the audit closed" drift [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]]
   found in the breadcrumb builder the same day.

## Acceptance criteria
- [x] `SensorDeviceController::view()`'s config-download warning text
      wrapped in a new `.nanoprobe-config-warning` class (new
      `modules/nanoprobe/css/nanoprobe.sensor-device.css` +
      `sensor_device_config` library) — a left-striped warning-tint box
      matching the dashboard's own `.hivelog-attention__row--warning`
      severity-stripe convention.
- [x] `ApiClientController::view()`'s endpoint reference block wrapped
      in the same shared `.hivelog-notice--warning` box as the sensor
      device page (see below), plus `.collective-api-client-endpoints`
      for its own content-specific styling (a code-chip treatment for
      the inline URL) — new `modules/collective/css/collective.api-client.css`
      + `collective/api_client` library, and collective's first-ever
      `collective.libraries.yml`.
- [x] Both "Download Configuration" and "Regenerate Token" switched
      from a raw `#type: link` with manual `button`/`button--primary`
      classes to the `hivelog:button` SDC component with
      `variant: danger` — both actions invalidate prior state (a
      downloaded config / an in-use token), matching the `danger`
      variant's existing semantics (Delete actions) rather than
      `primary`.
- [x] Two new context-wrapper classes,
      `.nanoprobe-sensor-device-actions` and
      `.collective-api-client-actions`, added to `hivelog.buttons.css`'s
      central `:is()` allow-list (all 7 rule blocks) and its header
      docblock — the established single-source-of-truth extension
      point, not a per-module workaround.
- [x] Kernel tests: `SensorDeviceControllerTest` updated for the new
      button structure; new `ApiClientControllerTest` created from
      scratch (no prior coverage of `ApiClientController::view()`
      existed at all) covering the same shape plus access gating
      (owner vs. outsider vs. view-only).
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`, no
      regressions.
- [x] Verified live against `cms2`, via each page's own aggregated CSS
      bundle (fetched with its full query string): both
      `.nanoprobe-sensor-device-actions` and
      `.collective-api-client-actions` appear in the `:is()` allow-list,
      `.button--danger`'s token-driven red styling resolves under both,
      and both pages' notice boxes render identically (same
      `.hivelog-notice--warning` rules) with only their
      content-specific rules (paragraph text vs. heading/list/code)
      differing.

## Implementation notes
- Considered wrapping the new buttons in one of the *existing* context
  classes (`.hivelog-inspection-actions`, `.hivelog-queen-actions`,
  `.hivelog-queen-observation-actions`) instead of registering new
  ones — same naming pattern ("canonical-page action area for entity
  X"), and shorter diff. Rejected: a grep across the whole codebase
  found those three are referenced *only* inside `hivelog.buttons.css`
  itself — dead CSS, not actually applied to any current markup — so
  reusing one would have been coincidence, not an intentional shared
  context. Registering a new, correctly-scoped class per entity matches
  what every other context wrapper in the file actually does.
- Initially gave the two pages *different* notice styling — a
  warning-tinted box for the sensor device page's destructive-action
  text, a neutral bordered box for the API Client page's endpoint
  reference (informational, not itself a warning). User feedback:
  wanted the same look and feel on both regardless of that semantic
  distinction. Rather than duplicate identical CSS across two
  module-owned files, extracted the shared box styling (margin,
  padding, left-stripe, tint, radius) into a new core class,
  `.hivelog-notice--warning` (`css/hivelog.notices.css`,
  `hivelog/notices` library) — matching this codebase's own
  established pattern of centralizing shared cross-module visual
  language in core (`hivelog.buttons.css`, `hivelog.tables.css`)
  rather than letting each module re-invent it. Both controllers now
  apply `hivelog-notice--warning` for the box itself, plus their own
  module-specific class (`collective-api-client-endpoints`) for
  content-only rules (heading/list/code-chip styling) that don't
  belong in the shared class. `nanoprobe.sensor-device.css` and its
  `sensor_device_config` library, which held only the now-superseded
  box rules, were deleted rather than left as dead weight.
- This is the same day's second and third finding of "a submodule
  shipped a UI surface after an earlier audit/consistency task closed,
  and nothing caught the drift" (after [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]]'s
  breadcrumb gaps) — worth treating as a standing risk for
  nanoprobe/nexus/collective specifically, not a one-off, per
  [[action-button-consistency]]'s own updated key findings.

## Related
- Project:: [[action-button-consistency]]
- Tasks:: [[0010-define-button-tokens-and-source-of-truth]],
  [[0012-audit-action-buttons-across-pages]] (the original audit this
  extends), [[0078-sensor-device-configuration-descriptor]],
  [[0101-collective-rescope-to-api-client]],
  [[0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities]] (the
  same day's parallel "submodule UI missed by an earlier consistency
  pass" finding)
- Decisions:: [[0012-action-button-design-system]]
- Commits::
