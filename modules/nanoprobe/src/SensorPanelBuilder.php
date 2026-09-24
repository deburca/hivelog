<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
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

  /**
   * How stale a device's latest reading can be before its stat tile flags it.
   *
   * Mirrors `SensorAlertCollector::OFFLINE_THRESHOLD_SECONDS` (24 hours)
   * — the same "is this device actually still reporting" question, just
   * surfaced on the tile's sublabel instead of the dashboard's "Needs
   * attention" queue.
   */
  protected const STALE_THRESHOLD_SECONDS = 86400;

  /**
   * Compact unit suffix per metric, for a stat tile's headline value.
   */
  protected const METRIC_UNITS = [
    'weight_kg' => 'kg',
    'temp_internal_c' => '°C',
    'temp_external_c' => '°C',
    'humidity_internal_pct' => '%',
    'humidity_external_pct' => '%',
    'battery_voltage' => 'V',
    'signal_rssi' => 'dBm',
  ];

  /**
   * Preferred "vital stat" metric per `device_type`, for a stat tile.
   *
   * Device types with no entry here (acoustic, entrance_counter, gps —
   * per `SensorDevice::DEVICE_TYPE_METRICS` they currently report only
   * `battery_voltage`/`signal_rssi`) fall back in
   * `buildDeviceStatTile()` to whichever metric the device most
   * recently reported.
   */
  protected const PRIMARY_METRIC_BY_DEVICE_TYPE = [
    'weight' => 'weight_kg',
    'temperature_humidity' => 'temp_internal_c',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected SensorTrendChartBuilder $trendChartBuilder,
    protected RendererInterface $renderer,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds the Sensors panel for the Hive's dedicated Insights page.
   *
   * Lived on the Hive canonical page itself until task 0111 moved it
   * (alongside nexus's AI Insight panel) to a separate page, once real
   * content — a 30-day trend chart per device/metric — made the
   * canonical page too busy for its own "at a glance" purpose; the
   * per-device stat tiles `buildHiveStatTiles()` builds are what stayed
   * behind, each linking straight to its own device's full-history page
   * rather than back to this panel.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if
   *   there's neither accessible sensor data nor an "Add Sensor" link
   *   to offer the current user.
   */
  public function buildHivePanel(Hive $hive): array {
    $devices = $this->loadAccessibleDevices([
      'hive' => $hive->id(),
      'scope' => 'hive',
      'enabled' => 1,
    ]);
    $add_url = Url::fromRoute('nanoprobe.sensor_device.add_for_hive', ['hive' => $hive->id()]);
    return $this->buildPanel($devices, $add_url);
  }

  /**
   * Builds the Sensors panel for an Apiary canonical page.
   *
   * Apiary-scoped devices only — hive-scoped devices attached to hives
   * within this apiary appear on their own hive's page instead, not
   * rolled up here. No "Add Sensor" action and no "Sensors" title row
   * here (user request, 2026-09-23) — `nanoprobe.sensor_device.add_for_apiary`
   * itself is unchanged and still reachable directly, just not linked
   * from this panel.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if
   *   there's no accessible sensor data to show.
   */
  public function buildApiaryPanel(Apiary $apiary): array {
    $devices = $this->loadAccessibleDevices([
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'enabled' => 1,
    ]);
    return $this->buildPanel($devices, NULL, show_heading: FALSE);
  }

  /**
   * Builds one stat tile descriptor per hive-scoped sensor device.
   *
   * Backs hook_hivelog_hive_stat_tiles() (nanoprobe.module) — task 0110.
   * Each tile links to that device's full-history readings page
   * (`entity.sensor_device.readings`), not back to this same Sensors
   * panel — unlike nexus's AI Insight tile, there genuinely is more to
   * show than what already fits on this page (the panel's own trend
   * chart is capped at TREND_WINDOW_DAYS).
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   Tile descriptors keyed `nanoprobe_sensor_<device id>`, one per
   *   device with an accessible reading to summarise. A device with no
   *   reading yet contributes no tile.
   */
  public function buildHiveStatTiles(Hive $hive): array {
    $devices = $this->loadAccessibleDevices([
      'hive' => $hive->id(),
      'scope' => 'hive',
      'enabled' => 1,
    ]);
    return $this->buildStatTiles($devices);
  }

  /**
   * Builds one stat tile descriptor per apiary-scoped sensor device.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   Tile descriptors, shaped exactly like buildHiveStatTiles()'s own
   *   return value.
   */
  public function buildApiaryStatTiles(Apiary $apiary): array {
    $devices = $this->loadAccessibleDevices([
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'enabled' => 1,
    ]);
    return $this->buildStatTiles($devices);
  }

  /**
   * Assembles tile descriptors from a set of accessible devices.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice[] $devices
   *   Devices to summarise, already access-filtered.
   *
   * @return array
   *   Tile descriptors keyed `nanoprobe_sensor_<device id>`.
   */
  protected function buildStatTiles(array $devices): array {
    $tiles = [];
    foreach ($devices as $device) {
      $tile = $this->buildDeviceStatTile($device);
      if ($tile) {
        $tiles['nanoprobe_sensor_' . $device->id()] = $tile;
      }
    }
    return $tiles;
  }

  /**
   * Builds one device's stat tile descriptor, or NULL if it has no data.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to summarise.
   *
   * @return array|null
   *   A tile descriptor (see hook_hivelog_hive_stat_tiles()'s own
   *   docblock), or NULL if the device has no accessible reading yet.
   */
  protected function buildDeviceStatTile(SensorDevice $device): ?array {
    $latest_by_metric = array_filter(
      $this->getLatestReadingPerMetric($device),
      fn(SensorReading $reading) => $reading->access('view', $this->currentUser)
    );
    if (!$latest_by_metric) {
      return NULL;
    }

    $preferred = self::PRIMARY_METRIC_BY_DEVICE_TYPE[$device->get('device_type')->value] ?? NULL;
    if ($preferred !== NULL && isset($latest_by_metric[$preferred])) {
      $metric = $preferred;
    }
    else {
      // Prefer a non-diagnostic metric if the device reported one; only
      // fall back to battery/signal if that's genuinely all there is.
      $non_diagnostic = array_diff_key($latest_by_metric, array_flip(['battery_voltage', 'signal_rssi']));
      $metric = array_key_first($non_diagnostic) ?? array_key_first($latest_by_metric);
    }

    $reading = $latest_by_metric[$metric];
    $value = rtrim(rtrim(number_format((float) $reading->get('value')->value, 2, '.', ''), '0'), '.');
    $unit = self::METRIC_UNITS[$metric] ?? '';

    $recorded = (int) $reading->get('recorded')->value;
    $is_stale = ($this->time->getRequestTime() - $recorded) >= self::STALE_THRESHOLD_SECONDS;

    return [
      'value' => $unit ? "$value $unit" : $value,
      'label' => $device->label(),
      'url' => Url::fromRoute('entity.sensor_device.readings', ['sensor_device' => $device->id()]),
      'sublabel' => $is_stale
        ? $this->t('No data for @time', ['@time' => $this->dateFormatter->formatTimeDiffSince($recorded)])
        : $this->t('Updated @time ago', ['@time' => $this->dateFormatter->formatTimeDiffSince($recorded)]),
      'sublabel_variant' => $is_stale ? 'warning' : 'default',
      'weight' => 10,
    ];
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
   * @param \Drupal\Core\Url|null $add_url
   *   The hive-contextual "Add Sensor" route (task 0106) — shown only
   *   when the current user actually has access to it, so this never
   *   links a beekeeper into a 403. NULL suppresses the action entirely
   *   (the Apiary canonical page's own panel doesn't offer it — see
   *   buildApiaryPanel()'s own docblock).
   * @param bool $show_heading
   *   Whether to render the "Sensors" title row at all. FALSE for the
   *   Apiary canonical page's own panel (user request, 2026-09-23) —
   *   with the Add Sensor action already gone there, a title-only row
   *   had nothing left to justify itself.
   *
   * @return array
   *   A render array keyed `nanoprobe_sensors`, or an empty array if
   *   there's neither an accessible reading to show nor an "Add Sensor"
   *   link to offer (an attached device that has never reported yet
   *   contributes nothing on its own).
   */
  protected function buildPanel(array $devices, ?Url $add_url, bool $show_heading = TRUE): array {
    $device_sections = [];
    foreach ($devices as $device) {
      $section = $this->buildDeviceSection($device);
      if (!empty($section)) {
        $device_sections['device_' . $device->id()] = $section;
      }
    }

    $can_add = $add_url && $add_url->access($this->currentUser);
    if (empty($device_sections) && !$can_add) {
      return [];
    }

    $panel = [
      '#type' => 'container',
      // 'sensors' is the anchor task 0141's delete-dependency registry
      // (ADR-0103 row #7, apiary → sensor_device, BLOCK) links to from
      // the apiary delete-blocked page — present even when $show_heading
      // is FALSE, since the apiary panel has no heading of its own to
      // anchor to (see buildApiaryPanel()'s docblock).
      '#attributes' => ['class' => ['nanoprobe-sensors-panel'], 'id' => 'sensors'],
      '#weight' => 7.5,
    ];

    if ($show_heading) {
      $heading = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Sensors'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
      ];
      if ($can_add) {
        $heading['add'] = [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Sensor'),
            'url' => $add_url->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ];
      }
      $panel['heading'] = $heading;
    }

    if (empty($device_sections)) {
      $device_sections['empty'] = [
        '#markup' => '<p>' . $this->t('No sensors are registered here yet.') . '</p>',
      ];
    }

    return [
      'nanoprobe_sensors' => $panel + $device_sections,
    ];
  }

  /**
   * Builds one device's section: a metric tab per reading.
   *
   * Each tab shows that metric's current value, trend chart, and
   * last-updated time. Covers only the metrics the device has actually
   * reported. Previously
   * a table of latest values followed by a separate list of trend
   * charts (task 0111); a follow-up request the same day found that
   * split hard to scan once both existed, so both live together per
   * metric behind `nanoprobe:metric-tabs` (a CSS-only vertical tab
   * strip — no JS anywhere in HiveLog) instead.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to build a section for.
   *
   * @return array
   *   A render array, or an empty array if the device has no accessible
   *   reading at all yet.
   */
  protected function buildDeviceSection(SensorDevice $device): array {
    $latest_by_metric = array_filter(
      $this->getLatestReadingPerMetric($device),
      fn(SensorReading $reading) => $reading->access('view', $this->currentUser)
    );
    if (!$latest_by_metric) {
      return [];
    }

    $tabs = [];
    foreach ($latest_by_metric as $metric => $reading) {
      $label = SensorReading::METRIC_TYPES[$metric] ?? $metric;
      $value = rtrim(rtrim(number_format((float) $reading->get('value')->value, 2, '.', ''), '0'), '.');
      $recorded = (int) $reading->get('recorded')->value;

      $chart = $this->buildTrendChart($device, $metric);
      $chart_html = !empty($chart)
        ? $this->renderer->renderInIsolation($chart)
        : '<p class="nanoprobe-metric-tabs__no-chart">' . $this->t('Not enough data yet for a trend chart.') . '</p>';

      $tabs[] = [
        'id' => $metric,
        'label' => (string) $label,
        'value' => $value,
        'updated' => (string) $this->t('Updated @time ago', ['@time' => $this->dateFormatter->formatTimeDiffSince($recorded)]),
        'chart' => (string) $chart_html,
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['nanoprobe-sensor-device']],
      // The chart markup above was rendered via renderInIsolation(), which
      // deliberately discards its own #attached (it's meant for content
      // rendered outside the current render process) — so the chart's own
      // library attachment never bubbles up on its own. Attach it here
      // instead, since this section renders a chart's markup whenever it
      // renders at all.
      '#attached' => ['library' => ['nanoprobe/sensor_trend']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $device->label(),
      ],
      'tabs' => [
        '#type' => 'component',
        '#component' => 'nanoprobe:metric-tabs',
        '#props' => [
          'group' => (string) $device->id(),
          'tabs' => $tabs,
        ],
      ],
    ];
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
   * Builds a trend chart for one device/metric, if there's enough data.
   *
   * Thin wrapper around `SensorTrendChartBuilder::buildChart()`
   * (extracted from here in task 0110, which reuses the same charting
   * code for a full-history page): this class only knows about "the
   * last N trailing days", the shared builder doesn't need to.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $device
   *   The device to chart.
   * @param string $metric
   *   The metric to chart.
   * @param int|null $days
   *   How many trailing days to cover. Defaults to TREND_WINDOW_DAYS.
   *
   * @return array
   *   A render array, or an empty array if fewer than two distinct days
   *   of data exist in the window (a single point isn't a trend — the
   *   latest-reading summary above already shows it).
   */
  protected function buildTrendChart(SensorDevice $device, string $metric, ?int $days = NULL): array {
    $days ??= self::TREND_WINDOW_DAYS;
    $end = $this->time->getRequestTime();
    $start = $end - ($days * 86400);

    $metric_label = SensorReading::METRIC_TYPES[$metric] ?? $metric;
    $chart_label = $this->t('@metric trend, last @days days', [
      '@metric' => $metric_label,
      '@days' => $days,
    ]);

    return $this->trendChartBuilder->buildChart($device, $metric, $start, $end, $chart_label);
  }

}
