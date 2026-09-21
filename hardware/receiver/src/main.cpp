/**
 * HiveLog pilot receiver node — task 0079.
 *
 * Bridges the sensor node's raw point-to-point LoRa packets onto
 * HiveLog's ingestion endpoint. Reads its own bearer token and endpoint
 * URL from a locally stored configuration descriptor
 * (docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §3) rather than anything baked into this source — the same firmware
 * image is reused unchanged for any device; only the copied-on
 * config.json differs. Downloaded once via
 * docs/project-management/tasks/0078-sensor-device-configuration-descriptor.md's
 * "Download Configuration" action and copied onto this board's LittleFS
 * filesystem as /config.json before first boot.
 *
 * Mains-powered, WiFi-connected — unlike the sensor node, this board can
 * freely use WiFi, NTP and HTTPS.
 */

#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <LittleFS.h>
#include <LoRa.h>
#include <SPI.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <time.h>

#include "secrets.h"  // Copy secrets.h.example to secrets.h and fill in.

// ---------------------------------------------------------------------
// Pin map — must match the sensor node's LoRa wiring (same radio module
// type, same frequency); the two ends of a raw point-to-point link have
// no negotiation step, so a mismatch here just means silence.
// ---------------------------------------------------------------------
#define LORA_SCK_PIN 5
#define LORA_MISO_PIN 19
#define LORA_MOSI_PIN 27
#define LORA_NSS_PIN 18
#define LORA_RST_PIN 14
#define LORA_DIO0_PIN 26
#define LORA_FREQUENCY_HZ 868E6

#define CONFIG_PATH "/config.json"
#define PROTOCOL_TAG "HLOG1"

// NTP servers for wall-clock time — needed because this board has no
// RTC of its own and `recorded` must be a real timestamp, not just
// millis() since boot.
#define NTP_SERVER_1 "pool.ntp.org"
#define NTP_SERVER_2 "time.nist.gov"
#define NTP_GMT_OFFSET_SECONDS 0
#define NTP_DAYLIGHT_OFFSET_SECONDS 0

String g_endpoint_url;
String g_bearer_token;

/**
 * Connects to WiFi using the credentials in secrets.h. Blocks until
 * connected — acceptable for a mains-powered board that isn't racing a
 * battery budget, unlike the sensor node.
 */
void connectWifi() {
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println(" connected.");
}

/**
 * Loads the endpoint URL and bearer token from the locally stored
 * configuration descriptor. Halts (via an infinite retry loop with a
 * visible serial message) if the file is missing or malformed, since
 * there's nothing useful this board can do without it.
 */
void loadConfig() {
  if (!LittleFS.begin(true)) {
    Serial.println("LittleFS mount failed.");
    return;
  }

  File file = LittleFS.open(CONFIG_PATH, "r");
  if (!file) {
    Serial.println(
      "config.json not found — copy a downloaded SensorDevice "
      "configuration onto this board's filesystem first."
    );
    return;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, file);
  file.close();

  if (error) {
    Serial.print("config.json parse error: ");
    Serial.println(error.c_str());
    return;
  }

  g_endpoint_url = doc["endpoint"]["url"].as<String>();
  g_bearer_token = doc["auth"]["token"].as<String>();

  Serial.print("Loaded config for endpoint: ");
  Serial.println(g_endpoint_url);
}

/**
 * Formats the current NTP-synced wall-clock time as an ISO 8601 UTC
 * string, matching the `recorded` shape in
 * docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §2's example payload.
 */
String currentTimestampIso8601() {
  time_t now;
  time(&now);
  struct tm timeinfo;
  gmtime_r(&now, &timeinfo);

  char buffer[25];
  strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%SZ", &timeinfo);
  return String(buffer);
}

/**
 * Parses a sensor-node packet of the form
 * "HLOG1|weight_kg=42.710|battery_voltage=3.680|seq=123" into a map of
 * key/value pairs. Returns an empty map (and logs why) if the protocol
 * tag doesn't match — a forward-compatibility guard in case a future
 * firmware version changes the packet shape.
 */
