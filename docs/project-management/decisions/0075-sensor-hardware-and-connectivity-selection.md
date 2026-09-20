---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-09-20
supersedes:
---
# ADR-0075: Sensor hardware & connectivity selection

## Status
accepted, as a recommendation guiding which bridge gets built/documented
first against [[0074-sensor-data-ingestion-architecture]] — not a
constraint enforced by any code. Nothing here blocks a different hardware
choice later; the ingestion contract is transport-agnostic by design.

## Context
[[0074-sensor-data-ingestion-architecture]] decided *what HiveLog accepts*
(one HTTP contract) and deliberately said nothing about *what hardware
produces it*. Someone still has to pick a first sensor node design and a
first transport path to actually pilot the feature — this ADR is that
choice, kept separate from ADR-0074 on purpose: hardware/protocol
recommendations date faster than a data-model decision does, and they
should be revisable (a future ADR can supersede this one) without
reopening the entity/API design.

### Connectivity comparison
Per [Hivekraft](https://hivekraft.com/en/learn/fortgeschritten/09-datenbasiert),
corroborated by the wider search:

| Protocol | Range | Power | Needs | Best for |
|---|---|---|---|---|
| **LoRaWAN** | 1–10 km | Lowest | A gateway (or a network server account) | Apiaries with no WiFi coverage, several sites, battery life measured in months/years |
| **WiFi** | 50–100 m | Higher | An existing WiFi network in range | An apiary at or near a house/shed with a router already present |
| **GSM/LTE (cellular)** | Anywhere with coverage | Highest | A SIM + data plan per device | Remote/isolated apiaries with no WiFi and no gateway, where cost-per-device is less of a concern |

For a beekeeper starting small, **WiFi is the cheapest and simplest if an
apiary already sits near a router**; LoRaWAN is the right choice once an
apiary is out of WiFi range or once more than one apiary needs covering
from a single receiver, which matches the "start small and extend"
framing directly — begin with whichever protocol the first apiary's
physical situation calls for, without having to commit to one protocol
module-wide, since [[0074-sensor-data-ingestion-architecture]]'s ingestion
contract doesn't care which was used.

### LoRa hardware already sourced: two distinct paths, not one
The 868 MHz LoRa module the team has already identified (an
SX1276/RFM95-class radio + antenna, paired with an Arduino) is a **raw
LoRa radio**, not a complete LoRaWAN end node by itself. It supports two
materially different builds:

1. **Point-to-point raw LoRa** — the sensor node and a single "receiver"
   node (an identical radio on an ESP32/Arduino sitting near the house/
   router) talk directly to each other with no gateway, no network
   server, and no TTN/ChirpStack account at all. The receiver bridges
   onto WiFi and does the actual `POST` to HiveLog's ingestion endpoint
   ([[0074-sensor-data-ingestion-architecture]] §2) — it is the receiver,
   not the battery/radio-only sensor node, that reads the generated
   configuration descriptor (§3 of that ADR) and holds the device's
   token. This is the **cheapest and fastest way to pilot a single
   apiary** — one extra ~€10
   receiver board, firmware on both ends, done. It does not scale to
   multiple independent apiaries without a receiver at each, and it isn't
   interoperable with any standard LoRaWAN tooling.
2. **True LoRaWAN** — the same radio, but running a LoRaWAN protocol stack
   (e.g. via the RadioLib or MCCI LMIC library) to OTAA-join a real
   LoRaWAN network. This needs an actual gateway in range (either a
   dedicated one, ~€80–250, or joining [The Things Network](https://www.thethingsnetwork.org)'s
   community coverage if a public gateway already exists nearby) plus a
   network server. Two viable network servers:
   - **The Things Stack (TTN), community edition** — free, cloud-hosted,
     no infrastructure to run; supports pushing every uplink straight to
     an HTTP webhook or MQTT. Fair-use policy limits apply (airtime/
     message-count caps), which a handful of hive sensors at a 15–60
     minute interval sit comfortably under.
   - **Self-hosted ChirpStack** — full control, no fair-use ceiling, but
     real ops work (a server to run and keep patched). Only worth it once
     device count or a need for guaranteed, unshared capacity justifies
     the maintenance burden.

**Recommendation: start with option 1 (point-to-point) for the very first
pilot hive**, since it proves the sensor → weight-reading → HiveLog chain
end-to-end fastest with hardware already in hand and zero third-party
accounts. **Move to option 2 with TTN** (not self-hosted ChirpStack) as
soon as a second apiary or an out-of-WiFi-range site needs covering — TTN
is free, needs no server of its own to operate, and its HTTP
webhook/MQTT integration bridges straight into
[[0074-sensor-data-ingestion-architecture]]'s ingestion contract with no
HiveLog-side code, only bridge configuration. Self-hosted ChirpStack is a
later option, not a starting one, and only if TTN's fair-use ceiling or
public-coverage gaps actually become a problem in practice.

### Weight sensing: the load cell already sourced
The half-bridge load cell + HX711-style 24-bit ADC amplifier already
identified is the standard, widely-used DIY beehive-scale approach —
the same shape used by the "Bienenwaage" project (2011) and every
BroodMinder-DIY-style build found during research. Practical guidance:
- A **single half-bridge pair** (as sourced) works if the hive stand is
  level and rigid; **four full load cells, one under each corner**, is
  the more accurate, more tolerant-of-an-uneven-stand configuration used
  by most mature DIY projects, at roughly proportionally higher cost and
  wiring complexity. Start with the half-bridge for the pilot — it's
  already in hand and sufficient to validate the whole pipeline — and
  treat four-corner load cells as an accuracy upgrade to revisit once the
  ingestion/dashboard side is proven, not a blocker to starting.
- Per [[0074-sensor-data-ingestion-architecture]] §5, sample and transmit
  every 15–60 minutes, not continuously — this is both what every source
  reviewed recommends and what keeps LoRa airtime/battery and HiveLog's
  storage volume sane without any code needing to enforce it.

### Microcontroller: ESP32 over a bare Arduino Uno, where the choice is open
The team's linked parts pair with "Arduino" generically. Where the board
isn't already fixed, an ESP32-class board (also ~€5–10, comparable price
to an Uno/Nano) is the better general default for this project: built-in
WiFi *and* Bluetooth (useful for the point-to-point-then-WiFi-bridge
build above, and for any future Bluetooth-sensor aggregation in the style
of BEEP), more RAM/flash for a real LoRaWAN stack if/when needed, and it
is what most of the open-source hive-monitoring prior art (Hiveeyes-adjacent
builds, BroodMinder-DIY-style projects) already standardises on. This
does not conflict with the LoRa module already sourced — the same
SX1276/RFM95-class radio pairs with an ESP32 exactly as it does with a
classic Arduino.

### What NOT to build first
Per [[0074-sensor-data-ingestion-architecture]] §6, acoustic and chemical
(VOC/VSC) sensing are explicitly deferred — both need real edge signal
processing (acoustic) or are still an emerging, immature technology
(VOC/VSC) with no defined HiveLog-side data model yet. Starting there
first would mean building the hardest sensor type before the ingestion
pipeline itself is even proven with the simplest one (weight).

## Decision (recommended)
1. **Pilot node**: the already-sourced load cell + HX711 amplifier for
   weight, on an ESP32 (preferred) or the already-sourced Arduino, talking
   **point-to-point raw LoRa** to a single receiver bridging onto WiFi and
   HiveLog's ingestion endpoint. One hive, one metric, proving the full
   chain.
2. **Second sensor type**: internal temperature/humidity (a cheap,
   well-understood addition, e.g. a DHT22/SHT31-class sensor on the same
   node), still point-to-point.
3. **Scale-out trigger**: once a second apiary or an out-of-WiFi-range
   site needs covering, move to **true LoRaWAN via TTN's community
   network** rather than adding more point-to-point receivers — TTN is
   free and needs no infrastructure, and both the existing and any new
   nodes reuse the same [[0074-sensor-data-ingestion-architecture]]
   ingestion contract without change.
4. **Explicitly not now**: acoustic sensors, chemical/VOC sensors, a
   self-hosted LoRaWAN network server (ChirpStack), and any single
   commercial vendor's proprietary platform as the primary path.

## Consequences
- Positive: reuses hardware already in hand; proves the riskiest new
  architecture (the ingestion API and its auth) against the simplest,
  highest-value sensor (weight) before investing in harder sensor types;
  a clear, cheap trigger (a second apiary / out-of-range site) for when to
  adopt real LoRaWAN infrastructure instead of guessing upfront.
- Negative / trade-offs: point-to-point raw LoRa doesn't scale past one
  receiver's radio range and isn't standards-based — it is a deliberate,
  temporary simplification for the pilot, not the end state. A half-bridge
  load cell is less accurate than a four-corner setup on an uneven stand.
  TTN's fair-use policy is a real ceiling, not unlimited capacity, though
  well above what a hobby-scale deployment needs.
- Follow-up tasks: tracked under the new
  [[sensor-data-collection]] project, alongside
  [[0074-sensor-data-ingestion-architecture]] for the ingestion side of
  the same initiative.
