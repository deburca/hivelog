---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[sensor-data-collection]]"
area: hardware
created: 2026-09-20
branch: feature/0096-off-grid-gateway-power-design
release:
depends-on: ["[[0095-renewable-power-for-apiary-equipment]]"]
blocked-by:
---
# Task: Off-grid solar power design for a dedicated LoRaWAN gateway (conditional)

## Context
[[0095-renewable-power-for-apiary-equipment]] §2 identifies this as the
one genuinely hard power-engineering case — a real gateway draws
roughly 10× an ESP32 sensor node — but is explicit that it should only
be taken on if the preferred options fail: siting a gateway somewhere
already on mains power, or relying on existing public/community
LoRaWAN coverage (e.g. The Things Network). This task is **not
triggered yet**: Phase 1 ([[0075-sensor-hardware-and-connectivity-selection]]'s
point-to-point pilot) uses no gateway at all, and that ADR's own
"scale-out trigger" (a second apiary or an out-of-WiFi-range site
needing LoRaWAN) hasn't happened. Recorded now, low priority, so the
harder case isn't discovered mid-deployment with no plan.

## Acceptance criteria
- [ ] **First, confirm this task is actually needed** before doing any
      of the rest: check whether the new site (a) is within range of
      mains power, or (b) already has usable public/community LoRaWAN
      gateway coverage. If either is true, the outcome of this task is
      "no solar gateway build required" — record that and close it, per
      [[0095-renewable-power-for-apiary-equipment]] §2's explicit
      preference to avoid this build rather than default into it.
- [ ] If genuinely needed: confirm the actual power draw of the specific
      gateway hardware chosen — [[0095-renewable-power-for-apiary-equipment]]
      cites a real range (roughly 3–6 Wh/day for an indoor pico-gateway
      up to 15–25 Wh/day for an outdoor 16-channel unit with cellular
      backhaul) that varies by channel count and backhaul type; don't
      assume the low end without checking the chosen unit's own specs.
- [ ] Size the solar panel + battery bank at **5× daily consumption**
      (not the sensor node's 3× rule) — per
      [[0095-renewable-power-for-apiary-equipment]] §2's winter-reliability
      reasoning for this latitude — an MPPT controller in the 12–20 W
      class, and a real battery bank (LiFePO4 preferred, per that ADR's
      cold-climate note — the same reasoning applies here, more so given
      the larger capacity involved).
- [ ] Multi-week off-grid smoke test spanning genuinely overcast weather,
      mirroring [[0079-pilot-weight-sensor-hardware-build]]'s own
      power-validation approach at the smaller scale — confirm the
      gateway stays up through a real cloudy stretch, not just the
      sizing arithmetic.
- [ ] Document the actual siting decision made, whichever it turns out
      to be — this task's record is valuable either way, including (and
      especially) if the answer is "we avoided this build entirely."

## Related
- Project:: [[sensor-data-collection]]
- Decisions:: [[0095-renewable-power-for-apiary-equipment]],
  [[0075-sensor-hardware-and-connectivity-selection]]
- Commits::
