# Weight sensor node — build, wiring and flashing guide

Build guide for the pilot hive-weight `SensorDevice` from
[task 0079](../docs/project-management/tasks/0079-pilot-weight-sensor-hardware-build.md).
Design rationale lives in
[ADR-0074](../docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md)
(ingestion contract) and
[ADR-0075](../docs/project-management/decisions/0075-sensor-hardware-and-connectivity-selection.md)
(hardware choice). Firmware is in [`oneof9/`](oneof9/) (sensor node) and
[`receiver/`](receiver/); the overview and calibration notes are in
[`README.md`](README.md). This file is the hands-on build guide.

Pin numbers below mirror the `#define`s at the top of
`oneof9/src/main.cpp`. If the two ever disagree, the firmware is the truth —
fix this document.

## 1. Parts

Status: **have** = confirmed in hand, **need** = still to obtain, **check** =
verify before relying on it.

### Core components

| Part                                                     | Role                                | Status |
| -------------------------------------------------------- | ----------------------------------- | ------ |
| ESP32-WROOM-32U DevKitC V4 ×2                            | One sensor node, one receiver       | have   |
| 4 × 50 kg half-bridge load cells + HX711 (Geekstory kit) | Weighing, 200 kg total capacity     | have (HX711 has headers) |
| Paradisetronic 868 MHz SX1276 LoRa breakout ×2           | Point-to-point radio, one per board | have; **check** header pitch (some are 2.0 mm, not breadboard-friendly) |
| 868 MHz antennas ×2                                      | One per LoRa breakout, incl. receiver | have |
| LiFePO4 3.2 V 3200 mAh 18650 cell                        | Node battery                        | have; **check** whether it is protected |
| 18650 holders with leads ×2                              | Cell mounting and wiring            | have |
| TP5000 charger board (Youmile)                           | Solar → LiFePO4 charging            | have |
| 5 V 2 W solar panel + USB-A female pigtail               | Charging source                     | have |
| IP65 boxes ×2 (main, load-cell junction)                 | Weatherproofing                     | have |

### Still needed

| Part | Role |
| ---- | ---- |
| **1S LiFePO4 protection board** (skip only if the cell proves to be protected) | Over-discharge / over-charge / short protection; wire cell to B+/B−, and the ESP32 3V3 rail and TP5000 output to P+/P− |
| 2 × 100 kΩ resistors (1 % preferred; any equal pair 100–470 kΩ) | Battery-voltage divider to GPIO34 |
| 100 nF capacitor (optional) | Steadies the divider reading, GPIO34 to GND |
| Hook-up wire, incl. 4-conductor cable for the junction-box → main-box run | Final wiring |
| PG7/PG9 cable glands (if the boxes lack them) | Cable entry |
| Load-cell mounting hardware: base plate, top plate, spacers/washers, bolts ×4 corners | Lets each cell flex freely (§4) |
| USB power adapter | Powers the receiver |
| USB-A male-to-male cable | Solar panel → TP5000 pigtail |
| Known test weights, ~5–10 kg (e.g. water containers weighed on a bathroom scale) | Calibration (§6) |
| Optional: JST plug or inline switch on the battery leads | Disconnect the battery while flashing |

### Tools and consumables

USB-C **data** cables (have); Dupont jumper kit and breadboard; multimeter;
soldering iron, rosin-core solder, flux and helping hands; wire strippers;
heat gun; adhesive-lined 3:1 heat-shrink assortment (about 3–10 mm); silicone
sealant; silica-gel desiccant sachets.

## 2. System overview

```
 SOLAR 5V 2W ─► USB-A pigtail ─► TP5000 IN
                                   │  (charge target set for LiFePO4, 3.6 V)
                                   ▼ OUT
                          LiFePO4 3.2 V cell ──(+)──┬──► ESP32 3V3 pin  (NOT 5V/VIN)
                                             (–)──┼──► ESP32 GND
                                                   │
             battery sense:  cell(+) ─[100k]─┬─[100k]─ GND
                                             └─► GPIO34

 4 × load cells ─► junction (§4) ─► HX711 ─► ESP32 ─► SX1276 ~~LoRa 868 MHz~~► receiver ─► WiFi ─► HiveLog
```

