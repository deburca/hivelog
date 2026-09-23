---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-23
supersedes:
---
# ADR-0101: Automated varroa mite detection — camera sensor hardware design

## Status
accepted. Captures a hardware/optical design explored in a separate
conversation, for a future build — **nothing here has been ordered,
prototyped, or coded against**; this is the same "shopping list, not
inventory" status [[0097-hardware-infrastructure-and-component-catalog]]
was at before [[0079-pilot-weight-sensor-hardware-build]] started.
Gates a future hardware task the same way 0097 gates 0079: real
procurement and a market-priced component catalog (mirroring 0097's
own) are still needed before build can start, not just this optical/
mechanical design. Does not gate any pure-software work in
[[sensor-data-collection]] — nothing about HiveLog's own ingestion
contract needs to change for this sensor (see Decision).

## Context
[[sensor-data-collection]]'s own Scope section explicitly keeps
acoustic sensing out of scope: it "needs edge signal processing
HiveLog does not do; no `SensorEvent`-style entity is designed yet,
deliberately, since speccing one without a reference implementation
would be guessing." Automated varroa (mite) drop counting looked at
first like the same kind of problem — it also needs edge processing
(image capture + mite counting), not a simple ADC read like the
weight/temperature/humidity metrics already in scope. This ADR is the
reference implementation that resolves that: a design exists, and
critically, its *output* is a single daily number, so it fits
`SensorReading`'s existing numeric-metric shape exactly — no new
entity, no change to HiveLog's own ingestion contract at all (see
Decision). That's the key difference from acoustic sensing, which
would need a genuinely new data shape (a detected *event*, not a
periodic *measurement*) even with a reference implementation in hand.

