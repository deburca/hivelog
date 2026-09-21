<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one HTTP contract HiveLog exposes for automated sensor ingestion.
 *
 * Per docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §2/§3: this route carries `_access: 'TRUE'` — no Drupal permission gate
 * and no session CSRF token — because the caller is a device or bridge
 * with no Drupal session, authenticated instead by a per-device bearer
 * token this controller checks itself. This is a deliberate, narrow,
 * documented exception to the module's normal
 * `ApiaryAccessTrait`/session-CSRF model, scoped to this single route.
 */
class SensorIngestController extends ControllerBase {

  /**
   * Accepts one or a batch of sensor readings from an authenticated device.
   *
   * Batch semantics (a choice this task's acceptance criteria left open):
   * **all-or-nothing**. Every item in the body is validated before any
   * `SensorReading` is created; if any item fails validation, the whole
   * request is rejected with 422 and nothing is written. This keeps the
   * response shape simple (firmware only ever sees "all accepted" or
   * "nothing was written, here's why"), at the cost of one bad item in an
   * otherwise-good batch losing the whole batch — an acceptable trade-off
   * at the reporting intervals and batch sizes
   * [[0074-sensor-data-ingestion-architecture]] §5 describes (a handful
   * of readings queued through a connectivity gap, not hundreds).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   201 with the created reading ids on success; 401 for a missing or
   *   unrecognised token; 403 for a disabled device; 422 for a malformed
   *   body or a validation failure.
   */
  public function ingest(Request $request): JsonResponse {
    $device = $this->resolveDeviceFromToken($request);
    if ($device === NULL) {
      return new JsonResponse(['error' => 'Invalid or missing device token.'], Response::HTTP_UNAUTHORIZED);
    }
    if (!$device->get('enabled')->value) {
      return new JsonResponse(['error' => 'This device is disabled.'], Response::HTTP_FORBIDDEN);
    }

    $items = $this->parseBody($request);
    if ($items === NULL) {
      return new JsonResponse(['error' => 'Request body must be a single reading object or a JSON array of reading objects.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    if (empty($items)) {
      return new JsonResponse(['error' => 'At least one reading is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $errors = $this->validateItems($items);
    if (!empty($errors)) {
      return new JsonResponse(['error' => 'Validation failed.', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $created_ids = [];
    foreach ($items as $item) {
      $reading = SensorReading::create([
        'sensor_device' => $device->id(),
        'metric' => $item['metric'],
        'value' => $item['value'],
        'recorded' => $item['recorded_timestamp'],
      ]);
      $reading->save();
      $created_ids[] = $reading->id();
    }

    $device->set('last_seen', \Drupal::time()->getRequestTime());
    $device->save();

    return new JsonResponse(['created' => $created_ids], Response::HTTP_CREATED);
  }

  /**
   * Resolves the `SensorDevice` matching the request's bearer token.
   *
   * Checks every device, not only enabled ones, so a token that matches a
   * disabled device can be distinguished (403) from a token that matches
   * no device at all (401). `SensorDevice::verifyToken()` uses
   * `password_verify()` internally, which is timing-safe for the hash
   * comparison itself; iterating devices in id order is an accepted,
   * pilot-scale trade-off (a handful of devices, not thousands) — real
   * rate-limiting/lookup optimisation is explicitly deferred, per
   * [[0074-sensor-data-ingestion-architecture]] §2.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return \Drupal\nanoprobe\Entity\SensorDevice|null
   *   The matching device, or NULL if the token is missing or matches no
   *   device.
   */
  protected function resolveDeviceFromToken(Request $request): ?SensorDevice {
    $header = $request->headers->get('Authorization', '');
    if (!str_starts_with($header, 'Bearer ')) {
      return NULL;
    }
    $token = substr($header, strlen('Bearer '));
    if ($token === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager()->getStorage('sensor_device');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\nanoprobe\Entity\SensorDevice $device */
    foreach ($storage->loadMultiple($ids) as $device) {
      if ($device->verifyToken($token)) {
        return $device;
      }
    }

    return NULL;
  }

  /**
   * Decodes the request body into a list of reading item arrays.
   *
   * Accepts either a single JSON object (one reading) or a JSON array of
   * objects (a batch), per
   * [[0074-sensor-data-ingestion-architecture]] §2 — always normalised to
   * a plain list here so the rest of the controller only ever deals with
   * one shape.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return array[]|null
   *   A list of raw item arrays, or NULL if the body is not valid JSON or
   *   is neither a JSON object nor a JSON array of objects.
   */
  protected function parseBody(Request $request): ?array {
    $content = $request->getContent();
    try {
      $decoded = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }

    if (is_array($decoded) && array_is_list($decoded)) {
      foreach ($decoded as $item) {
        if (!is_array($item)) {
          return NULL;
        }
      }
      return $decoded;
    }

    if (is_array($decoded)) {
      // A single JSON object decodes to a non-list associative array.
      return [$decoded];
    }

    return NULL;
  }

  /**
   * Validates every item, returning per-item errors without writing anything.
   *
   * Also parses each item's `recorded` value into a UNIX timestamp and
   * stashes it as `recorded_timestamp`, so a valid batch's items are
   * ready to persist directly without re-parsing.
   *
   * @param array[] $items
   *   Raw item arrays, mutated in place to add `recorded_timestamp` on
   *   valid items.
   *
   * @return array[]
   *   A list of `{index, errors}` entries for invalid items; empty if
   *   every item is valid.
   */
  protected function validateItems(array &$items): array {
    $errors = [];

    foreach ($items as $index => &$item) {
      $item_errors = [];

      $metric = $item['metric'] ?? NULL;
      if (!is_string($metric) || !isset(SensorReading::METRIC_TYPES[$metric])) {
        $item_errors[] = 'metric must be one of: ' . implode(', ', array_keys(SensorReading::METRIC_TYPES));
      }

      $value = $item['value'] ?? NULL;
      if (!is_int($value) && !is_float($value)) {
        $item_errors[] = 'value must be a finite number.';
      }
      elseif (!is_finite((float) $value)) {
        $item_errors[] = 'value must be a finite number.';
      }

      $recorded = $item['recorded'] ?? NULL;
      $recorded_timestamp = $this->parseRecordedTimestamp($recorded);
      if ($recorded_timestamp === NULL) {
        $item_errors[] = 'recorded must be a valid timestamp.';
      }
      else {
        $item['recorded_timestamp'] = $recorded_timestamp;
      }

      if (!empty($item_errors)) {
        $errors[] = ['index' => $index, 'errors' => $item_errors];
      }
    }

    return $errors;
  }

  /**
   * Parses a `recorded` value (ISO 8601 string or UNIX timestamp) to epoch.
   *
   * @param mixed $recorded
   *   The raw `recorded` value from the request body.
   *
   * @return int|null
   *   A UNIX timestamp, or NULL if $recorded is missing or unparseable.
   */
  protected function parseRecordedTimestamp(mixed $recorded): ?int {
    if (is_int($recorded)) {
      return $recorded;
    }
    if (is_string($recorded) && $recorded !== '') {
      $timestamp = strtotime($recorded);
      return $timestamp === FALSE ? NULL : $timestamp;
    }
    return NULL;
  }

}