```
 HX711                    ESP32 DevKitC V4          SX1276 breakout
 ─────                    ────────────────          ───────────────
 VCC ── 3V3 rail ──────── 3V3 ────────────────────  VCC
 GND ───────────────────  GND ────────────────────  GND
 DT  ───────────────────  GPIO16
 SCK ───────────────────  GPIO17
                          GPIO5  ────────────────── SCK
                          GPIO19 ────────────────── MISO
                          GPIO27 ────────────────── MOSI
                          GPIO18 ────────────────── NSS / CS
                          GPIO14 ────────────────── RST
                          GPIO26 ────────────────── DIO0
                                                    ANT ── 868 MHz antenna
```

The **receiver** board uses the identical six LoRa connections (5 / 19 / 27 /
18 / 14 / 26) and nothing else: no HX711, no battery, USB-powered, on WiFi.
Both ends of a raw LoRa link must agree on radio wiring and frequency.

## 3. Voltage rules (why the node runs off the 3V3 pin)

- A LiFePO4 cell is 2.5–3.65 V. The ESP32, HX711 (2.6–5.5 V) and SX1276
  (≈1.8–3.7 V) all accept that range, so the whole node runs from raw cell
  voltage with no regulator.
- Feed the battery to the **3V3 pin**, never 5V/VIN. VIN goes through the
  board's regulator and expects ≥ 4.5 V.
- **Never connect USB and the battery at the same time.** USB drives the same
  3V3 rail through the onboard regulator. Disconnect the battery while
  flashing.
- Do not use a fixed-4.2 V (TP4056-style) charger on a LiFePO4 cell.
- Nothing else stops the cell over-discharging, since the ESP32 is fed straight
  from it. Use a protected cell, or fit a 1S LiFePO4 protection board on the
  cell with the load and the TP5000 output both on its P+/P− side.
- Power the HX711 from 3V3 (not 5V) so its DT line stays at ESP32-safe logic
  levels.

## 4. Load cells → HX711 (the fiddly part)

Each half-bridge cell has three wires. In this kit type the **red wire is the
centre tap** and the **white/black wires are the outer ends** — but colour
conventions vary between kits (some tutorials assume red = E+), so **identify
the wires with a multimeter before joining anything**:

1. Measure between each pair of wires on one cell (power off).
2. The pair with the **highest resistance (≈ 2 kΩ)** is the two outer wires.
   The third wire is the centre tap; it reads ≈ 1 kΩ to either outer wire.
   The exact numbers differ slightly by cell; the pattern (one pair roughly
   double the other two) is what matters.
3. Label the centre wire on every cell and treat it as "red" below.

Number the cells clockwise around the platform: 1 front-left, 2 front-right,
3 back-right, 4 back-left. Mount all four the same way up, with the sensing
end free to flex (spacers under the rigid ends).

