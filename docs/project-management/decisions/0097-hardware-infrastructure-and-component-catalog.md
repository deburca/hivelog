---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0097: Hardware infrastructure — extensibility framework & off-the-shelf component catalog

## Status
accepted. Gates procurement and any physically-hardware-dependent task
([[0079-pilot-weight-sensor-hardware-build]],
[[0096-off-grid-gateway-power-design]]) — per the user's own framing,
"without this infrastructure design — and eventual procurement — and
development will be unnecessary." Does **not** gate the pure-software
tasks in [[sensor-data-collection]] ([[0076-sensor-device-and-reading-entities]]
through [[0078-sensor-device-configuration-descriptor]],
[[0080-hive-apiary-sensors-panel]] onward) — see Consequences for why
that distinction is real, not a loophole.

## Context
Every prior hardware ADR ([[0075-sensor-hardware-and-connectivity-selection]],
[[0095-renewable-power-for-apiary-equipment]]) reasoned at the level of
**component categories** ("an SX1276/RFM95-class LoRa module," "a
TP4056-class charge controller") without verifying real, currently
purchasable products against each other for actual compatibility. That
was a real gap: researching this ADR caught a genuine error in
[[0095-renewable-power-for-apiary-equipment]] (a TP4056 charger, which
charges to 4.2V, paired with a LiFePO4 cell, which needs 3.6V and would
be damaged by that — now corrected in that ADR). Category-level
reasoning is not enough to actually build something; this ADR does the
product-level check the others didn't.

### What "extensible... off-the-shelf" actually requires
Not just "pick good parts" — a **standard interface per component
category**, so a *future* off-the-shelf part in that category plugs in
without a bespoke integration each time, the same way
[[0074-sensor-data-ingestion-architecture]] §2 made HiveLog's own
ingestion contract protocol-agnostic on the software side. This ADR is
that same idea applied to the physical/electrical side:

| Category | Standard interface | Why this one |
|---|---|---|
| Environmental sensors (temp, humidity, pressure, light) | **I2C** | Multiple sensors share one 2-wire bus (SDA/SCL) at distinct addresses — a new sensor type is "add to the existing bus," not "wire a new dedicated interface." Near-universal for this sensor class. |
| Probe-style temperature (in-hive placement matters more than sensor variety) | **1-Wire** (DS18B20-class) | Also multi-drop on one bus; ubiquitous, cheap, waterproof-probe versions exist off the shelf — exactly the placement flexibility a beehive needs (deep in the brood nest vs. under the lid). |
| Weight | **HX711's fixed 2-wire (clock+data) digital interface** | The de facto standard ADC for strain-gauge load cells in the hobbyist/light-industrial market — doesn't multi-drop like I2C, but modules are cheap enough that "one HX711 per load cell, one GPIO pair per HX711" is itself a standard, repeatable pattern. |
| Battery/solar power | **JST-PH 2.0mm connector** | The de facto standard small-LiPo/Li-ion connector across the hobbyist electronics market (Adafruit, SparkFun, and most MCU boards with a battery input use it) — an off-the-shelf battery or solar-charger board plugs straight in, no custom wiring adapter. |
| MCU + radio | A board exposing genuine I2C pins, spare GPIO, and a JST-PH battery/solar input | Not a single specific board — a *class* of board that satisfies the other four rows. |

A component that doesn't fit one of these standard interfaces is a
signal to look for a different, more standard part first, not a reason
to design a bespoke interface for it.

### Real, current market survey (not category-level guessing)
Pulled from live search, September 2026 — prices are illustrative
snapshots, re-check before ordering:

- **MCU + radio**: two genuinely different, both-real options, not one
  "correct" answer:
  - The generic ESP32/Arduino + separate 868 MHz SX1276/RFM95-class
    LoRa module already identified as an example
    ([[0075-sensor-hardware-and-connectivity-selection]]) — nothing
    purchased yet, but this remains the cheapest path to start with,
    and the power budget in
    [[0095-renewable-power-for-apiary-equipment]] is achievable with
    it.
  - **RAK WisBlock** (RAK4631 "core" module, nRF52840 + SX1262 LoRa,
    ~$18–24, plugging into a "base" board, ~$35–40 for a starter
    bundle) — a genuinely modular ecosystem: snap-in sensor modules
    (temperature/humidity, GPS, accelerometer, light, pressure) from
    RAK's own catalog, third-party I2C/1-Wire sensors via its IO
    expansion module, and — the reason it's worth naming specifically —
    an independently-reported **~2 µA sleep current vs. ~15 µA for a
    typical Heltec-class board**, explicitly called out in current
    sourcing as "the choice when sleep current drives the design for
    solar nodes." That's a direct, material improvement to
    [[0095-renewable-power-for-apiary-equipment]]'s whole thesis (lower
    average draw → smaller panel/battery, or more winter margin at the
    same size).
  - Also real and worth naming: **Heltec WiFi LoRa 32 V4** (ESP32-S3 +
    SX1262, integrated WiFi/BLE/LoRa, OLED, and — notably — **a
    built-in solar charging connector on the board itself**, removing a
    separate charge-controller module from the BOM entirely for that
    specific board).
