---
type: task
tags: [hivelog/task]
status: in-progress
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
- [ ] **Procurement — order placed 2026-09-21.** Most items expected by
      end of this week; the LiFePO4 batteries (GLK-Technologies) are on
      a separate delivery, expected the following week — do not start
      the power-wiring or multi-day power smoke test criteria until
      those actually arrive. Went through two corrections along the way
      before ordering (a wrong charge-controller pairing dropped, then
      briefly reintroduced with the wrong chemistry, before landing on a
      genuinely dual-chemistry board) — this list is the reconciled
      basket as ordered, not the original catalog picks. Still
      outstanding, not part of this order: the weatherproofing items
      below and the tools/consumables criterion — order separately once
      confirmed.
      - ESP32-WROOM-32U DevKitC V4, USB-C, external antenna, **pack of
        2** — covers both nodes' MCUs in one purchase. This is a full
        dev board (onboard USB-to-serial chip, status LED), not a bare
        module — see the power criterion below for what that changes.
      - LiFePO4 3.2V 3200mAh battery, **pack of 2** (GLK-Technologies) —
        genuine 18×65mm 18650 dimensions, well above the ~1.5Ah that
        would already be sufficient (more margin, longer top-up time
        from the small panel). No explicit cold-weather temperature
        rating found on this listing — worth checking further before
        assuming it covers the coldest Danish nights, per
        [[0095-renewable-power-for-apiary-equipment]]'s cold-climate
        reasoning.
      - Solar panel, 5V 2W, USB-A output, waterproof — within the
        1–2W/5–6V range specified.
      - Geekstory 4× 50kg half-bridge load cells + 1× HX711 — exceeds
        the pilot minimum; gets the four-corner accuracy upgrade
        (previously scoped as a later addition, see Implementation
        notes) for free instead of buying it separately afterward.
      - Paradisetronic 868MHz SX1276 LoRa breakout, **pack of 2** —
        covers both the sensor node's radio and the receiver's matching
        radio in one purchase.
      - Youmile TP5000 charging module, **pack of 5** — a genuinely
        dual-chemistry switch-mode (buck, more efficient than a linear
        TP4056/CN3058 board) charger, selectable between Li-ion (4.2V)
        and LiFePO4 (3.6V) via a physical jumper. **Verify the jumper is
        actually set to the LiFePO4/3.6V position before connecting the
        battery** — because it's switchable rather than fixed, this is
        an active physical check each unit needs, not a one-time
        datasheet read. No USB connector on this board (bare solder
        pads) — see the next item.
      - USB-A female bare-wire pigtail cable, **pack of 6** (Greluma) —
        solders onto the TP5000 board's input pads to give it a USB-A
        socket the solar panel's cable can plug into. Confirm the panel
        itself has (or you already own) a standard USB-A male-to-male
        cable to complete that link.
      - **Weatherproofing — real options identified, not yet added to
        the basket**: an IP65 ABS project box, ~115×90×55mm (Focket/
        Akozon/Tyenaza-family listings, ~DKK 80–97) for the main
        electronics — sized to comfortably fit the DevKitC board, LoRa
        breakout, HX711, TP5000 module, and battery together; a second,
        smaller IP65 box (~89×59×35mm, same product family, ~DKK 55–70)
        for the HX711 + wire junction near the load cell, since that
        can't share the main enclosure; the load cell's own exposed
        strain gauge and connections protected with silicone sealant or
        heat-shrink (no dedicated off-the-shelf product for this
        specific part exists — confirmed by search, not an oversight);
        a small pack of PG7/PG9 waterproof cable glands if the chosen
        enclosure doesn't ship with them pre-installed; a multi-pack of
        silica gel desiccant sachets for inside the main enclosure.
        **Heat-shrink type matters more than getting one exact
        diameter**: plain heat-shrink only insulates mechanically, it
        does not seal against water — buy **adhesive-lined ("dual-wall"),
        3:1-ratio heat-shrink** specifically (the type whose inner
        lining melts and flows to form a genuine watertight seal, used
        for marine/automotive/outdoor wiring), as an **assortment kit
        spanning roughly 3–10mm unshrunk** rather than one size — small
        end of that range for individual soldered joints on the load
        cell's thin signal wires, larger end for bundling the 4-wire
        cable where it exits the load cell body. The exact wire gauge
        wasn't confirmed from the Geekstory listing, which is the other
        reason an assortment beats guessing a single size.
      **Do not add a boost or buck converter to the cart** — per the
      power criterion below, LiFePO4's raw voltage feeds the
      ESP32/HX711/LoRa module directly; a boost-regulator variant of the
      charge-controller board would be redundant for this wiring, not an
      upgrade.