**Junction wiring** (per the
[Circuit Journal 4× 50 kg diagram](https://circuitjournal.com/50kg-load-cells-with-HX711)):

- Form a loop by joining the outer wires of neighbouring cells,
  **same colour to same colour**: 1-white↔2-white, 2-black↔3-black,
  3-white↔4-white, 4-black↔1-black.
- The centre (red) wires of the **diagonal pair 1 & 3** go to **HX711 E+ and E−**.
- The centre wires of the **diagonal pair 2 & 4** go to **HX711 A+ and A−**.
- Polarity within each pair only flips the sign of the reading; correct it by
  swapping A+/A− if the value falls when weight is added.
- **Check before powering**: resistance across each red diagonal pair should
  read ≈ 2 kΩ. A very different value means a bad join or a swapped colour.

Solder the joins and keep the junction and HX711 together in the small IP65
box near the load cells. The firmware reads a single HX711 channel; there is
no per-cell code.

## 5. Bench build order

1. Wire everything on Dupont jumpers first, **powered from USB only** (no
   battery, no solar).
2. **Attach the LoRa antenna before applying power** — transmitting an
   SX1276 without an antenna can damage it.
3. Wire the load-cell junction (§4) and HX711 (VCC→3V3, GND, DT→16, SCK→17).
4. Wire the LoRa breakout (§2).
5. Wire the battery divider (2 × 100 kΩ in series across the cell, midpoint to
   GPIO34).
6. Flash and calibrate (§6). Only then build the power chain and go final.
7. Power chain, last:
   1. Solder the USB-A pigtail's bare wires to the TP5000 input pads.
   2. **Set the TP5000 for 3.6 V (LiFePO4) and verify with a multimeter on the
      output with no battery attached** — it must read about 3.6 V, not 4.2 V.
      Do not trust the jumper naming: boards differ on whether "bridged" or
      "open" means LiFePO4 (see the TP5000 links in §9).
   3. **The TP5000 has no separate load output.** Its battery pads (BAT+/BAT−)
      are the only output, so the battery, the ESP32 and the divider all meet
      there. Make one junction for (+) and one for (−) by soldering two wires
      to the same pad, a small solder splice, or a terminal block:

      ```
      TP5000 BAT+ ──┬── battery (+)
                    ├── ESP32 3V3   (HX711 VCC and LoRa VCC come from this rail)
                    └── [100k] ─┬─ [100k] ─ GND
                                └── GPIO34

      TP5000 BAT− ──┬── battery (–)
                    └── ESP32 GND   (HX711, LoRa, divider and junction grounds)
      ```

      - With a protection board (§1), the TP5000 BAT pads and the ESP32 both
        connect to its **P+/P−**; only the cell goes on B+/B−.
      - The TP5000 charges the cell while the node runs from it; the node's
        average draw is small next to the charge current, so charging still
        completes.
      - **Connect the battery before the solar panel**, and never run the node
        from the panel alone: the TP5000 output is not a regulated supply
        without a battery on it.
      - Use the ESP32 **3V3** pin, never 5V/VIN (§3).
8. Replace jumpers with soldered or screw-terminal connections; friction-fit
   Dupont wires do not survive outdoors.
9. Sensor node only: **desolder the DevKitC power LED** before deployment (it
   drains the battery). Leave the receiver's LED alone.

## 6. Flashing and calibration

Install [PlatformIO](https://platformio.org) (VS Code extension or
`pip install platformio`). Libraries are pinned in each `platformio.ini`
(`bogde/HX711`, `sandeepmistry/LoRa`, plus `ArduinoJson` on the receiver).

**Sensor node** (battery disconnected, USB-C plugged in):

```bash
cd hardware/oneof9
pio run --target upload
pio device monitor        # 115200 baud
```

**Calibrate** (required — both constants ship as placeholders):

1. With `CALIBRATION_FACTOR = 1.0` and `TARE_OFFSET = 0.0`, note the raw
   reading with nothing on the platform → `TARE_OFFSET`.
2. Place a known weight (e.g. 5 kg) and note the reading.
3. `CALIBRATION_FACTOR = (raw_with_weight − TARE_OFFSET) / known_weight_kg`.
4. Repeat with a second weight and average if possible.
5. Set both in `oneof9/src/main.cpp` and reflash.

Recalibrate whenever cells are swapped or the junction is rewired. Also press
each corner in turn: each should move the reading the same way by a similar
amount. A corner that reads oppositely or not at all indicates a wiring error
in the loop (§4).

**Receiver** (second board, USB):

```bash
cd hardware/receiver
cp src/secrets.h.example src/secrets.h      # WiFi SSID/password; gitignored
```

Provision (see also README §Provisioning):

1. In HiveLog register a `SensorDevice` (scope `hive`, `device_type: weight`,
   `transport: lorawan`).
2. On its page choose **Download Configuration**. This regenerates the token —
   do it right before provisioning.
3. Save the file as `receiver/data/config.json`, then:

```bash
pio run --target upload
pio run --target uploadfs      # writes data/config.json to LittleFS
pio device monitor
```

The receiver should log `Loaded config for endpoint: …`.

## 7. Verify and deploy

1. Both boards on USB: the sensor node should print a weight + battery reading
   and a LoRa send; the receiver should log the packet, its RSSI and a
   successful POST; `last_seen` on the device page should update.
2. Disconnect USB from the sensor node, connect battery → 3V3 and the solar
   panel, and seal the boxes. The node wakes every `SLEEP_INTERVAL_SECONDS`
   (1800 s).
3. Watch `battery_voltage` in HiveLog over several days to confirm the panel
   sustains the node.

## 8. Troubleshooting

| Symptom | Likely cause |
|---|---|
| HX711 read hangs / constant 0 or −1 | DT/SCK swapped or wrong pin; HX711 not powered |
| Reading noisy or drifting | Loose junction joints; cells not free to flex; long unshielded wire |
| Weight decreases when loaded | Swap A+/A− (or E+/E−) |
| One corner responds oddly | Outer wires joined with mismatched colours (§4) |
| `LoRa init failed` | Wrong NSS/RST/DIO0 pin, no 3V3 at the module, or MISO/MOSI swapped |
| Receiver never hears node | Frequency mismatch (868 MHz both), antenna missing, `HLOG1` tag mismatch |
| Node resets / dies at night | Battery undercharged; TP5000 set to wrong voltage or panel too weak |
| Serial garbage when battery attached | USB and battery both on the rail — disconnect one |

## 9. External references

Wiring conventions and pin roles below come from third-party sources; where a
source's pins differ from ours (e.g. HX711 tutorials using GPIO 18/19), follow
this repo's pin map.

**Load cells and HX711**
- [Circuit Journal — 50 kg load cells with HX711 (4×, 2×, 1× diagrams)](https://circuitjournal.com/50kg-load-cells-with-HX711) — the loop wiring used in §4
- [SparkFun — HX711 breakout hookup guide](https://learn.sparkfun.com/tutorials/load-cell-amplifier-hx711-breakout-hookup-guide/all) — HX711 terminals, and the four-cell combinator concept
- [Random Nerd Tutorials — ESP32 with load cell and HX711](https://randomnerdtutorials.com/esp32-load-cell-hx711/) — ESP32 side, 3.3 V supply, calibration
- [bogde/HX711 library](https://github.com/bogde/HX711) — the library used in `oneof9/`

**LoRa (SX1276 + ESP32)**
- [Random Nerd Tutorials — ESP32 with LoRa (RFM95/SX1276)](https://randomnerdtutorials.com/esp32-lora-rfm95-transceiver-arduino-ide/) — SCK 5 / MISO 19 / MOSI 27 / NSS 18 / RST 14 / DIO0 26 wiring
- [MakerGuides — Long range communication with SX1276 and ESP32](https://www.makerguides.com/long-range-communication-with-lora-sx1276-and-esp32/) — module pinout, 3.3 V only
- [sandeepmistry/arduino-LoRa library](https://github.com/sandeepmistry/arduino-LoRa) — the library used in both firmwares

**Charging (TP5000, LiFePO4)**
- [Lygte-info — TP5000 4.2/3.6 V module review](https://lygte-info.dk/review/Review%20Charger%20TP5000%204.2-3.6V%20module%20UK.html) — measured behaviour, mode selection
- [Codrey — LiFePO4 battery charger](https://www.codrey.com/electronic-circuits/lifepo4-battery-charger/) — LiFePO4 charging background
- Your seller's own TP5000 listing/manual — jumper polarity differs by board, so measure the output (§5)

**Tooling**
- [PlatformIO](https://platformio.org) — build, upload, `uploadfs`
