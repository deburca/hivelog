---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[sensor-data-collection]]"
area: hardware
created: 2026-09-23
branch: feature/0115-varroa-camera-sensor-hardware-build
release:
depends-on: ["[[0101-varroa-camera-sensor-hardware-design]]"]
blocked-by:
---
# Task: Varroa camera sensor — build + integrate

## Context
The hardware/optical design for an automated varroa (mite) drop
counter — a camera eke mounted below the existing mesh floor,
photographing a sticky board, with on-device image analysis producing
a daily mite count — is captured in
[[0101-varroa-camera-sensor-hardware-design]]. This task tracks
actually building it, mirroring
[[0079-pilot-weight-sensor-hardware-build]]'s own shape (a real
hardware deliverable, not a PHP/test-suite one) for a second
sensor type. Explicitly a **future-stage task** — not triggered by any
current work, `backlog`/`low priority` deliberately, same status
[[0096-off-grid-gateway-power-design]] sits at until its own trigger
condition arrives.

## Acceptance criteria
- [ ] Resolve [[0101-varroa-camera-sensor-hardware-design]]'s own open
      question first: does mite-vs-debris classification run on-device
      (ESP32-S3) or server-side (an uploaded image)? This decides
      whether [[0074-sensor-data-ingestion-architecture]]'s endpoint
      needs a new image-upload path or stays exactly as-is. Do not
      start hardware procurement until this is settled — it changes
      what the sensor node's firmware needs to do.
- [ ] Market survey of real, currently-purchasable parts (autofocus
      OV5640-class camera module, ESP32-S3 dev board, a lens matching
      the ~70° HFOV / ~16 cm working-distance option from
      [[0101-varroa-camera-sensor-hardware-design]]'s distance table),
      mirroring [[0097-hardware-infrastructure-and-component-catalog]]'s
      own product-level (not category-level) approach — prices and
      availability re-checked at order time, not assumed from this
      task's own drafting.
- [ ] Collect labelled training images (mites vs. wax cappings, pollen,
      dead bees, other debris) — genuine lead time before any
      classifier can be built or evaluated, per
      [[0101-varroa-camera-sensor-hardware-design]]'s own note that
      this needs planning for, not a same-day step.
- [ ] Build the camera eke: same timber as the hive (per
      [[0101-varroa-camera-sensor-hardware-design]], keeps the stack
      square and weathertight), ~12–16 cm tall, mesh-covered side vents
      for condensation control, camera + diffuse white LEDs mounted
      under the mesh pointing down, a slide-out tray at the base for
      the sticky white board.
- [ ] Assemble and power the sensor node (ESP32-S3 + camera + LEDs) —
      solar + 18650 cell per
      [[0095-renewable-power-for-apiary-equipment]]'s existing pattern;
      confirm that pattern actually covers an image-capture-plus-
      classification power draw, which is a heavier duty cycle than
      the weight sensor's plain ADC read even with LEDs only lit for
      the capture window.
- [ ] Firmware: wake daily, light the LEDs, capture one image, run (or
      queue for upload) mite classification, compute the count as the
      delta from the previous day's image of the same board, report
      `varroa_mite_count_24h` via the existing ingestion endpoint, deep
      sleep.
- [ ] Register the new metric: `varroa_mite_count_24h` added to
      `SensorReading::METRIC_TYPES`, plus a corresponding
      `SensorDevice::DEVICE_TYPE_METRICS` device type (naming TBD at
      build time — `varroa_camera` or similar) — the only HiveLog-side
      code change this whole sensor needs, per
      [[0101-varroa-camera-sensor-hardware-design]]'s own Decision.
- [ ] Weekly board-swap procedure documented and followed during
      testing: swap the sticky board, log the swap timestamp, confirm
      the delta-based daily count logic actually handles a swap day
      correctly (count resets, doesn't show a false spike/drop).
- [ ] Validate mite-count accuracy against a manual count (alcohol-wash
      or sugar-roll, the beekeeper's existing method) on at least one
      real hive, for at least a few consecutive days, before treating
      the automated count as a trustworthy standalone signal.
- [ ] Document wiring, firmware, and the eke's build steps
      reproducibly, following [[0079-pilot-weight-sensor-hardware-build]]'s
      own precedent (`hardware/<name>/README.md` + PlatformIO project) —
      continuing the `oneof9`-style naming
      ([[0079-pilot-weight-sensor-hardware-build]]'s own numbering) for
      whichever slot this becomes once weight/temperature/humidity/
      acoustic are assigned theirs.

## Implementation notes
- No kernel/unit tests are expected from the hardware-build portion of
  this task, matching 0079's own precedent — real hardware has no way
  to be validated by a PHP test suite. The two small `METRIC_TYPES`/
  `DEVICE_TYPE_METRICS` additions are ordinary code changes and should
  get the same phpcs/test-suite treatment every other hivelog change
  does, once made.
- This task is deliberately unscheduled — do not pull it forward
  without the user explicitly asking, per
  [[sensor-data-collection]]'s own Phase 1/2/3 sequencing discipline.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0101-varroa-camera-sensor-hardware-design]],
  [[0074-sensor-data-ingestion-architecture]],
  [[0095-renewable-power-for-apiary-equipment]],
  [[0097-hardware-infrastructure-and-component-catalog]]
- Tasks:: [[0079-pilot-weight-sensor-hardware-build]] (the precedent
  this task's own shape follows)
- Commits::
