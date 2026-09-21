/**
 * HiveLog pilot weight-sensor node — task 0079.
 *
 * Wakes on a timer, reads the load-cell weight (via HX711) and battery
 * voltage, sends both as a single raw point-to-point LoRa packet to the
 * receiver node, then goes back to deep sleep. No WiFi, no LoRaWAN join,
 * no HTTP — this board never talks to HiveLog directly; see
 * docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §2 and docs/project-management/decisions/0075-sensor-hardware-and-connectivity-selection.md
 * for why that's the receiver's job.
 *
 * Board: ESP32-WROOM-32U DevKitC V4. Power: LiFePO4 cell wired directly
 * to the 3V3 pin (see hardware/README.md — do NOT use 5V/VIN). The
 * DevKitC's onboard power LED must be desoldered before field deployment
 * (a hardware step, not something this firmware can do) — it draws
 * quiescent current a bare module wouldn't, which matters a lot on a
 * battery/solar budget.
 */

#include <Arduino.h>
#include <HX711.h>
#include <SPI.h>
#include <LoRa.h>

// ---------------------------------------------------------------------
// Pin map — adjust to match actual wiring before flashing. These are the
// values used during bring-up; nothing here is read from any config file
// (this node has no WiFi/filesystem access to read one).
// ---------------------------------------------------------------------
#define HX711_DOUT_PIN 16
#define HX711_SCK_PIN 17

#define LORA_SCK_PIN 5
#define LORA_MISO_PIN 19
#define LORA_MOSI_PIN 27
#define LORA_NSS_PIN 18
#define LORA_RST_PIN 14
#define LORA_DIO0_PIN 26
#define LORA_FREQUENCY_HZ 868E6  // 868 MHz — EU ISM band.

// Battery voltage sense: a two-resistor divider from the LiFePO4 cell's
// raw terminal voltage (max ~3.6V) down to a range the ESP32's ADC can
// read safely. Equal-value resistors (e.g. 100k + 100k) halve the
// voltage, comfortably under the ADC's ~3.3V ceiling with margin, and
// draw negligible current from the battery. Adjust BATTERY_DIVIDER_RATIO
// if a different resistor pair is actually used.
#define BATTERY_ADC_PIN 34
#define BATTERY_DIVIDER_RATIO 2.0f  // (R1 + R2) / R2, for a 1:1 divider.
#define ADC_REFERENCE_VOLTAGE 3.3f
#define ADC_MAX_READING 4095.0f  // 12-bit ADC.

// ---------------------------------------------------------------------
// Calibration — MUST be set per physical build, not left at the
// placeholder. Standard HX711 calibration procedure: zero the scale
// with nothing on it (tare), then place a known weight (e.g. a 5kg test
// mass) and compute CALIBRATION_FACTOR = raw_reading / known_weight_kg.
// Repeat with a couple of different known weights and average for a
// better fit. This firmware reads all four load cells as a single
// summed signal (they're wired into one bridge via the junction box
// described in task 0079's weatherproofing notes, feeding one HX711),
// so calibration is against the combined four-corner reading, not any
// individual cell.
// ---------------------------------------------------------------------
#define CALIBRATION_FACTOR 1.0f  // TODO: replace with a real measured value.
#define TARE_OFFSET 0.0f          // TODO: replace with a real measured value.

// How many raw samples to average per wake cycle — reduces noise at the
// cost of a slightly longer, slightly higher-current read window.
#define HX711_SAMPLES_TO_AVERAGE 10

// How long the node sleeps between readings. 1800s (30 minutes) matches
// SensorDevice::DEFAULT_SUGGESTED_INTERVAL_SECONDS on the HiveLog side —
// keep these in agreement if either changes, though nothing enforces it
// automatically (the config descriptor's suggested_interval_seconds is
// advisory only; this firmware doesn't read it, since the sensor node
// has no network access to fetch it).
#define SLEEP_INTERVAL_SECONDS 1800
#define MICROSECONDS_PER_SECOND 1000000ULL

// Small protocol tag so the receiver can recognise and, if needed in
// future, version this packet format. Keys are short to save airtime;
// values are plain decimal text for easy debugging with a serial LoRa
// sniffer during bring-up.
#define PROTOCOL_TAG "HLOG1"

// Persisted across deep-sleep cycles via the RTC memory, so packets can
// be numbered even though the whole rest of the program state is lost
// on each sleep/wake. Purely a debugging aid (detecting dropped
// packets); the receiver does not require sequence numbers to be
// contiguous.
RTC_DATA_ATTR uint32_t sequence_number = 0;

HX711 scale;

float readWeightKg() {
  scale.begin(HX711_DOUT_PIN, HX711_SCK_PIN);
  // Give the HX711 a moment to settle after power-up before the first
  // real read — deep sleep fully powers this down between cycles.
  delay(200);
  long raw = scale.read_average(HX711_SAMPLES_TO_AVERAGE);
  return (raw - TARE_OFFSET) / CALIBRATION_FACTOR;
}

float readBatteryVoltage() {
  int raw = analogRead(BATTERY_ADC_PIN);
  float adc_voltage = (raw / ADC_MAX_READING) * ADC_REFERENCE_VOLTAGE;
  return adc_voltage * BATTERY_DIVIDER_RATIO;
}

bool sendReading(float weight_kg, float battery_voltage) {
  if (!LoRa.begin(LORA_FREQUENCY_HZ)) {
    return false;
  }

  char payload[96];
  snprintf(
    payload, sizeof(payload),
    "%s|weight_kg=%.3f|battery_voltage=%.3f|seq=%lu",
    PROTOCOL_TAG, weight_kg, battery_voltage,
    static_cast<unsigned long>(sequence_number)
  );

  LoRa.beginPacket();
  LoRa.print(payload);
  bool sent = LoRa.endPacket();

  LoRa.end();
  return sent;
}

void goToSleep() {
  esp_sleep_enable_timer_wakeup(
    static_cast<uint64_t>(SLEEP_INTERVAL_SECONDS) * MICROSECONDS_PER_SECOND
  );
  esp_deep_sleep_start();
  // Never reached — deep sleep resets execution back to setup() on wake.
}

void setup() {
  Serial.begin(115200);

  SPI.begin(LORA_SCK_PIN, LORA_MISO_PIN, LORA_MOSI_PIN, LORA_NSS_PIN);
  LoRa.setPins(LORA_NSS_PIN, LORA_RST_PIN, LORA_DIO0_PIN);

  float weight_kg = readWeightKg();
  float battery_voltage = readBatteryVoltage();

  Serial.printf(
    "weight_kg=%.3f battery_voltage=%.3f seq=%lu\n",
    weight_kg, battery_voltage,
    static_cast<unsigned long>(sequence_number)
  );

  bool sent = sendReading(weight_kg, battery_voltage);
  if (!sent) {
    // No retry queue on this node deliberately — it's about to sleep for
    // up to 30 minutes regardless, and a single dropped reading is not
    // worth the extra always-on radio time a retry would cost on a
    // solar/battery budget. A gap shows up as a receiver-side "device
    // offline" signal (SensorDevice.last_seen) if it happens repeatedly.
    Serial.println("LoRa send failed.");
  }

  sequence_number++;
  goToSleep();
}

void loop() {
  // Unreachable: setup() always ends in deep sleep, which restarts
  // execution from setup() on the next wake rather than returning here.
}
