<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;

/**
 * Collects sensor-driven "Needs attention" alert rows.
 *
 * Backs hook_hivelog_needs_attention_alerts() (nanoprobe.module) — see
 * hivelog.api.php and
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md
 * for why this is a hook implementation rather than
 * `DashboardController` calling into `nanoprobe` directly.
 *
 * Three simple threshold rules, per
 * docs/project-management/tasks/0081-sensor-needs-attention-alerts.md —
 * no forecasting or anomaly-detection model, matching
 * [[0074-sensor-data-ingestion-architecture]]'s "simple, explainable
 * rules over a model" style.
 */
class SensorAlertCollector {

  use StringTranslationTrait;

  /**
   * How stale `last_seen` must be before a device counts as offline.
   *
   * A flat 24h fallback — the task's own proposal of "2x the device's
   * typical reporting interval" needs a per-device interval value that
   * doesn't exist yet (`SensorDevice::DEFAULT_SUGGESTED_INTERVAL_SECONDS`
   * is only ever used to build a config descriptor, never persisted on
   * the entity) and is explicitly flagged as needing real Phase 1 data
   * to calibrate — the flat fallback is what's actually implementable
   * today.
   */
  protected const OFFLINE_THRESHOLD_SECONDS = 86400;

  /**
   * The weight-drop threshold (kg) that fires the "possible swarm" alert.
   *
   * The middle of [[0074-sensor-data-ingestion-architecture]] §7's cited
   * 1–3kg range.
   */
  protected const WEIGHT_DROP_THRESHOLD_KG = 1.5;

  /**
   * Healthy brood-nest temperature range, in Celsius.
   */
  protected const BROOD_TEMP_MIN_C = 33.0;
  protected const BROOD_TEMP_MAX_C = 36.0;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Collects every sensor-driven alert row for the given apiaries.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Apiaries the current user may view, keyed by entity id.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheability collector.
   *
   * @return array[]
   *   Alert rows, shaped like DashboardController::collectLowStockAlerts()'s.
   */
  public function collectAlerts(array $apiaries, CacheableMetadata $cache): array {
    if (empty($apiaries)) {
      return [];
    }

    $devices = $this->loadAccessibleDevices(array_keys($apiaries));
    if (empty($devices)) {
      return [];
    }

    $cache->addCacheTags($this->entityTypeManager->getDefinition('sensor_device')->getListCacheTags());
    $cache->addCacheTags($this->entityTypeManager->getDefinition('sensor_reading')->getListCacheTags());

    $alerts = [];
    foreach ($devices as $device) {
      $cache->addCacheableDependency($device);
      $apiary = $apiaries[(int) $device->get('apiary')->target_id] ?? NULL;
      if (!$apiary) {
        // Shouldn't happen (loadAccessibleDevices() already filtered by
        // these apiary ids), but a missing parent is a broken chain, not
        // an alert-worthy condition — skip defensively.
        continue;
      }
      $hive = $device->get('hive')->entity;

      foreach ([
        $this->checkDeviceOffline($device, $apiary, $hive),
        $this->checkWeightDrop($device, $apiary, $hive),
        $this->checkTemperatureOutOfRange($device, $apiary, $hive),
      ] as $alert) {
        if ($alert !== NULL) {
          $alerts[] = $alert;
        }
      }
    }

    return $alerts;
  }

  /**
   * Loads enabled SensorDevice entities under the given apiaries.
   *
   * `apiary` is a required field on every device regardless of `scope`
   * (a hive-scoped device still records its ultimate apiary), so one
   * query covers both hive- and apiary-scoped devices.
   *
   * @param int[] $apiary_ids
   *   Apiary ids to match.
   *
   * @return \Drupal\nanoprobe\Entity\SensorDevice[]
   *   Accessible, enabled devices.
   */
  protected function loadAccessibleDevices(array $apiary_ids): array {
    $storage = $this->entityTypeManager->getStorage('sensor_device');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('enabled', 1)
      ->execute();
    if (empty($ids)) {
      return [];
    }

    $devices = $storage->loadMultiple($ids);
    return array_filter(
      $devices,
      fn(SensorDevice $device) => $device->access('view')
    );
  }

  /**
   * Device offline rule: `last_seen` older than the threshold.
   *
   * A device that has never reported at all (`last_seen` empty) is not
   * "offline" — it's simply not provisioned/sending yet, and alerting on
   * that would false-alarm on every freshly-registered device.
   */
  protected function checkDeviceOffline(SensorDevice $device, Apiary $apiary, ?Hive $hive): ?array {
    if ($device->get('last_seen')->isEmpty()) {
      return NULL;
    }
    $last_seen = (int) $device->get('last_seen')->value;
    $age = $this->time->getRequestTime() - $last_seen;
    if ($age < self::OFFLINE_THRESHOLD_SECONDS) {
      return NULL;
    }

    $device_url = $device->access('update')
      ? Url::fromRoute('entity.sensor_device.canonical', ['sensor_device' => $device->id()])->toString()
      : NULL;

    return [
      'severity' => 'warning',
      'chip' => $this->t('Device offline'),
      'title' => $device->label(),
      'context' => $this->attentionContext($apiary, $hive, $this->t('Last seen @time ago', [
        '@time' => $this->dateFormatter->formatTimeDiffSince($last_seen),
      ])),
      'action_label' => $this->t('View Device'),
      'action_url' => $device_url ?? $apiary->toUrl()->toString(),
      'sort' => [2, mb_strtolower($device->label())],
    ];
  }