std::map<String, String> parsePacket(const String &raw) {
  std::map<String, String> fields;

  int first_pipe = raw.indexOf('|');
  if (first_pipe == -1 || raw.substring(0, first_pipe) != PROTOCOL_TAG) {
    Serial.println("Ignoring packet with unrecognised protocol tag.");
    return fields;
  }

  String remainder = raw.substring(first_pipe + 1);
  int start = 0;
  while (start < static_cast<int>(remainder.length())) {
    int pipe = remainder.indexOf('|', start);
    String segment = (pipe == -1)
      ? remainder.substring(start)
      : remainder.substring(start, pipe);

    int equals = segment.indexOf('=');
    if (equals != -1) {
      fields[segment.substring(0, equals)] = segment.substring(equals + 1);
    }

    if (pipe == -1) {
      break;
    }
    start = pipe + 1;
  }

  return fields;
}

/**
 * Builds the JSON batch body and POSTs it to the ingestion endpoint.
 *
 * `setInsecure()` skips TLS certificate validation — a deliberate,
 * documented simplification for a Phase 1 hobbyist pilot (embedding and
 * rotating a pinned CA certificate in firmware is real added complexity
 * this pilot doesn't need yet); revisit before a wider/production
 * rollout. No retry queue on failure — a single dropped batch is
 * logged and discarded, matching the sensor node's own "a gap shows up
 * as last_seen going stale" tolerance for this pilot.
 */
void postReading(float weight_kg, float battery_voltage, int rssi) {
  if (g_endpoint_url.isEmpty() || g_bearer_token.isEmpty()) {
    Serial.println("No config loaded — cannot POST.");
    return;
  }

  String recorded = currentTimestampIso8601();

  JsonDocument doc;
  JsonArray batch = doc.to<JsonArray>();

  JsonObject weight_item = batch.add<JsonObject>();
  weight_item["metric"] = "weight_kg";
  weight_item["value"] = weight_kg;
  weight_item["recorded"] = recorded;

  JsonObject battery_item = batch.add<JsonObject>();
  battery_item["metric"] = "battery_voltage";
  battery_item["value"] = battery_voltage;
  battery_item["recorded"] = recorded;

  JsonObject rssi_item = batch.add<JsonObject>();
  rssi_item["metric"] = "signal_rssi";
  rssi_item["value"] = rssi;
  rssi_item["recorded"] = recorded;

  String body;
  serializeJson(doc, body);

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.begin(client, g_endpoint_url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", "Bearer " + g_bearer_token);

  int status = http.POST(body);
  Serial.printf("POST %s -> %d\n", g_endpoint_url.c_str(), status);
  if (status != 201) {
    Serial.println(http.getString());
  }
  http.end();
}

void setup() {
  Serial.begin(115200);

  connectWifi();
  configTime(NTP_GMT_OFFSET_SECONDS, NTP_DAYLIGHT_OFFSET_SECONDS, NTP_SERVER_1, NTP_SERVER_2);
  loadConfig();

  SPI.begin(LORA_SCK_PIN, LORA_MISO_PIN, LORA_MOSI_PIN, LORA_NSS_PIN);
  LoRa.setPins(LORA_NSS_PIN, LORA_RST_PIN, LORA_DIO0_PIN);
  if (!LoRa.begin(LORA_FREQUENCY_HZ)) {
    Serial.println("LoRa init failed.");
  }
}

void loop() {
  int packet_size = LoRa.parsePacket();
  if (packet_size == 0) {
    delay(50);
    return;
  }

  String raw;
  while (LoRa.available()) {
    raw += static_cast<char>(LoRa.read());
  }
  int rssi = LoRa.packetRssi();

  Serial.print("Received: ");
  Serial.print(raw);
  Serial.printf(" (rssi=%d)\n", rssi);

  std::map<String, String> fields = parsePacket(raw);
  if (fields.count("weight_kg") == 0 || fields.count("battery_voltage") == 0) {
    Serial.println("Packet missing expected fields — dropped.");
    return;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected — reconnecting before POST.");
    connectWifi();
  }

  postReading(
    fields["weight_kg"].toFloat(),
    fields["battery_voltage"].toFloat(),
    rssi
  );
}
