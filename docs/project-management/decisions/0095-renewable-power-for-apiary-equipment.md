---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0095: Renewable power for apiary equipment (solar + battery)

## Status
accepted, as a constraint on
[[0075-sensor-hardware-and-connectivity-selection]] rather than a
rewrite of it — that ADR is already accepted and other documents
([[0079-pilot-weight-sensor-hardware-build]]) cite specific content
from it, so this records the new requirement and its consequences as
its own decision, the same way [[0087-ai-insights-hosting-and-privacy-model]]
extended [[0083-ai-assisted-apiary-insights]] rather than editing it in
place.

## Context
User directive: "all equipment at/around the apiaries should be powered
by renewable energy — e.g. small solar panel charging a battery that
powers all sensors, controllers, and transmitters." This is a hard
constraint, not a preference — every component
[[0075-sensor-hardware-and-connectivity-selection]] already recommended
needs its actual power draw checked against it, not assumed compatible.

### Scoping "at/around the apiaries"
Read literally, this covers the sensor node (physically at the hive)
and any receiver/gateway that must also be sited near the apiary for
radio range. It does **not**, on the most natural reading, cover
equipment inside the beekeeper's own house — a separate, already-powered
building, not "at/around the apiary" in ordinary language. This ADR
adopts that reading explicitly rather than assuming it silently: **if
that's wrong and the constraint is meant to cover the receiver even when
it's sited inside the house, say so** — it changes which component
carries the hard engineering problem (see below).

### The two components have very different power budgets
Real numbers, not estimates from memory — pulled from current
solar-IoT sizing guidance:

