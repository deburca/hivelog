---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0074: Sensor data ingestion architecture

## Status
accepted. The one assumption flagged as "most worth double-checking" — an
open, self-built platform rather than a single commercial vendor's stack —
was confirmed directly: "Intention is to build an open platform." The
self-hosted-middleware-vs-direct-webhook question remains a documented,
non-blocking recommendation (§2) rather than a hard requirement, and is
free to be revisited once device count grows.

## Context
The user wants to move HiveLog from purely manual record-keeping (hefting a
hive to guess its weight, writing an inspection note) towards **automated**
data collection: weight, temperature, humidity, acoustic and similar
sensors, feeding readings into HiveLog continuously rather than only when a
beekeeper visits. Four sources were reviewed to ground this decision:

- [5 Innovative Hive Monitoring Technologies](https://www.farmstandapp.com/61561/5-innovative-hive-monitoring-technologies-for-beekeepers/) —
  general-audience overview (weight, temperature/humidity, acoustic,
  camera, integrated platforms); light on concrete vendors.
- [Hivekraft: Datenbasierte Betriebsführung](https://hivekraft.com/en/learn/fortgeschritten/09-datenbasiert) —
  the most concrete of the four: per-sensor-type cost ranges and a direct
  LoRaWAN/WiFi/GSM comparison (see below).
- [ScienceDirect S0168169924009475](https://www.sciencedirect.com/science/article/pii/S0168169924009475) —
  inaccessible (403, paywalled); related open-access literature on the same
  topic was substituted (see Market landscape below).
- [Apiculture.ai: Data-Driven Apiary Management](https://apiculture.ai/blog/data-driven-apiary-management) —
  a vendor blog (HiveShield), but its "data hierarchy" (which metric is
  worth collecting first) and four-stage "management loop" (continuous
  monitoring → anomaly detection → targeted inspection → intervention) are
  both directly useful and map cleanly onto concepts HiveLog already has.

### Market landscape
Two distinct families of existing solution:

- **Commercial, closed platforms**: Arnia, BroodMinder, Nectar, BeeWise,
  Hivemind, Bee Smart Technologies, HiveSense. Each bundles its own
  hardware, its own cloud, and its own app — a beekeeper buys into one
  vendor's whole stack. BEEP is a partial exception: it explicitly
  aggregates several *other* vendors' Bluetooth sensors (BroodMinder, BEEP,
  Govee, SensorPush, Inkbird) onto one timeline, which is closer in spirit
  to what's being asked for here.
- **Open-source / DIY, community-run**: [Hiveeyes](https://hiveeyes.org)
  (Berlin, since 2014) is the most relevant prior art — a "flexible toolkit
  for beehive monitoring" built entirely on off-the-shelf open components:
  MQTT (Eclipse Mosquitto) as the transport, Kotori as the ingestion
  bridge, InfluxDB for storage, Grafana for dashboards, mqttwarn for
  alerting. It explicitly documents itself as infrastructure other
  projects build custom sensor nodes against, not a fixed product. Older
  sibling projects (Hivetool since 2010, the "Bienenwaage" ATmega+WiFi
  scale since 2011, Open Source Beehives since 2013) independently arrived
  at the same shape: cheap MCU + sensor → some transport → a generic
  ingestion point → a store a dashboard reads from.

Both families converge on the same three-layer shape — **sensor node →
transport/bridge → ingestion store** — which is exactly the layering
proposed below. The commercial platforms collapse all three layers behind
one vendor's app; the open projects keep them separate, which is what
makes them extensible to a "multitude of sensors... start small and extend
integration by integration," per the user's explicit brief.

### What's actually worth measuring, and in what order
Per Apiculture.ai's "data hierarchy" (independently corroborated by the
other three sources):

1. **Weight** — the single most information-dense metric. Reveals nectar
   flow, a sudden 1–3 kg loss (a swarm departed), winter stores, and
   feeding uptake, all from one continuously-logged number.
2. **Acoustic** — pre-swarm piping (24–72 h advance warning), queenless
   "roaring" (detectable within hours), foraging-activity level. The most
   technically demanding to *transmit* (see acoustic scoping below).
3. **Temperature / humidity** — brood-nest temperature (34–35 °C = healthy
   brood; sustained drops = a population/health problem), external
   temperature/humidity as context.
4. **Chemical (VOC/VSC)** — the most advanced and least mature; flagged by
   the source itself as "the most technically demanding monitoring
   modality... the most significant remaining development potential."
   Explicitly out of scope here.

Hivekraft's cost figures (EUR, retail, 2026): hive scales €150–400,
internal temperature sensors €30–80, external temperature/humidity
€20–50, acoustic €50–150 ("experimental"), GPS trackers €30–80
(anti-theft). These roughly bracket what a DIY build (a load cell + HX711
+ an MCU + a radio, of the kind already sourced by the team) costs to
assemble per node, which is the basis for recommending weight as the
starting sensor in [[0075-sensor-hardware-and-connectivity-selection]].

### Scoping questions this ADR has to settle
1. **Buy a vendor platform, or build a generic ingestion point?** A vendor
   platform (BroodMinder, Arnia, ...) gets working hardware fastest but
   locks the whole effort to one company's sensors, app, and pricing —
   directly contrary to "a multitude of sensors... start small and extend."
   Building a small, generic, transport-agnostic ingestion API inside
   HiveLog — the Hiveeyes/open-source shape, not the vendor-app shape — is
   the only option that satisfies the brief. This does **not** rule out a
   vendor integration later (a future adapter that pulls from a vendor API
   and re-posts into the same ingestion endpoint is a natural follow-up,
   not a competing architecture).
2. **What does HiveLog itself need to speak?** Nothing protocol-specific.
   LoRaWAN, MQTT, Bluetooth and cellular are all radio/transport concerns
   that live *before* the data reaches HiveLog — see Decision §2. HiveLog
   only needs one well-documented HTTP contract; everything upstream of
   that is a "bridge" concern covered by
   [[0075-sensor-hardware-and-connectivity-selection]], not this ADR.
3. **What registers a physical device, and how is it authorised?** Neither
   the existing apiary-scoped human-authorisation model
   ([[0019-authorisation-model]]) nor a full Drupal user account fits a
   battery-powered sensor node — see Decision §3/§4.
4. **How much data, kept how, for how long?** A weight sensor logging every
   15–60 minutes (Hivekraft's own recommended interval) is small; naive
   unbounded per-second-resolution storage is not. This has to be decided
   now, even if implementation is deferred, because it constrains the
   entity design — see Decision §5.
5. **Where does acoustic data fit, given HiveLog is a lightweight
   Drupal-backed logbook, not an ML/audio backend?** Raw audio cannot be a
   `float` reading and must not be routed through Drupal at all — see
   Decision §6.
6. **How does this connect to the dashboard?** HiveLog already has a
   generalised "Needs attention" alert queue
   ([[0057-dashboard-information-architecture]]) fed by exactly one
   pattern so far (the seasonal calendar,
   [[0025-seasonal-calendar-and-hive-action-tracking]]) plus low-stock
   inventory. Sensor anomalies are a natural third feed into the same
   queue rather than a parallel notification system — see Decision §7.

**Resolved / remaining questions:**
- ~~Does the beekeeper want to commit to *building* device firmware/
  bridges, or would a single commercial platform's faster time-to-data be
  preferred despite the vendor lock-in?~~ **Resolved 2026-09-20**: "Intention
  is to build an open platform." The rest of this ADR is written on that
  basis without qualification.
- Self-hosted ingestion middleware (Mosquitto/Kotori-style, à la Hiveeyes)
  vs. HiveLog receiving webhooks directly with no separate broker in
  between — Decision §2 recommends the latter for a small pilot to avoid
  standing up and operating a second system, but this should be
  re-examined once device count grows past a handful. Not blocking.

## Decision (recommended)

### 1. Two new entities, mirroring the `CalendarAction`/`HiveActionLog` "plan vs. record" split
Following the same parent-registration-vs-time-series-record pattern
already established by [[0025-seasonal-calendar-and-hive-action-tracking]]
([[0003-code-defined-entity-schema]]):

**`SensorDevice`** (the registered hardware — one row per physical node):
- `label` (`string`, required) — e.g. "VV-01 Scale".
- `apiary` (`entity_reference` → `apiary`, required).
- `hive` (`entity_reference` → `hive`, required only when `scope = hive`).
- `scope` (`list_string`: `apiary` | `hive`, required) — **reuses
  `CalendarAction.scope`'s established duality exactly**: a device
  monitoring one specific hive (a per-hive weight or in-hive
  temperature/acoustic sensor) is hive-scoped; a device serving the whole
  site (an ambient weather station, a site GPS/tilt tracker) is
  apiary-scoped. No new scoping concept is introduced.
- `device_type` (`list_string`, code-defined: `weight`, `temperature_humidity`,
  `acoustic`, `entrance_counter`, `gps`, `multi`, `other`) — classification
  only, shown in the UI; does not restrict which `metric` values a device
  may report (validated separately, see §4).
- `transport` (`list_string`, code-defined: `lorawan`, `wifi`, `cellular`,
  `bluetooth`, `other`) — informational, for the beekeeper's own
  reference; no behavioural effect on ingestion.
- `token` (`string`, server-generated, never shown again after creation
  except via an explicit "regenerate" action) — the device's credential,
  see §3.
- `enabled` (`boolean`, default `TRUE`) — mirrors `CalendarAction.enabled`;
  a disabled device's readings are rejected outright (403), not silently
  accepted-but-hidden — an explicit, auditable "this device is
  deauthorised" state rather than a soft mute.
- `last_seen` (`timestamp`, updated on every accepted reading) — powers a
  "device offline" alert (see §7) without a separate polling job.
- Standard `uid` / `created` / `changed`.

**`SensorReading`** (one data point — the time series):
- `sensor_device` (`entity_reference` → `sensor_device`, required).
- `metric` (`list_string`, code-defined, required — see §4).
- `value` (`float`, required).
- `recorded` (`timestamp`, required) — when the *sensor* took the
  measurement, supplied by the device/bridge.
- `received` (`created` timestamp, automatic) — when *HiveLog* ingested
  it. Kept distinct from `recorded` deliberately: LoRaWAN duty-cycle
  limits and store-and-forward mean a batch of readings taken overnight
  can arrive in one uplink hours later, and the gap itself (large
  `received - recorded`) is a useful "this device had connectivity
  trouble" signal in its own right.
- No `edit`/`delete` form and no `add` form for humans — these rows are
  machine-written only, via the ingestion endpoint in §2. An
  `administer hivelog` user can still delete via the entity API for
  cleanup (e.g. a miscalibrated device's bad readings), but there is no
  UI encouraging manual entry — manual weight already has a home
  (`HiveInspection.weight`, untouched by this ADR, see §7).

Both entities resolve their governing apiary through
`ApiaryAccessTrait::resolveApiary()` exactly like every other entity
(`SensorDevice` → `apiary` directly, or → `hive` → `apiary`;
`SensorReading` → `sensor_device` → apiary/hive → apiary), so **reading**
sensor data back out (dashboard, hive/apiary pages, any future export)
goes through the same permission model as everything else in the module —
[[0019-authorisation-model]] is not touched. Only *writing* a reading (the
ingestion endpoint) uses a different, new authorisation mechanism, scoped
narrowly to that one route — see §3.

### 2. Ingestion is one generic, transport-agnostic HTTP endpoint — HiveLog never speaks LoRaWAN, MQTT, or Bluetooth
A single custom controller route:

```
POST /hivelog/api/sensor-readings
Authorization: Bearer <device token>
Content-Type: application/json

{"metric": "weight_kg", "value": 42.7, "recorded": "2026-09-21T10:15:00Z"}
```

or a batch (for store-and-forward catch-up after a connectivity gap):

```json
[
  {"metric": "weight_kg", "value": 42.7, "recorded": "2026-09-21T10:15:00Z"},
  {"metric": "temp_internal_c", "value": 34.8, "recorded": "2026-09-21T10:15:00Z"}
]
```

This is the entire integration surface HiveLog exposes. **Everything
upstream — LoRaWAN join procedures, a gateway, a network server, MQTT,
Bluetooth pairing — is a "bridge" concern that translates whatever a
sensor node speaks into this one JSON contract.** A bridge can be a
LoRaWAN network server's built-in webhook/uplink integration (The Things
Stack and ChirpStack both support pushing uplinks straight to an HTTP URL
— no separate broker needed for a pilot-scale deployment), a small
script subscribing to an MQTT topic and re-posting each message as this
JSON shape, a Home Assistant or Node-RED flow, or — as Hiveeyes shows is
viable at larger scale — a self-hosted Mosquitto + bridge pair for
20+ colonies. **This is the concrete mechanism behind "start small and
extend integration by integration":** supporting a new sensor brand or a
new radio protocol means writing or configuring a new bridge, never
changing HiveLog itself, because the contract above is the only thing
HiveLog's code depends on.

Responses: `201` with the created reading id(s) on success; `401` for a
missing/invalid token; `403` for a disabled device; `422` for a validation
failure (unknown `metric`, non-finite `value`, unparseable `recorded`).
Rate-limiting a misbehaving/compromised device (`429`) is a real concern
worth having but is deferred to implementation — noted here so it isn't
forgotten, not solved by this ADR.

### 3. Device authentication & provisioning: a per-device secret token, delivered as a generated configuration descriptor
Provisioning a full Drupal user account per physical sensor (to reuse
core authentication, as [[0022-authentication-and-membership]] otherwise
prefers) would be a heavyweight, awkward fit — a sensor node isn't a
person, has no email, and shouldn't be able to log into the site. Instead,
`SensorDevice.token` is a server-generated, revocable, per-device secret,
validated by a small custom access-check service the ingestion controller
calls directly (`Authorization: Bearer <token>` → look up the
`SensorDevice` with a matching hashed token → confirm `enabled`). This
keeps the credential real, scoped to exactly one device, and instantly
revocable (delete or disable the `SensorDevice`, or regenerate its token)
without pulling in a contrib OAuth/API-key module —
[[0006-contrib-dependency-policy]]'s minimal-dependency policy holds; this
is a small, self-contained mechanism, not a framework.

This directly fulfils [[0022-authentication-and-membership]]'s own
forward-reference: *"If a programmatic API (REST/JSON:API) is ever added,
it must require authenticated requests, reuse the same access checks
([[0020-access-parity-custom-routes]]), and get its own ADR."* — this is
that ADR. The "reuse the same access checks" clause applies to *reading*
sensor data (§1, via `ApiaryAccessTrait` as normal); it cannot apply
literally to the *write* path, since there is no Drupal-session user on
the other end of a device's HTTP request — this is the one deliberate,
narrow exception, and it is scoped to a single route.

**CSRF**, per [[0018-csrf-and-safe-http-methods]]: the ingestion route is
a `POST` (correct verb for a state-changing write) but does **not** carry
Drupal's session-cookie `_csrf_token` — a device makes a stateless,
credentialed request with no ambient browser session to ride, so
session-based CSRF does not apply to it (the same reasoning core's own
REST/JSON:API modules use for token-authenticated requests). The
bearer-token check *is* this route's protection against forged writes; it
is a deliberate, reviewable deviation from 0018's literal mechanism, not
an unprotected `GET` mutation, which 0018 continues to prohibit
everywhere else without exception.

**Provisioning a physical device with its token is a separate problem
from validating it**, and needs its own answer: how does a piece of
firmware — generic, reused unchanged across every device, per the
explicit brief — learn *this* device's endpoint URL, *this* device's
token, and the payload shape to send, without hand-editing and
recompiling firmware source per device (which would also mean a secret
sitting in whatever build tooling/version control the firmware source
lives in)? The answer is a small, generated **configuration descriptor**:
a JSON document, downloadable on demand from the `SensorDevice` canonical
page (gated by the same entity-update access as the device itself — only
someone who can already manage that device can retrieve its credential),
that firmware reads at boot from local storage (an ESP32's SPIFFS/
LittleFS partition, or an SD card) rather than from anything baked into
the compiled program:

```json
{
  "config_version": 1,
  "device": {"id": 42, "label": "VV-01 Scale"},
  "endpoint": {
    "url": "https://example.org/hivelog/api/sensor-readings",
    "method": "POST",
    "content_type": "application/json"
  },
  "auth": {"type": "bearer", "token": "<device secret token>"},
  "transport": {"batch": true, "suggested_interval_seconds": 1800},
  "metrics": ["weight_kg", "battery_voltage", "signal_rssi"]
}
```

- `config_version` — lets a future breaking schema change be detected and
  refused by old firmware rather than silently misinterpreted.
- `endpoint` / `auth` — exactly this ADR's ingestion contract (§2) and
  this device's token (above), machine-readable instead of hand-copied
  into source.
- `transport.batch` / `suggested_interval_seconds` — whether firmware
  should queue readings through a connectivity gap and send them as one
  batched array (§2), and HiveLog's own recommended sampling interval
  (§5's 15–60 minute guidance), as a default firmware may use — advisory,
  not enforced; the ingestion endpoint does not reject faster reporting.
- `metrics` — the exact `SensorReading::METRIC_TYPES` strings (§4) this
  device's `device_type` is expected to report, generated from HiveLog's
  own taxonomy so firmware never has to hardcode or guess a metric-name
  string independently (removing a whole class of "typo → silent 422"
  failure).

Deliberately **not** included: which physical pin reads which sensor, or
any calibration constant — that is firmware/hardware-specific and stays
in firmware's own local configuration, since HiveLog has no way to know a
device's wiring and shouldn't pretend to.

**Who actually reads this file** depends on the build, per
[[0075-sensor-hardware-and-connectivity-selection]]: for a WiFi-direct
node, the sensor node's own firmware does — it is the thing making the
HTTP call. For the recommended point-to-point-LoRa pilot, the tiny
battery/radio-only sensor node never speaks HTTP at all; it is the
**receiver** node's firmware (the one bridging onto WiFi) that reads this
descriptor and makes the authenticated call on the sensor node's behalf.
Either way, exactly one component in the chain needs this file, and
HiveLog's role is the same regardless: generate it, gate its download
behind normal entity access, and let regenerating the underlying token
(§1) invalidate any previously-downloaded copy automatically. Self-
provisioning — a device fetching or refreshing this descriptor over the
network by itself, rather than a beekeeper copying it on once during
setup — is a natural future enhancement, explicitly deferred rather than
built now: it would need its own, different credential (a short-lived
claim code, not the data token itself) and is real added complexity a
manual-copy Phase 1 does not need.

### 4. Metric taxonomy stays code-defined, per [[0003-code-defined-entity-schema]]
`SensorReading.metric` is a small, curated `list_string` (a
`SensorReading::METRIC_TYPES` constant, mirroring `CalendarAction`'s
`category` field and `Queen::QUEEN_COLOUR_MAP`), not a free-form string.
Starting set: `weight_kg`, `temp_internal_c`, `temp_external_c`,
`humidity_internal_pct`, `humidity_external_pct`, `battery_voltage`,
`signal_rssi`. A genuinely new metric (e.g. `entrance_traffic_count` for
an IR-beam entrance counter) is added the same low-risk way every other
allowed-values extension in this module already works — extend the
constant, ship an update hook. This is a deliberate trade-off: a
free-form string would need zero schema changes to extend, but would
also accept typos, unit confusion (`weight_kg` vs `weight_lb` silently
mixed for the same device), and unbounded cardinality with no validation
at all. Given the module's existing, consistent preference for
code-defined vocabularies over open strings, curated-but-extensible wins
here too.

### 5. Retention: cap raw readings, roll up for long-term trends
At Hivekraft's own recommended 15–60 minute sampling interval, one metric
on one device generates roughly 9,000–35,000 rows a year — manageable as
plain content-entity rows for a handful of hives, but this must not be
allowed to grow unbounded as devices multiply over years. Decision:
- The ingestion contract does not enforce a sampling interval — that's a
  firmware/bridge choice — but §7's UI documentation and any published
  bridge guidance recommend 15–60 minutes, matching every source
  reviewed. A device hammering the endpoint far faster than that is a
  configuration bug to fix at the source, not something HiveLog should
  silently absorb forever.
- Raw `SensorReading` rows are retained for a rolling window (proposed:
  2 years) via a cron-driven purge, past which only a daily
  min/max/avg rollup survives. The rollup's exact shape (a new
  `SensorReadingDaily` entity vs. a computed aggregate table) is left to
  the implementation task — this ADR fixes the *policy* (raw data is not
  kept forever; a long-term trend must still be answerable after purge),
  not the storage mechanics.
- This is a deferred-but-decided concern: Phase 1 (§8) can ship without
  the purge/rollup job existing yet, since a pilot of a few devices for a
  few months will not hit the volume where it matters, but the job must
  exist before general rollout — tracked as a Phase 3 follow-up, not
  optional forever.

### 6. Acoustic and other derived/event data is explicitly out of scope for Phase 1
Raw audio cannot be a `SensorReading.value` (a single float), and routing
raw waveforms through a Drupal-backed logbook is the wrong architecture
regardless — feature extraction and classification (pre-swarm piping,
queenless "roaring") belong at the edge (on the sensor node or a local
gateway/SBC), not in HiveLog. **No raw audio, and no continuous-audio
streaming, is ever accepted by the ingestion endpoint.** A future phase
could add a `SensorEvent` entity (discrete, timestamped, labelled
detections — `"pre_swarm_acoustic"`, `"queenless_acoustic"` — with a
confidence score and free-text notes) once a real edge-classification
pipeline exists to produce them; deliberately not designed here, since
speccing an entity for a capability with no reference implementation yet
would be guessing. Chemical (VOC/VSC) sensing is even earlier-stage per
the Apiculture.ai source itself and is not designed for at all.

### 7. Dashboard integration: a new alert source into the existing "Needs attention" queue, plus a per-hive/apiary sensor panel
Reuses [[0057-dashboard-information-architecture]]'s established pattern
— the queue is already fed by two independent passes (seasonal calendar,
low-stock inventory) merged into one list; sensor anomalies become a
third pass, not a parallel notification system:
- **Device offline** — `SensorDevice.last_seen` older than some threshold
  (e.g. 2× its own typical reporting interval, or a flat 24 h) becomes a
  `warning` row, mirroring the low-stock pattern's severity handling.
- **Sudden weight drop** (a possible swarm) — a same-day drop past a
  threshold (Apiculture.ai's own figure: 1–3 kg) on a `weight_kg` metric
  becomes a `critical` row, alongside the seasonal calendar's existing
  overdue-action severity.
- **Temperature out of brood range** — a sustained `temp_internal_c`
  reading well outside 33–36 °C becomes a `warning` row.

These are threshold checks against the two most recent readings per
device/metric — no forecasting or ML, matching the module's existing
"simple, explainable rules over a model" style (the seasonal calendar is
a fixed week-number window, not a predictive schedule either).

On the hive/apiary canonical pages, a new "Sensors" panel shows each
attached device's latest reading per metric plus a trend chart. The
existing weight histogram
(`HiveController::buildWeightHistogram()`, inline SVG, no charting-library
dependency) is the template to extend, not replace: `HiveInspection.weight`
(manual, hefted-by-hand, point-in-time) is untouched by this ADR and keeps
its own histogram exactly as today. A hive with an attached weight
`SensorDevice` gains a second, much denser chart built from `SensorReading`
rows (pre-aggregated to daily points before rendering, per §5, so the SVG
stays small regardless of sampling frequency) — the two data sources sit
side by side rather than being merged, since they have different
precision and provenance and conflating them would misrepresent both.

### 8. Phasing
- **Phase 1** (this ADR's minimum viable slice): `SensorDevice` +
  `SensorReading` entities, the ingestion endpoint + device-token auth,
  the generated configuration-descriptor download (§3) — without it, no
  real device can actually be provisioned, so it ships with the endpoint,
  not after it — numeric metrics only (weight, temperature, humidity,
  battery, RSSI), a read-only "Sensors" panel on hive/apiary pages with
  the daily-rollup chart.
- **Phase 2**: dashboard "Needs attention" integration (§7's three alert
  rules).
- **Phase 3**: the retention/rollup cron job (§5) — required before
  general rollout, not required for a small pilot.
- **Phase 4 (not designed here)**: `SensorEvent` for acoustic/derived
  detections (§6); any commercial-vendor pull adapter, built on top of the
  same ingestion contract rather than a separate code path.

## Consequences
- Positive: one small, protocol-agnostic contract supports an unbounded
  variety of sensor hardware and radio protocols without HiveLog code
  changes — new hardware needs a new bridge, not a new HiveLog feature.
  Reuses every established pattern this module already has (code-defined
  schema, `ApiaryAccessTrait` apiary/hive scope duality, custom
  controllers, inline-SVG charting, the dashboard's merged-alert-passes
  queue) rather than introducing a parallel subsystem. The retention
  policy is decided before any data exists, not discovered as a problem
  after a year of unbounded growth. Keeps HiveLog's own dependency
  footprint at zero new contrib modules. The generated configuration
  descriptor (§3) means firmware is written and flashed exactly once and
  reused unchanged across every device — a new sensor node is a copy of
  a downloaded file onto local storage, not a rebuild.
- Negative / trade-offs: this is a genuinely new kind of surface for the
  module — its first machine-authenticated write API, with its own
  (narrow, documented) exception to both the session-CSRF rule and the
  "every entity goes through `ApiaryAccessTrait`" rule for the write path
  specifically. Building the "bridge" layer (LoRaWAN gateway/network
  server config, or an MQTT relay script) is real, ongoing work that
  lives *outside* this module and its test suite, and is a beekeeper/ops
  responsibility this ADR does not automate. The configuration descriptor
  carries the device's plaintext secret token, so its download path and
  the beekeeper's own handling of the file (whatever copies it onto an SD
  card or flashes it into a filesystem) both become part of the
  credential's attack surface — mitigated by gating the download behind
  the device's normal entity-update access and by the token staying
  instantly revocable, but a real, non-zero widening compared to a token
  that only ever lived in one place. No forecasting/anomaly ML is
  proposed — Phase 2's alerts are fixed thresholds, which will both
  under- and over-fire relative to a trained model; accepted as
  consistent with the module's existing "simple, explainable rules" style
  rather than treated as a gap to close immediately. Acoustic/VOC sensing
  — two of the four metrics the original sources discuss — are explicitly
  not designed for yet.
- Follow-up tasks: tracked under the new
  [[sensor-data-collection]] project, alongside
  [[0075-sensor-hardware-and-connectivity-selection]] for the hardware/
  protocol side of the same initiative.
