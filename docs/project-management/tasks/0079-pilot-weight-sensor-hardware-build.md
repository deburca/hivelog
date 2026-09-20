---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[sensor-data-collection]]"
area: hardware
created: 2026-09-20
branch: feature/0079-pilot-weight-sensor-hardware-build
release:
depends-on: ["[[0077-sensor-ingestion-endpoint-and-device-auth]]", "[[0078-sensor-device-configuration-descriptor]]", "[[0097-hardware-infrastructure-and-component-catalog]]"]
blocked-by: ["[[0078-sensor-device-configuration-descriptor]]"]
---
# Task: Pilot weight-sensor hardware build + end-to-end smoke test

## Context
Proves the whole chain end-to-end against real hardware, for one hive,
per [[0075-sensor-hardware-and-connectivity-selection]] Decision §1 and
the architecture diagram in [[sensor-data-collection]]. Unlike every
other task in this project, this one has no PHP/test-suite deliverable
in `hivelog` itself — its acceptance is a real device reporting real
data, matching the module's existing "verify end-to-end against a real
site" testing culture, extended here to real hardware for the first
time.

## Acceptance criteria
- [ ] **Procure every component first — nothing has been purchased yet.**
      Per [[0097-hardware-infrastructure-and-component-catalog]]'s
      catalog: for the sensor node, a half-bridge load cell + HX711
      amplifier, an ESP32 (preferred per
      [[0075-sensor-hardware-and-connectivity-selection]]) or an Arduino,
      an 868 MHz SX1276/RFM95-class LoRa module, a small (1–2W, 5–6V)
      solar panel, and a chemistry-matched charge controller + cell (see
      the next criterion). For the receiver node, a matching 868 MHz
      LoRa radio plus a WiFi-capable ESP32 (or a single Heltec WiFi
      LoRa 32-class board integrating both). Confirm each item's real,
      current listing and price before ordering — the catalog names
      component categories and examples, not a fixed, already-checked-out
      cart.
- [ ] Assemble the sensor node: the load cell + HX711 amplifier, on an
      ESP32 (preferred per
      [[0075-sensor-hardware-and-connectivity-selection]]) or an
      Arduino, plus the 868 MHz LoRa radio module — once procured.