- **A duty-cycled ESP32 sensor node** (deep-sleep between readings,
  waking briefly to read the load cell and transmit over LoRa) draws
  roughly **3–5 mAh/day** at an hourly-or-slower reporting interval —
  [Zbotic's solar-powered ESP32 LoRa field monitor writeups](https://zbotic.in/solar-powered-iot-sensor-node-esp32-with-deep-sleep/)
  put deep-sleep current around 20µA on a bare module, and note the
  practically-important caveat that **standard dev boards draw far more
  than that in "deep sleep" than the datasheet implies**, because of
  onboard USB/power-LED circuitry and voltage regulators that aren't
  designed to be cut — the fix is a bare ESP32-WROOM module, or
  physically removing the board's power LED, not just calling
  `esp_deep_sleep_start()` and assuming the datasheet number applies.
  This is a tiny load: even the pessimistic, real-world case is well
  within what a small panel + battery handles comfortably.
- **A real LoRaWAN gateway** (an 8/16-channel concentrator listening
  continuously) draws roughly **10–15 Wh/day** for an outdoor unit with
  wired backhaul, more with cellular backhaul —
  [LinkSolar's gateway sizing guide](https://linksolar.net/blogs/guide/solar-panel-for-lora-gateway)
  puts this at **roughly 10× an ESP32 sensor node's draw**. This is a
  materially harder off-grid power problem, not a scaled-up version of
  the easy one.

The point-to-point pilot [[0075-sensor-hardware-and-connectivity-selection]]
already recommends — one sensor node, one receiver sited near the
house, no gateway at all — happens to already put the *only* hard
component (anything gateway-class) at a location this ADR reads as
out of scope. **The Phase 1 plan doesn't need a redesign**; it needs
this ADR's power spec added to the one component that was always going
to be at the hive.

## Decision

### 1. Sensor node: small solar panel + battery + charge controller — the easy case
- A small (1–2 W, 5–6 V) solar panel plus a matching charge controller
  and cell is the standard, well-proven combination for this power
  class — no custom power electronics needed. **Correction (2026-09-20,
  caught during [[0097-hardware-infrastructure-and-component-catalog]]'s
  market research, not designed carefully enough here originally): the
  charge controller and cell chemistry must match.** A TP4056-class
  controller charges to 4.2V, correct for a standard Li-ion/18650 cell
  but **enough to overcharge and damage a LiFePO4 cell**, which needs a
  3.6V charge voltage from a chemistry-specific IC (e.g. CN3058-based).
  Pick one consistent pair, not "TP4056 with a LiFePO4 cell" as this
  ADR originally, wrongly, implied — see
  [[0097-hardware-infrastructure-and-component-catalog]] for the
  corrected, real-product-level component catalog.
- **Sizing rule** (from the same sourcing above): panel daily harvest
  should be **at least 3× the node's daily consumption**, assuming
  ~3 hours of effective sunlight — comfortably achievable at this power
  budget even with a genuinely small panel.
- **Firmware/hardware practice, not just component choice**: use a bare
  ESP32-WROOM module rather than a full dev board where practical, or
  remove the dev board's power LED — the real-world gap between
  datasheet deep-sleep current and what an unmodified dev board actually
  draws is the single biggest lever on battery/panel sizing, bigger than
  panel wattage itself.
- **Cold-climate note, specific to this deployment**: the apiary this
  module already tracks real data for (Vand Værk) is in Denmark — short
  winter days, low sun angle, and genuinely sub-zero temperatures.
  Standard Li-ion cells (18650s included) have reduced charge-acceptance
  below ~0°C and can be damaged by charging at freezing temperatures;
  LiFePO4 is more cold-tolerant (at somewhat lower energy density and
  higher cost per cell) and is the safer default for a cell that will
  sit outdoors through a Danish winter, not a generic global
  recommendation copied from a warmer-climate guide.
- **Realistic autonomy, not the optimistic math**: naive
  battery-capacity ÷ deep-sleep-current arithmetic suggests months of
  runtime with zero solar input at all; real-world guidance is blunter —
  plan for **2–3 weeks of full-cloudy-season autonomy** before a
  correctly-sized panel/battery pair needs to recover, not the
  theoretical number. Size the battery against that, not the spreadsheet
  figure.

### 2. Receiver/gateway: avoid the hard problem where possible, solve it properly where not
- **Preferred**: site the receiver (Phase 1's point-to-point design) or
  any future LoRaWAN gateway somewhere already on mains power within
  radio range of the apiary — inside the house, a shed, or wherever
  else is convenient — per this ADR's scoping above, that is not
  "at/around the apiary" and carries no renewable-power obligation.
  This is the existing Phase 1 recommendation already, unchanged.
- **If a public/community LoRaWAN gateway (e.g. via The Things Network)
  already covers the site**, per
  [[0075-sensor-hardware-and-connectivity-selection]]'s own preference
  for TTN over self-hosting, there is no gateway to power at all — it's
  someone else's infrastructure, out of scope entirely.
- **Only if a dedicated gateway genuinely must be sited away from mains
  power** — no house, shed, or public gateway in range — does the hard
  problem apply: a 12–20 W MPPT solar controller/panel and a real
  battery bank (LinkSolar's guide: **~50 Ah LiFePO₄ for a
  carrier-grade setup**, less for a lighter pico-gateway), sized at
  **5× daily consumption, not 3×**, specifically because "sizing at 5×
  isn't paranoia — it's the difference between running through January
  and going dark mid-winter" at this kind of latitude. This is a real
  cost and complexity step up from the sensor node's power budget, not
  a bigger version of the same shopping list, and should only be taken
  on if the preferred options above are genuinely unavailable.

### 3. This is a hardware/siting decision, not a code change
Nothing in [[sensor-data-collection]]'s software (the ingestion
endpoint, `SensorDevice`/`SensorReading` entities, the configuration
descriptor) changes because of this ADR — HiveLog's own code has no way
to know or care how a device is powered. This is entirely a constraint
on [[0079-pilot-weight-sensor-hardware-build]]'s physical build and on
any future gateway-siting decision, recorded here so it isn't lost or
re-litigated per device.

## Consequences
- Positive: grounded in real numbers rather than "solar is easy" hand-
  waving — the sensor node genuinely is easy at this power class, with a
  well-proven, cheap component combination; the harder gateway-class
  case is identified honestly rather than glossed over, with a concrete
  reason (10× the power draw, a materially bigger solar/battery system)
  and a concrete way to avoid it (site at the house, or use existing TTN
  coverage) before accepting the added cost/complexity. The Phase 1
  pilot plan needs no redesign — it already sites the hard component
  where this ADR says power isn't a problem.
- Negative / trade-offs: the "at/around the apiaries" scoping is this
  ADR's own interpretation, not confirmed — if the intent was broader
  (the receiver must be solar-powered even at the house), the pilot's
  hardware plan and cost genuinely change, and
  [[0079-pilot-weight-sensor-hardware-build]]'s acceptance criteria
  would need updating to add a receiver-side power budget. Cold-climate
  battery guidance (LiFePO4 over standard Li-ion) is specific to this
  apiary's real location and may not generalise if the module is ever
  used somewhere warmer — noted as such, not stated as a universal rule.
  Real-world battery autonomy is meaningfully worse than the optimistic
  capacity-divided-by-current math suggests, which this ADR states
  plainly rather than letting a beekeeper discover it the hard way
  after a cloudy fortnight.
- Follow-up tasks: [[0079-pilot-weight-sensor-hardware-build]]'s
  acceptance criteria should be extended to include the sensor node's
  solar/battery/charge-controller build per §1, not just the
  sensor/radio electronics — tracked under
  [[sensor-data-collection]]. No change needed to any
  [[ai-apiary-insights]] task.
