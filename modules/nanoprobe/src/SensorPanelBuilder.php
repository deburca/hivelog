<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;

/**
 * Builds the read-only "Sensors" panel for Hive/Apiary canonical pages.
 *
 * Backs hook_hivelog_hive_view_panels()/hook_hivelog_apiary_view_panels()
 * (nanoprobe.module) — see
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md
 * for why this is a hook implementation rather than hivelog core calling
 * into nanoprobe directly.
 */
class SensorPanelBuilder {

  use StringTranslationTrait;

  /**
   * How many of a device's most recent readings to scan for "latest per metric".
   *
   * Devices report only a handful of distinct metrics (per
   * SensorDevice::DEVICE_TYPE_METRICS), so 50 comfortably covers several
   * interleaved metrics' latest values without a heavier query.
   */
  protected const LATEST_READING_SAMPLE_SIZE = 50;

  /**
   * How many trailing days of readings the trend chart covers.
   */
  protected const TREND_WINDOW_DAYS = 30;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds the Sensors panel for a Hive canonical page.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if
   *   there is no accessible sensor data to show.
   */
  public function buildHivePanel(Hive $hive): array {
    $devices = $this->loadAccessibleDevices([
      'hive' => $hive->id(),
      'scope' => 'hive',
      'enabled' => 1,
    ]);
    return $this->buildPanel($devices);
  }

  /**
   * Builds the Sensors panel for an Apiary canonical page.
   *
   * Apiary-scoped devices only — hive-scoped devices attached to hives
   * within this apiary appear on their own hive's page instead, not
   * rolled up here.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if
   *   there is no accessible sensor data to show.
   */
  public function buildApiaryPanel(Apiary $apiary): array {
    $devices = $this->loadAccessibleDevices([
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'enabled' => 1,
    ]);
    return $this->buildPanel($devices);
  }

  /**
   * Loads SensorDevice entities matching $conditions, filtered to viewable ones.
   *
   * The query conditions already narrow by hive/apiary/scope/enabled,
   * but access is still checked explicitly per entity — apiary
   * membership determines *readability*, per
   * [[0019-authorisation-model]]; a query condition on `hive`/`apiary`
   * alone says nothing about whether the current user may see it.
   *
   * @param array $conditions
   *   Field-name => value conditions for the sensor_device query.
   *
   * @return \Drupal\nanoprobe\Entity\SensorDevice[]
   *   Accessible devices, keyed by entity id.
   */
  protected function loadAccessibleDevices(array $conditions): array {
    $storage = $this->entityTypeManager->getStorage('sensor_device');
    $query = $storage->getQuery()->accessCheck(TRUE);
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    $devices = $storage->loadMultiple($ids);
    return array_filter(
      $devices,
      fn(SensorDevice $device) => $device->access('view', $this->currentUser)
    );
  }