  /**
   * Sudden weight drop rule — a possible swarm.
   *
   * A same-day drop past the threshold between the two most recent
   * `weight_kg` readings.
   */
  protected function checkWeightDrop(SensorDevice $device, Apiary $apiary, ?Hive $hive): ?array {
    $readings = $this->latestReadings($device, 'weight_kg', 2);
    if (count($readings) < 2) {
      return NULL;
    }
    [$latest, $previous] = $readings;

    $latest_date = $this->dateFormatter->format((int) $latest->get('recorded')->value, 'custom', 'Y-m-d', 'UTC');
    $previous_date = $this->dateFormatter->format((int) $previous->get('recorded')->value, 'custom', 'Y-m-d', 'UTC');
    if ($latest_date !== $previous_date) {
      return NULL;
    }

    $drop = (float) $previous->get('value')->value - (float) $latest->get('value')->value;
    if ($drop < self::WEIGHT_DROP_THRESHOLD_KG) {
      return NULL;
    }

    return [
      'severity' => 'critical',
      'chip' => $this->t('Possible swarm'),
      'title' => $device->label(),
      'context' => $this->attentionContext($apiary, $hive, $this->t('Weight dropped @drop kg today', [
        '@drop' => $this->trimDecimal($drop),
      ])),
      'action_label' => $this->t('View Hive'),
      'action_url' => ($hive ?? $apiary)->toUrl()->toString(),
      'sort' => [0, -$drop],
    ];
  }

  /**
   * Brood temperature rule.
   *
   * The two most recent `temp_internal_c` readings both outside the
   * healthy range — "sustained", not a single noisy blip.
   */
  protected function checkTemperatureOutOfRange(SensorDevice $device, Apiary $apiary, ?Hive $hive): ?array {
    $readings = $this->latestReadings($device, 'temp_internal_c', 2);
    if (count($readings) < 2) {
      return NULL;
    }

    foreach ($readings as $reading) {
      $value = (float) $reading->get('value')->value;
      if ($value >= self::BROOD_TEMP_MIN_C && $value <= self::BROOD_TEMP_MAX_C) {
        // At least one of the two recent readings is back in range —
        // not sustained.
        return NULL;
      }
    }

    $latest_value = (float) $readings[0]->get('value')->value;

    return [
      'severity' => 'warning',
      'chip' => $this->t('Temperature out of range'),
      'title' => $device->label(),
      'context' => $this->attentionContext($apiary, $hive, $this->t('@value °C, outside @min–@max °C brood range', [
        '@value' => $this->trimDecimal($latest_value),
        '@min' => self::BROOD_TEMP_MIN_C,
        '@max' => self::BROOD_TEMP_MAX_C,
      ])),
      'action_label' => $this->t('View Hive'),
      'action_url' => ($hive ?? $apiary)->toUrl()->toString(),
      'sort' => [2, mb_strtolower($device->label())],
    ];
  }

  /**
   * The N most recent readings for a device/metric, newest first.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to query.
   * @param string $metric
   *   The metric machine name.
   * @param int $limit
   *   Maximum readings to return.
   *
   * @return \Drupal\nanoprobe\Entity\SensorReading[]
   *   Readings, newest first — a plain numeric-indexed list, not keyed
   *   by entity id, so callers can safely use `[$latest, $previous] =`.
   */
  protected function latestReadings(SensorDevice $device, string $metric, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('sensor_reading');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->sort('recorded', 'DESC')
      ->range(0, $limit)
      ->execute();
    if (empty($ids)) {
      return [];
    }

    $readings = $storage->loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $id) {
      if (isset($readings[$id])) {
        /** @var \Drupal\nanoprobe\Entity\SensorReading $reading */
        $reading = $readings[$id];
        if ($reading->access('view')) {
          $ordered[] = $reading;
        }
      }
    }
    return $ordered;
  }

  /**
   * Builds a row's context line — apiary (and hive) links plus a detail.
   *
   * Matches DashboardController::attentionContext()'s exact markup/CSS
   * classes (`.hivelog-attention__ctx`) so a sensor row renders
   * identically to a seasonal/low-stock row; that method is
   * core-private, so this is an independent, identical implementation,
   * not shared code.
   */
  protected function attentionContext(Apiary $apiary, ?Hive $hive, $detail): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<span class="hivelog-attention__ctx">{{ apiary }}{% if hive %} › {{ hive }}{% endif %} · {{ detail }}</span>',
      '#context' => [
        'apiary' => $apiary->toLink()->toRenderable(),
        'hive' => $hive ? $hive->toLink()->toRenderable() : NULL,
        'detail' => $detail,
      ],
    ];
  }

  /**
   * Trims trailing zeros from a decimal, e.g. 2.000 → "2", 1.500 → "1.5".
   */
  protected function trimDecimal(float $value): string {
    return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
  }

}
