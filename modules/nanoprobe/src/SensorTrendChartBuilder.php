<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;

/**
 * Renders a device/metric's daily min/max/avg trend as an inline SVG chart.
 *
 * Extracted from `SensorPanelBuilder` (task 0110) so the same charting
 * code serves both that class's 30-trailing-days dashboard panel chart
 * and `SensorDeviceController::readings()`'s full-history page — the
 * only real difference between the two is the date range (and, since
 * this class has no opinion on "last N days" vs. "a custom range", the
 * chart's own label text), not the aggregation/rendering logic itself.
 */
class SensorTrendChartBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected AccountInterface $currentUser,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds a trend chart for one device/metric over an explicit date range.
   *
   * Aggregates raw readings into daily min/max/avg **on read** for the
   * portion of the range still within
   * `SensorReadingRetentionService::RAW_RETENTION_DAYS`, and reads the
   * persisted `SensorReadingDaily` rollup (task 0082) for any older
   * portion whose raw rows may already have been purged.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to chart.
   * @param string $metric
   *   The metric to chart.
   * @param int $start
   *   Range start (inclusive), UNIX timestamp.
   * @param int $end
   *   Range end (inclusive), UNIX timestamp.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $chart_label
   *   The chart's own accessible label/title — the caller knows whether
   *   this is "last 30 days" or a custom range, this class doesn't.
   *   Defaults to a plain "@metric trend" if omitted.
   *
   * @return array
   *   A render array, or an empty array if fewer than two distinct days
   *   of data exist in the range (a single point isn't a trend).
   */
  public function buildChart(SensorDevice $device, string $metric, int $start, int $end, ?TranslatableMarkup $chart_label = NULL): array {
    $raw_retention_cutoff = $end - (SensorReadingRetentionService::RAW_RETENTION_DAYS * 86400);

    $daily = [];

    $raw_start = max($start, $raw_retention_cutoff);
    if ($raw_start < $end) {
      $this->collectDailyAggregatesFromRaw($device, $metric, $raw_start, $end, $daily);
    }
    if ($start < $raw_retention_cutoff) {
      $this->collectDailyAggregatesFromRollup($device, $metric, $start, min($end, $raw_retention_cutoff), $daily);
    }

    ksort($daily);

    if (count($daily) < 2) {
      return [];
    }

    $points = [];
    foreach ($daily as $date => $aggregate) {
      $points[] = [
        'date' => $date,
        'min' => $aggregate['min'],
        'max' => $aggregate['max'],
        'avg' => $aggregate['sum'] / $aggregate['count'],
      ];
    }

    $metric_label = SensorReading::METRIC_TYPES[$metric] ?? $metric;
    $chart_label ??= $this->t('@metric trend', ['@metric' => $metric_label]);

    return $this->renderTrendSvg($points, $chart_label);
  }

  /**
   * Aggregates raw SensorReading rows into $daily's per-date buckets.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to query.
   * @param string $metric
   *   The metric to query.
   * @param int $start
   *   Window start (inclusive), UNIX timestamp.
   * @param int $end
   *   Window end (inclusive), UNIX timestamp.
   * @param array $daily
   *   Per-date `['min' => float, 'max' => float, 'sum' => float, 'count'
   *   => int]` buckets, keyed by `Y-m-d`, merged into by reference.
   */
  protected function collectDailyAggregatesFromRaw(SensorDevice $device, string $metric, int $start, int $end, array &$daily): void {
    $storage = $this->entityTypeManager->getStorage('sensor_reading');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->condition('recorded', $start, '>=')
      ->condition('recorded', $end, '<=')
      ->execute();
    if (empty($ids)) {
      return;
    }

    foreach ($storage->loadMultiple($ids) as $reading) {
      $date = $this->dateFormatter->format((int) $reading->get('recorded')->value, 'custom', 'Y-m-d', 'UTC');
      $value = (float) $reading->get('value')->value;
      if (!isset($daily[$date])) {
        $daily[$date] = ['min' => $value, 'max' => $value, 'sum' => $value, 'count' => 1];
      }
      else {
        $daily[$date]['min'] = min($daily[$date]['min'], $value);
        $daily[$date]['max'] = max($daily[$date]['max'], $value);
        $daily[$date]['sum'] += $value;
        $daily[$date]['count']++;
      }
    }
  }

  /**
   * Reads persisted SensorReadingDaily rollups into $daily's per-date buckets.
   *
   * Used for the portion of a chart's range old enough that raw rows may
   * already be purged (task 0082) — a rollup row's `avg_value` is used
   * directly (`sum` = `avg_value`, `count` = 1), rather than re-deriving
   * an average from raw data that may no longer exist.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to query.
   * @param string $metric
   *   The metric to query.
   * @param int $start
   *   Window start (inclusive), UNIX timestamp.
   * @param int $end
   *   Window end (inclusive), UNIX timestamp.
   * @param array $daily
   *   Per-date buckets, keyed by `Y-m-d`, merged into by reference. A
   *   rollup row always replaces any existing bucket for its date rather
   *   than merging with it — the two data sources cover disjoint date
   *   ranges by construction (see buildChart()).
   */
  protected function collectDailyAggregatesFromRollup(SensorDevice $device, string $metric, int $start, int $end, array &$daily): void {
    $start_date = $this->dateFormatter->format($start, 'custom', 'Y-m-d', 'UTC');
    $end_date = $this->dateFormatter->format($end, 'custom', 'Y-m-d', 'UTC');

    $storage = $this->entityTypeManager->getStorage('sensor_reading_daily');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->condition('date', $start_date, '>=')
      ->condition('date', $end_date, '<=')
      ->execute();
    if (empty($ids)) {
      return;
    }

    foreach ($storage->loadMultiple($ids) as $rollup) {
      if (!$rollup->access('view', $this->currentUser)) {
        continue;
      }
      $daily[$rollup->get('date')->value] = [
        'min' => (float) $rollup->get('min_value')->value,
        'max' => (float) $rollup->get('max_value')->value,
        'sum' => (float) $rollup->get('avg_value')->value,
        'count' => 1,
      ];
    }
  }

  /**
   * Renders daily min/max/avg points as an inline SVG band + line chart.
   *
   * Same "inline SVG, no charting-library dependency" technique as
   * `HiveController::buildWeightHistogram()`, independently implemented
   * here since that method is core-private and the underlying data
   * shape differs (a continuous daily trend, not discrete year-scoped
   * inspection weigh-ins).
   *
   * @param array $points
   *   Daily aggregates: `['date' => 'Y-m-d', 'min' => float, 'max' =>
   *   float, 'avg' => float]`, sorted ascending by date, at least 2.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The chart's accessible label/title.
   *
   * @return array
   *   A render array.
   */
  protected function renderTrendSvg(array $points, TranslatableMarkup $label): array {
    $svg_width = 800;
    $svg_height = 200;
    $padding_top = 20;
    $padding_bottom = 30;
    $padding_x = 40;
    $chart_height = $svg_height - $padding_top - $padding_bottom;
    $chart_width = $svg_width - (2 * $padding_x);

    $all_values = [];
    foreach ($points as $point) {
      $all_values[] = $point['min'];
      $all_values[] = $point['max'];
    }
    $min_value = min($all_values);
    $max_value = max($all_values);
    $range = ($max_value - $min_value) ?: 1.0;

    $count = count($points);
    $x_for = fn(int $index) => $padding_x + ($index / ($count - 1)) * $chart_width;
    $y_for = fn(float $value) => $padding_top + $chart_height - (($value - $min_value) / $range) * $chart_height;

    $band_top = [];
    $band_bottom = [];
    $avg_line = [];
    foreach ($points as $index => $point) {
      $x = round($x_for($index), 1);
      $band_top[] = $x . ',' . round($y_for($point['max']), 1);
      $band_bottom[] = $x . ',' . round($y_for($point['min']), 1);
      $avg_line[] = $x . ',' . round($y_for($point['avg']), 1);
    }
    $band_points = implode(' ', $band_top) . ' ' . implode(' ', array_reverse($band_bottom));
    $avg_points = implode(' ', $avg_line);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['nanoprobe-sensor-trend']],
      '#attached' => ['library' => ['nanoprobe/sensor_trend']],
      'chart' => [
        '#type' => 'inline_template',
        '#template' => '<div class="nanoprobe-sensor-trend__frame"><svg viewBox="0 0 {{ svg_width }} {{ svg_height }}" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ label }}"><title>{{ label }}</title><polygon class="nanoprobe-sensor-trend__band" points="{{ band_points }}" /><polyline class="nanoprobe-sensor-trend__avg" points="{{ avg_points }}" fill="none" /><text class="nanoprobe-sensor-trend__date" x="{{ padding_x }}" y="{{ svg_height - 8 }}" font-size="11">{{ first_label }}</text><text class="nanoprobe-sensor-trend__date" x="{{ svg_width - padding_x }}" y="{{ svg_height - 8 }}" text-anchor="end" font-size="11">{{ last_label }}</text></svg></div>',
        '#context' => [
          'label' => $label,
          'svg_width' => $svg_width,
          'svg_height' => $svg_height,
          'padding_x' => $padding_x,
          'band_points' => $band_points,
          'avg_points' => $avg_points,
          'first_label' => $points[0]['date'],
          'last_label' => $points[$count - 1]['date'],
        ],
      ],
    ];
  }

}