  /**
   * Assembles the panel render array from a set of accessible devices.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice[] $devices
   *   Devices to include, already access-filtered.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if no
   *   device actually has any accessible reading to show (an attached
   *   device that has never reported yet contributes nothing).
   */
  protected function buildPanel(array $devices): array {
    $device_sections = [];
    foreach ($devices as $device) {
      $section = $this->buildDeviceSection($device);
      if (!empty($section)) {
        $device_sections['device_' . $device->id()] = $section;
      }
    }

    if (empty($device_sections)) {
      return [];
    }

    return [
      'nanoprobe_sensors' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['nanoprobe-sensors-panel']],
        '#weight' => 7.5,
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Sensors'),
        ],
      ] + $device_sections,
    ];
  }

  /**
   * Builds one device's section: latest reading + trend chart per metric.
   *
   * Covers only the metrics the device has actually reported.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to build a section for.
   *
   * @return array
   *   A render array, or an empty array if the device has no accessible
   *   reading at all yet.
   */
  protected function buildDeviceSection(SensorDevice $device): array {
    $latest_by_metric = $this->getLatestReadingPerMetric($device);

    $metric_sections = [];
    foreach ($latest_by_metric as $metric => $reading) {
      if (!$reading->access('view', $this->currentUser)) {
        continue;
      }
      $metric_sections['metric_' . $metric] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['nanoprobe-sensor-metric']],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->formatLatestReadingSummary($metric, $reading),
        ],
        'chart' => $this->buildTrendChart($device, $metric),
      ];
    }

    if (empty($metric_sections)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['nanoprobe-sensor-device']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $device->label(),
      ],
    ] + $metric_sections;
  }

  /**
   * Finds the most recent reading for each distinct metric a device reported.
   *
   * Scans the device's most recent LATEST_READING_SAMPLE_SIZE readings
   * (across all metrics, newest first) rather than running one query per
   * possible metric — cheaper, and correct at Phase 1 pilot scale where
   * a device reports only a handful of distinct metrics per
   * SensorDevice::DEVICE_TYPE_METRICS.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to look up readings for.
   *
   * @return \Drupal\nanoprobe\Entity\SensorReading[]
   *   The latest reading per metric, keyed by metric machine name.
   */
  protected function getLatestReadingPerMetric(SensorDevice $device): array {
    $storage = $this->entityTypeManager->getStorage('sensor_reading');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('sensor_device', $device->id())
      ->sort('recorded', 'DESC')
      ->range(0, self::LATEST_READING_SAMPLE_SIZE)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $readings = $storage->loadMultiple($ids);

    // loadMultiple() doesn't promise to preserve $ids's order, so walk
    // $ids explicitly to keep the DESC-by-recorded ordering the query
    // itself established.
    $latest_by_metric = [];
    foreach ($ids as $id) {
      if (!isset($readings[$id])) {
        continue;
      }
      /** @var \Drupal\nanoprobe\Entity\SensorReading $reading */
      $reading = $readings[$id];
      $metric = $reading->get('metric')->value;
      if (!isset($latest_by_metric[$metric])) {
        $latest_by_metric[$metric] = $reading;
      }
    }

    return $latest_by_metric;
  }

  /**
   * Formats the "latest reading" summary line for one metric.
   *
   * @param string $metric
   *   The metric machine name.
   * @param \Drupal\nanoprobe\Entity\SensorReading $reading
   *   The latest reading for that metric.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The formatted summary.
   */
  protected function formatLatestReadingSummary(string $metric, SensorReading $reading) {
    $label = SensorReading::METRIC_TYPES[$metric] ?? $metric;
    $value = rtrim(rtrim(number_format((float) $reading->get('value')->value, 2, '.', ''), '0'), '.');
    $recorded = (int) $reading->get('recorded')->value;

    return $this->t('@label: @value (@time ago)', [
      '@label' => $label,
      '@value' => $value,
      '@time' => $this->dateFormatter->formatTimeDiffSince($recorded),
    ]);
  }

  /**
   * Builds a trend chart for one device/metric, if there's enough data.
   *
   * Aggregates raw readings into daily min/max/avg **on read**, not from
   * a persisted rollup — Phase 1 pilot volume (per
   * [[0074-sensor-data-ingestion-architecture]] §5, a handful of devices
   * reporting every 15–60 minutes) keeps a TREND_WINDOW_DAYS query small
   * enough that this is genuinely simpler than building
   * [[0082-sensor-reading-retention-and-rollup]]'s persisted rollup
   * early just to serve this panel. Revisit if this ever shows up as
   * slow in practice.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to chart.
   * @param string $metric
   *   The metric to chart.
   *
   * @return array
   *   A render array, or an empty array if fewer than two distinct days
   *   of data exist in the window (a single point isn't a trend — the
   *   latest-reading summary above already shows it).
   */
  protected function buildTrendChart(SensorDevice $device, string $metric): array {
    $end = $this->time->getRequestTime();
    $start = $end - (self::TREND_WINDOW_DAYS * 86400);

    $storage = $this->entityTypeManager->getStorage('sensor_reading');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->condition('recorded', $start, '>=')
      ->condition('recorded', $end, '<=')
      ->sort('recorded', 'ASC')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $daily = [];
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

    return $this->renderTrendSvg($points, $metric);
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
   * @param string $metric
   *   The metric being charted, for the accessible label.
   *
   * @return array
   *   A render array.
   */
  protected function renderTrendSvg(array $points, string $metric): array {
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

    $metric_label = SensorReading::METRIC_TYPES[$metric] ?? $metric;

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['nanoprobe-sensor-trend']],
      '#attached' => ['library' => ['nanoprobe/sensor_trend']],
      'chart' => [
        '#type' => 'inline_template',
        '#template' => '<div class="nanoprobe-sensor-trend__frame"><svg viewBox="0 0 {{ svg_width }} {{ svg_height }}" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ label }}"><title>{{ label }}</title><polygon class="nanoprobe-sensor-trend__band" points="{{ band_points }}" /><polyline class="nanoprobe-sensor-trend__avg" points="{{ avg_points }}" fill="none" /><text class="nanoprobe-sensor-trend__date" x="{{ padding_x }}" y="{{ svg_height - 8 }}" font-size="11">{{ first_label }}</text><text class="nanoprobe-sensor-trend__date" x="{{ svg_width - padding_x }}" y="{{ svg_height - 8 }}" text-anchor="end" font-size="11">{{ last_label }}</text></svg></div>',
        '#context' => [
          'label' => $this->t('@metric trend, last @days days', [
            '@metric' => $metric_label,
            '@days' => self::TREND_WINDOW_DAYS,
          ]),
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