- [ ] **Tools & consumables — reusable across builds, not part of the
      device itself, and not yet confirmed on hand.**
      - An assorted **Dupont jumper wire kit** (male-male, male-female,
        female-female) + a **breadboard**, for bench-prototyping the
        LoRa/HX711/ESP32 wiring and validating firmware before
        committing to anything permanent. **The field-deployed node
        should not stay on jumper wires** — friction-fit connections can
        work loose from vibration/thermal cycling sealed in an outdoor
        enclosure over weeks; transfer to soldered joints (or locking
        connectors, e.g. JST, if the battery should stay serviceable
        without resoldering) once firmware is validated.
      - **Soldering iron, rosin-core solder, flux, and a "helping
        hands"/PCB holder** — needed for the USB-A pigtail's bare wires
        into the TP5000's input pads, the load cell's wires (unless the
        specific HX711 board has screw terminals — check once it
        arrives), and the final soldered build generally.
      - **A heat gun — not a lighter.** The adhesive-lined heat-shrink
        (see the weatherproofing item above) needs sustained, even heat
        for its inner liner to properly melt and flow into a real seal;
        a lighter's uneven flame tends to scorch it locally without
        sealing, defeating the point of buying the adhesive-lined type.
      - **A multimeter** — required to actually satisfy this task's own
        criteria below (confirm the TP5000's jumper output voltage
        before connecting the battery; measure real sleep/active
        current on the assembled node), not optional.
      - **Wire strippers**, for the pigtail and any other bare-wire
        ends.
- [ ] Assemble the sensor node: the load cell + HX711 amplifier, on an
      ESP32 (preferred per
      [[0075-sensor-hardware-and-connectivity-selection]]) or an
      Arduino, plus the 868 MHz LoRa radio module — once procured.
- [ ] Power the sensor node per [[0095-renewable-power-for-apiary-equipment]]
      §1 and [[0097-hardware-infrastructure-and-component-catalog]]'s
      corrected component pairing. **Chain, as actually procured**: solar
      panel → USB-A male-to-male cable → Greluma USB-A female pigtail
      (bare wires) → soldered to the Youmile TP5000 board's input pads →
      TP5000 (**jumper set to LiFePO4/3.6V — verify physically, not
      assumed**) → GLK-Technologies LiFePO4 3.2V/3200mAh cell → ESP32's
      **3V3 pin directly** (not 5V/VIN).
      - **Do not pair a TP4056-style fixed-4.2V charger with a LiFePO4
        cell** — this was the original error in
        [[0095-renewable-power-for-apiary-equipment]] (corrected), then
        briefly reintroduced during shopping when a TP4056 board was
        picked up before being caught and swapped for the TP5000. The
        TP5000 avoids this by being genuinely switchable — which is also
        exactly why its jumper position needs an active check per unit,
        not a one-time spec read.
      - **Voltage compatibility, confirmed 2026-09-21**: LiFePO4's
        2.5–3.6V discharge curve sits entirely inside the bare ESP32
        chip's real 2.2–3.6V operating range, so it can power the module
        **directly with no separate voltage regulator**, wired to the
        3V3 pin specifically — not the 5V/VIN pin most dev boards expect
        for USB-style power, which routes through an onboard regulator
        needing more headroom than 3.2–3.6V reliably provides. This is
        actually simpler than standard Li-ion, whose 4.2V full-charge
        voltage *exceeds* the ESP32's 3.6V absolute maximum and would
        need a step-down regulator to avoid damaging the chip if fed to
        3V3 directly. The HX711 (2.6–5.5V range) and the LoRa module
        (typically 1.8–3.7V for SX1276-class) both also accept 3.2V
        comfortably, so the whole node runs off raw LiFePO4 voltage with
        no boost/buck converter anywhere.
      - **The board actually procured is an ESP32-WROOM-32U DevKitC V4 —
        a full dev board, not the bare module originally preferred.**
        Its standard Espressif reference-design header does expose a
        3V3 pin usable for direct battery injection, so the wiring above
        still works — but the onboard USB-to-serial chip and status LED
        draw quiescent current a bare module wouldn't, so
        **removing/desoldering the power LED is a required assembly
        step for the sensor node's copy of this board, not an optional
        best practice** (the receiver's copy is mains-powered and
        doesn't need this). Confirm actual measured sleep/active current
        on the real assembled node afterward, not just the datasheet
        figure.
      - The TP5000's switch-mode (buck) design is more efficient than a
        linear TP4056/CN3058 charger — less of the panel's already-small
        harvest wasted as heat.
- [ ] Weatherproof the sensor node — this is a build step, not just a
      purchase. Main electronics (ESP32, LoRa breakout, TP5000, battery)
      go in the ~115×90×55mm IP65 enclosure; cable entries sealed with
      proper waterproof glands, not just a drilled hole; a desiccant
      sachet inside against condensation; light-coloured box to limit
      solar heat gain; stainless fasteners. The load cell's HX711 +
      wire junction get their own smaller IP65 box near the load cell
      itself, separate from the main enclosure. The load cell's exposed
      strain gauge and connections get a coat of silicone sealant or
      **adhesive-lined (dual-wall, 3:1) heat-shrink** over the joints —
      plain heat-shrink only insulates, it doesn't seal water out, so
      this is the one heat-shrink type that actually belongs here. No
      dedicated commercial product for this part exists, per
      real-market research, so this is the accepted DIY approach for
      the pilot rather than a gap to close before proceeding. Route
      cables so water runs off and drips clear of entry points, not
      pooling against a gland or seam.
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
- Four-corner load cells were scoped as a later accuracy upgrade, not
  required for the pilot, per
  [[0075-sensor-hardware-and-connectivity-selection]] — the basket
  selected 2026-09-21 (4× 50kg half-bridge cells) gets this from day
  one anyway, so wire and calibrate all four from the start rather than
  building single-cell first and upgrading later.

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0075-sensor-hardware-and-connectivity-selection]],
  [[0095-renewable-power-for-apiary-equipment]],
  [[0097-hardware-infrastructure-and-component-catalog]],
  [[0074-sensor-data-ingestion-architecture]]
- Commits::