- [ ] Power the sensor node per [[0095-renewable-power-for-apiary-equipment]]
      §1 and [[0097-hardware-infrastructure-and-component-catalog]]'s
      corrected component pairing: a small (1–2 W) solar panel plus a
      **chemistry-matched** charge controller and cell — either a
      TP4056-class controller with a standard Li-ion/18650 cell, or a
      CN3058-class LiFePO4-specific controller with a LiFePO4 cell
      (preferred given this apiary's real Danish winter conditions — see
      that ADR's cold-climate note). **Do not pair a TP4056 with a
      LiFePO4 cell** — it charges to 4.2V, which overcharges and damages
      that chemistry (the original, since-corrected error in
      [[0095-renewable-power-for-apiary-equipment]]). **Confirmed cell
      spec for the LiFePO4 path: 3.2V nominal, 1.5Ah (1500mAh), genuine
      18650 form factor (18.2×64.8mm)** — verified against real current
      listings 2026-09-21: physically fits standard 18650 holders,
      comfortably covers the 2–3 week cloudy-season autonomy target with
      real margin (this power budget only needs ~3–5mAh/day; 1.5Ah is
      close to the practical capacity ceiling for genuine LiFePO4 in this
      form factor anyway, since its lower energy density means there
      isn't a meaningfully bigger 18650 LiFePO4 option to consider), and
      its ~4.2A continuous discharge rating is far beyond this node's
      ~100–150mA transmit-burst peak. **Before ordering the specific
      listing found**: confirm its own datasheet states a **3.6V charge
      voltage**, not 4.2V — a "LiFePO4" listing quoting 4.2V is either
      mislabeled or not actually LiFePO4 chemistry, and pairing it with a
      CN3058-class charger (which outputs ~3.6V) would undercharge it
      rather than deliver the rated capacity. **Voltage compatibility,
      confirmed 2026-09-21**: LiFePO4's 2.5–3.6V discharge curve sits
      entirely inside the bare ESP32 chip's real 2.2–3.6V operating
      range, so it can power the module **directly with no separate
      voltage regulator**, wired to the board's **3V3 pin specifically**
      — not the 5V/VIN pin most dev boards expect for USB-style power,
      which routes through an onboard regulator needing more headroom
      than 3.2–3.6V reliably provides. This is actually simpler than
      standard Li-ion, whose 4.2V full-charge voltage *exceeds* the
      ESP32's 3.6V absolute maximum and would need a step-down regulator
      to avoid damaging the chip if fed to 3V3 directly. The HX711
      (2.6–5.5V range) and the LoRa module (typically 1.8–3.7V for
      SX1276-class) both also accept 3.2V comfortably, so the whole node
      can plausibly run off raw LiFePO4 voltage with no boost/buck
      converter anywhere — confirm the specific ESP32 board chosen
      exposes a clean 3V3 direct-feed path before assuming this, since
      not every dev board does. Use a bare ESP32-WROOM module or remove
      the dev board's power LED — the real-world gap between a board's
      advertised deep-sleep current and what an unmodified dev board
      actually draws matters more to battery life than panel wattage or
      cell capacity. Confirm actual measured sleep/active current on the
      real assembled node, not just the datasheet figure.
- [ ] Assemble the receiver node: a matching LoRa radio on an
      ESP32/Arduino with WiFi, sited near the house/router — per
      [[0095-renewable-power-for-apiary-equipment]]'s scoping, this is
      not "at/around the apiary" and stays on ordinary mains power; no
      solar/battery build needed for it.
- [ ] Sensor firmware: reads the HX711 on a timer (15–60 minute interval
      per [[0074-sensor-data-ingestion-architecture]] §5), transmits a
      raw point-to-point LoRa packet carrying the weight reading — no
      LoRaWAN join, no gateway, per
      [[0075-sensor-hardware-and-connectivity-selection]]'s pilot
      recommendation.
- [ ] Receiver firmware: receives the LoRa packet; reads its locally
      stored configuration descriptor (downloaded via
      [[0078-sensor-device-configuration-descriptor]] and copied on once
      during setup) for the endpoint URL and bearer token; `POST`s to
      [[0077-sensor-ingestion-endpoint-and-device-auth]]'s endpoint with
      `metric: weight_kg`.
- [ ] Register a real `SensorDevice` (`scope: hive`, `device_type:
      weight`, `transport: lorawan`) against a real test hive; download
      its config descriptor; copy it onto the receiver.
- [ ] End-to-end smoke test: with a known test weight on the load cell, a
      correct-within-expected-accuracy `SensorReading` appears in HiveLog
      within one reporting interval, and `SensorDevice.last_seen`
      updates.
- [ ] Multi-day power smoke test: leave the sensor node running on
      solar + battery alone (no bench power, no manual recharge) for at
      least a week, ideally spanning a mix of sunny and overcast days,
      and confirm it keeps reporting on schedule throughout — a
      real-world check against
      [[0095-renewable-power-for-apiary-equipment]]'s "2–3 weeks of
      cloudy-season autonomy, not the optimistic capacity÷current
      arithmetic" guidance, not just a component-datasheet assumption.
- [ ] Document the wiring and firmware source somewhere reproducible for
      a second device (a small firmware repo, or a `hardware/` doc
      folder — exact location decided during implementation; it does not
      need to live inside the `hivelog` Drupal module itself).

## Implementation notes
- No kernel/unit tests are added by this task — see Context. If the
  receiver ends up running any shared logic worth unit-testing outside
  the firmware itself, that's a sign it should move into
  [[0077-sensor-ingestion-endpoint-and-device-auth]]'s scope instead, not
  a reason to add PHP tests here.
- Four-corner load cells (vs. the single half-bridge planned for the
  pilot) are a later accuracy upgrade, not required to pass this task —
  per [[0075-sensor-hardware-and-connectivity-selection]].

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0075-sensor-hardware-and-connectivity-selection]],
  [[0095-renewable-power-for-apiary-equipment]],
  [[0097-hardware-infrastructure-and-component-catalog]],
  [[0074-sensor-data-ingestion-architecture]]
- Commits::
