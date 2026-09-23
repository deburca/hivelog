<?php

declare(strict_types=1);

namespace Drupal\assimilate;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;

/**
 * Generates one plausible new `SensorReading` per metric per cron run.
 *
 * A random walk anchored on each metric's own most recent reading (or a
 * realistic baseline, for a device's very first reading) — a small
 * per-run nudge plus Gaussian noise, clamped to a realistic range per
 * metric — not pure independent random noise, so a trend chart actually
 * shows a believable trend (task 0107's own explicit requirement)
 * instead of a flat scatter. `battery_voltage` additionally "resets" to
 * a full charge once it drifts low enough, simulating a battery swap
 * rather than draining to zero and staying there forever.
 */
class MockReadingGenerator {

  /**
   * Per-metric generation parameters.
   *
   * `baseline` seeds a device's very first reading for that metric.
   * `trend_per_run` is the deliberate per-run nudge (0 for metrics that
   * should just wander, e.g. temperature/humidity/signal). `noise` is
   * the Gaussian noise standard deviation added on top. `min`/`max`
   * clamp the result to a realistic range for the metric.
   */
  protected const METRIC_PARAMS = [
    'weight_kg' => [
      'baseline' => 22.0,
      'trend_per_run' => 0.08,
      'noise' => 0.35,
      'min' => 5.0,
      'max' => 60.0,
    ],
    'temp_internal_c' => [
      'baseline' => 34.5,
      'trend_per_run' => 0.0,
      'noise' => 0.4,
      'min' => 30.0,
      'max' => 38.0,
    ],
    'temp_external_c' => [
      'baseline' => 18.0,
      'trend_per_run' => 0.0,
      'noise' => 2.5,
      'min' => -10.0,
      'max' => 40.0,
    ],
    'humidity_internal_pct' => [
      'baseline' => 58.0,
      'trend_per_run' => 0.0,
      'noise' => 2.0,
      'min' => 40.0,
      'max' => 75.0,
    ],
    'humidity_external_pct' => [
      'baseline' => 60.0,
      'trend_per_run' => 0.0,
      'noise' => 5.0,
      'min' => 15.0,
      'max' => 95.0,
    ],
    'battery_voltage' => [
      'baseline' => 3.9,
      'trend_per_run' => -0.015,
      'noise' => 0.02,
      'min' => 3.0,
      'max' => 4.2,
    ],
    'signal_rssi' => [
      'baseline' => -68.0,
      'trend_per_run' => 0.0,
      'noise' => 4.0,
      'min' => -100.0,
      'max' => -40.0,
    ],
  ];

  /**
   * Fallback parameters for a metric not listed in METRIC_PARAMS.
   *
   * Not expected to be hit in practice — every metric
   * `SensorDevice::DEVICE_TYPE_METRICS` can produce is covered above —
   * but keeps generateReading() total rather than throwing on a future
   * metric this class hasn't been taught about yet.
   */
  protected const FALLBACK_PARAMS = [
    'baseline' => 0.0,
    'trend_per_run' => 0.0,
    'noise' => 1.0,
    'min' => -1000.0,
    'max' => 1000.0,
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {}

  /**
   * Generates and saves one new reading per metric the device reports.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to generate readings for.
   *
   * @return \Drupal\nanoprobe\Entity\SensorReading[]
   *   The newly-created, already-saved readings.
   */
  public function generateReadingsForDevice(SensorDevice $device): array {
    $readings = [];
    foreach ($device->getConfigMetrics() as $metric) {
      $readings[] = $this->generateReading($device, $metric);
    }
    return $readings;
  }

  /**
   * Generates and saves one new reading for a single device/metric.
   */
  protected function generateReading(SensorDevice $device, string $metric): SensorReading {
    $params = self::METRIC_PARAMS[$metric] ?? self::FALLBACK_PARAMS;
    $previous = $this->lastValue($device, $metric) ?? $params['baseline'];

    $value = $previous + $params['trend_per_run'] + $this->gaussianNoise($params['noise']);

    // Simulate a battery swap rather than draining to the floor and
    // staying there for every subsequent reading.
    if ($metric === 'battery_voltage' && $value <= $params['min'] + 0.1) {
      $value = $params['max'];
    }

    $value = max($params['min'], min($params['max'], $value));

    $reading = SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => $metric,
      'value' => round($value, 2),
      'recorded' => $this->time->getRequestTime(),
    ]);
    $reading->save();
    return $reading;
  }

  /**
   * The most recent reading's value for this device/metric, or NULL.
   */
  protected function lastValue(SensorDevice $device, string $metric): ?float {
    $storage = $this->entityTypeManager->getStorage('sensor_reading');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->sort('recorded', 'DESC')
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return NULL;
    }
    $reading = $storage->load(reset($ids));
    return $reading ? (float) $reading->get('value')->value : NULL;
  }

  /**
   * A single Gaussian-distributed random value via the Box-Muller transform.
   *
   * Plain `mt_rand()` noise would be uniform, not the bell-curve-shaped
   * noise real sensor readings actually have — most readings close to
   * the trend, occasional larger swings, never a hard cutoff.
   *
   * @param float $stddev
   *   The standard deviation of the returned value.
   *
   * @return float
   *   A random value with mean 0 and the given standard deviation.
   */
  protected function gaussianNoise(float $stddev): float {
    $u1 = max(mt_rand(1, mt_getrandmax()) / mt_getrandmax(), PHP_FLOAT_EPSILON);
    $u2 = mt_rand(0, mt_getrandmax()) / mt_getrandmax();
    $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    return $z * $stddev;
  }

}