- **Weight**: HX711 breakout + load cell kits are commodity items,
  **$8–16**, sold across Amazon, Walmart, eBay, and AliExpress
  simultaneously — about as "readily available off-the-shelf" as a
  component gets. Capacity should be sized to the hive's real weight
  (a full Langstroth hive with supers commonly reaches 30–50+ kg), so a
  single cell rated meaningfully above that, or four smaller cells
  summed under each corner of the stand (the more accurate,
  more-wiring-complexity option [[0075-sensor-hardware-and-connectivity-selection]]
  already flagged as a later upgrade).
- **Environmental**: **BME280** (temperature + humidity + pressure,
  I2C/SPI, roughly **$5–15**) and **SHT31** (temperature + humidity
  only, I2C, higher accuracy — ±2% RH — roughly **$10–14**) are both
  widely stocked, standard-I2C, well-documented parts. **DS18B20**
  (1-Wire, waterproof-probe versions widely available, roughly **$1–3**)
  is the standard choice specifically when *where* the probe sits
  matters (brood nest vs. under the lid) more than which sensor brand
  it is.
- **Power**: **TP4056** (~$1–2, the cheapest way to charge a single
  standard Li-ion/18650 cell from a small 5–6V panel) is correct only
  when paired with standard Li-ion chemistry. For LiFePO4 — the
  cold-climate-preferred chemistry per
  [[0095-renewable-power-for-apiary-equipment]] — the equivalent is a
  **CN3058-based (or similarly LiFePO4-specific) charge controller**,
  charging to 3.6V rather than 4.2V. Small 5–6V "garden-light-style"
  solar panels are the standard, cheap match for either controller;
  MPPT-variant controllers exist for better harvest efficiency at
  modest extra cost, worth it given
  [[0095-renewable-power-for-apiary-equipment]]'s own winter-margin
  concerns.

## Decision
1. **Adopt the five-row interface table above as the standing
   extensibility contract** for every future sensor/hardware addition
   to [[sensor-data-collection]] — a new component type is evaluated
   against "does it fit I2C / 1-Wire / HX711's interface / JST-PH
   power" before being considered, the same way a new `metric` in
   [[0074-sensor-data-ingestion-architecture]] §4 is evaluated against
   the existing code-defined taxonomy before inventing a new field.
2. **Correct [[0095-renewable-power-for-apiary-equipment]]'s
   TP4056/LiFePO4 pairing error** (done, in that ADR directly, per its
   own note) — a chemistry-matched charge controller is now a hard
   requirement, not a detail to sort out during assembly.
3. **Pilot node** ([[0079-pilot-weight-sensor-hardware-build]]): order
   the identified-but-not-yet-purchased ESP32/Arduino + LoRa module +
   HX711 + load cell — cheapest option to buy, and the power budget
   works with it. Pair with a **chemistry-matched** charge controller
   per the correction above (TP4056 + standard Li-ion, *or* a
   CN3058-class controller + LiFePO4 — pick one, not a mismatched pair).
4. **Standardise on RAK WisBlock (or an equivalently modular,
   low-sleep-current platform) for scaling beyond the first node** —
   once a second sensor type or a second hive needs covering, the
   combination of genuine plug-in modularity and materially lower sleep
   current is worth the switch from the pilot's bespoke wiring, per the
   market data above. This is a forward recommendation, not a
   retroactive redesign of the already-planned pilot.
5. **Weight sensing stays HX711 + a commodity load cell**, sized to the
   real hive weight range; **environmental sensing (when it starts, per
   [[sensor-data-collection]]'s Phase 1 scope) uses BME280/SHT31 (I2C)
   or DS18B20 (1-Wire, probe placement)** — both satisfy the standard
   interfaces in §1, so no new hardware research is needed when that
   metric type actually gets built, only a purchase against this
   catalog.

## Consequences
- Positive: the TP4056/LiFePO4 error is exactly the kind of mistake
  category-level ("a charge controller," "a LiFePO4 cell") reasoning
  produces and product-level checking catches — worth doing before
  ordering anything, not after a damaged battery. The five-row interface
  table is a genuine, reusable extensibility mechanism, not just a
  restated wish — a beekeeper (or a future contributor) adding, say, an
  entrance-counter sensor next year checks it against I2C/1-Wire/GPIO
  first, rather than reverse-engineering a bespoke wiring scheme from
  scratch. Naming two real, current MCU/radio platforms (the cheap
  generic parts vs. RAK WisBlock) rather than one lets the pilot proceed
  cheaply now while flagging the better-fit platform for scaling,
  instead of silently picking one and hiding the trade-off.
- Negative / trade-offs: this is real research overhead before any
  hardware task can start — genuinely what the user asked for
  ("without this... development will be unnecessary"), not a
  self-imposed delay. Prices and specific product availability are a
  September 2026 snapshot; re-verify before actually ordering, since
  hobbyist-electronics listings change — **nothing described in this
  catalog has been purchased yet; every item here is still a shopping
  list, not an inventory.** Recommending RAK WisBlock for future scaling
  while keeping the pilot on the cheaper generic parts means the fleet
  won't be hardware-uniform from day one — an accepted, explicit
  trade-off (cheap pilot now vs. one consistent platform from
  the start), not an oversight.
- Follow-up tasks: [[0079-pilot-weight-sensor-hardware-build]]'s
  acceptance criteria should reference this ADR's corrected §3 power
  pairing directly (it currently points at
  [[0095-renewable-power-for-apiary-equipment]] alone, which no longer
  has the error but doesn't itself name the corrected component). No
  new task files are needed purely for this ADR — it's a research/
  catalog document informing the *existing* hardware task, not a new
  subsystem. Tracked under [[sensor-data-collection]].