### Why the camera can't go where a beekeeper would expect
The natural instinct is a camera inside the hive looking down at the
varroa board — but that's exactly where the bees are; nothing can sit
there. The mesh floor beekeepers already use for varroa monitoring
(bees can't reach below it, mites fall through it) is the only clear
sightline available, and it's *underneath* the hive, not inside it.

### Design: a camera eke below the mesh floor
```
Brood box
Base plate: entrance + landing board + varroa mesh (unchanged)
Camera eke, ~12–16 cm tall  ← camera + LEDs mounted just under the mesh, pointing down
  Sticky white board on a slide-out tray at the bottom of the eke
Hive stand
```
A shallow spacer box (an "eke," standard beekeeping terminology for a
spacer added to a hive stack) inserted between the existing floor and
the hive stand. The camera and LEDs mount just under the mesh, pointing
straight down at a sticky white board on a slide-out tray at the
eke's base. The 3 mm mesh keeps bees out of the eke entirely; the
entrance and landing board stay exactly where they are. The only
physical change to the hive is that it now sits 12–16 cm higher on its
stand.

### Camera distance and resolution
Target **≤ 0.15–0.2 mm per pixel** so a ~1.5 mm mite covers at least
5–7 pixels — enough for a classifier to work with. Distance from lens
to board is set by the lens's horizontal field of view (HFOV):

```
d = (W / 2) / tan(HFOV / 2)
```

where `W` is the board width to cover. For a ~45 cm wide board with a
5 MP (2592 px wide) sensor:

| Setup | Lens HFOV | Distance | Resolution |
|---|---|---|---|
| 1 camera, whole board | ~120° wide | ~13 cm | ~0.17 mm/px, worse at the edges — marginal |
| 2 cameras, half board each | ~90° | ~11 cm | ~0.09 mm/px — good, but doubles the camera/wiring/eke-height cost |
| 1 camera, fixed sample area (~22 × 17 cm), scaled up | ~70° | ~16 cm | ~0.08 mm/px — good, and the simplest build |

Mite drop across the board is fairly uniform, so counting a fixed
central sample area and scaling the count up to the full board is
statistically reasonable — it keeps the build to one camera instead of
two, at the cost of some statistical noise on days with genuinely
uneven drop patterns.

## Decision
Build the varroa sensor as a **camera + on-device image analysis
node**, whose output is one new numeric metric per day — a mite drop
count — POSTed to the existing sensor-data-collection ingestion
architecture exactly like `weight_kg` is today. This is the load-
bearing decision: **the image capture and mite counting happen
entirely on the sensor node**, not in HiveLog. HiveLog's own side of
the contract (the `SensorDevice`/`SensorReading` entities, the
`POST /hivelog/api/sensor-readings` endpoint, the config-descriptor
provisioning flow, the Sensors panel) needs no new code to accept
this sensor once built — only a new entry in `SensorReading::METRIC_TYPES`
(a name like `varroa_mite_count_24h`) and a corresponding
`SensorDevice::DEVICE_TYPE_METRICS` device type, both trivial additions
whenever the actual build task starts. Left for that future task, not
made here, per this project's own documents-only scope right now.

Camera/eke build parameters, to be finalised against real product
availability before ordering (mirroring [[0097-hardware-infrastructure-and-component-catalog]]'s
own market-survey step, not yet done here):
- **MCU**: an ESP32-S3 specifically (not the plain ESP32 used for the
  weight pilot) — the S3 variant has the camera peripheral interface
  and enough RAM/PSRAM for on-device image buffering that a bare ESP32
  typically lacks.
- **Camera module**: an OV5640-class 5 MP module, **autofocus variant
  specifically** — many cheap fixed-focus OV5640 boards ship focused
  at infinity, wrong for a ~13–16 cm working distance; either buy an
  autofocus module or plan to manually refocus a fixed-lens one at
  build time.
- **Lighting**: diffuse white LEDs, powered only during the brief
  daily capture window (a few seconds), not continuously — matters for
  both power budget and mite colour rendering (mites are reddish-brown,
  so white light rather than a tinted or narrow-spectrum source).
- **Distance/lens**: per the table above — the single-camera,
  fixed-sample-area setup (~16 cm, ~70° HFOV lens) is the simplest
  build and the one to prototype first.
- **Power**: ESP32-S3 deep-sleeps between daily captures; a small solar
  panel + 18650 cell is expected to be sufficient, matching the weight
  sensor's own solar/battery pattern
  ([[0095-renewable-power-for-apiary-equipment]]) rather than a new
  power design.
- **Board-swap procedure**: the sticky board is swapped on a fixed
  cadence (weekly suggested), each swap logged with its date/time —
  the daily mite count is the *difference* between consecutive daily
  images of the same board, not an absolute count read off one image,
  since mites accumulate on the board between swaps.
- **Condensation control**: the underside of a hive is a humid
  environment — vent the eke sides with mesh-covered openings (not
  sealed) and consider an anti-fog coating on the lens itself.
- **Debris disambiguation**: wax cappings, pollen, and dead bees will
  also land on the board alongside mites. The on-device (or
  server-side, depending on where classification actually runs —
  genuinely undecided, see Open questions) detector needs to
  distinguish these by size, shape, and colour — which means
  **collecting labelled training images early**, before the detector
  itself can be built, is real lead time to plan for, not a footnote.

## Consequences
- Positive: resolves [[sensor-data-collection]]'s own stated blocker
  for any camera-based sensor ("no reference implementation") for this
  specific modality — a real design now exists, distance/resolution
  math is worked out, and the practical build risks (focus, lighting,
  condensation, debris, board-swap logistics, power) are identified
  up front rather than discovered mid-build. Confirms the sensor's
  *output* fits the existing numeric-metric ingestion contract
  unchanged — a materially smaller integration than acoustic sensing
  would be, since no new entity or API shape is needed.
- Negative / trade-offs: this design has not been validated against
  real hardware or real bees — no camera has captured a real mite yet,
  no classifier exists, and the debris-disambiguation problem in
  particular could turn out to need meaningfully more sophisticated
  image processing (and more labelled training data) than this ADR's
  optimistic framing assumes. The 12–16 cm eke permanently raises every
  hive it's fitted to, a physical/ergonomic change beekeepers should
  sign off on per-hive, not something to roll out silently. No market
  survey of real, currently-purchasable parts (OV5640 autofocus
  modules, ESP32-S3 boards, specific lens HFOV options) has been done
  yet — the next step before ordering anything is exactly the kind of
  product-level check [[0097-hardware-infrastructure-and-component-catalog]]
  did for the weight sensor's components.
- Follow-up: [[0115-varroa-camera-sensor-hardware-build]] tracks the
  actual build (backlog, low priority — explicitly a future-stage task,
  not scheduled). No code changes accompany this ADR; the new
  `varroa_mite_count_24h` metric and its `SensorDevice` device type
  are additions for that future task to make, not this one.

## Open questions
- **Where does image classification actually run?** This ADR assumes
  on-device (ESP32-S3) processing, matching the "edge processing, then
  report a simple metric" pattern the Decision section leans on — but
  an ESP32-S3's compute budget for a real mite-vs-debris classifier is
  unverified. A server-side fallback (upload the captured image,
  classify in HiveLog or an external service) is a real alternative
  that would change the ingestion shape — an image upload, not a bare
  numeric reading — and hasn't been designed here.
  [[0074-sensor-data-ingestion-architecture]]'s endpoint only accepts
  numeric metric readings today; a raw-image path would be new scope
  for that ADR, not a natural extension of it.
  Decide before starting [[0115-varroa-camera-sensor-hardware-build]].
  Revised for this same design run: not decided here, deferred to that
  task's own early work.
- How many hives is this worth deploying to, and does mite-count
  accuracy need validation against manual alcohol-wash/sugar-roll
  counts (the beekeeper's existing manual method,
  [[0025-seasonal-calendar-and-hive-action-tracking]]'s
  `varroa_treatment`/`varroa_count` fields) before being trusted as a
  standalone automated signal? Not addressed here.
- Real component sourcing (autofocus OV5640 listings, ESP32-S3 board
  options, lens HFOV choices matching the table above) — a market
  survey mirroring [[0097-hardware-infrastructure-and-component-catalog]]'s
  own approach, not done as part of this ADR.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0074-sensor-data-ingestion-architecture]] (the numeric
  ingestion contract this sensor's daily count reuses unchanged),
  [[0075-sensor-hardware-and-connectivity-selection]] (the pilot
  weight sensor's own hardware precedent),
  [[0095-renewable-power-for-apiary-equipment]] (solar/battery power
  pattern this design assumes it can reuse),
  [[0097-hardware-infrastructure-and-component-catalog]] (the
  standard-interface extensibility framework and market-survey
  approach this ADR's own Open Questions still owes)
- Tasks:: [[0115-varroa-camera-sensor-hardware-build]]
