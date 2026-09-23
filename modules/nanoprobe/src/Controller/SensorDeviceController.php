<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\nanoprobe\Form\SensorReadingFilterForm;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the Sensor Device canonical page.
 *
 * Deliberately minimal — a field summary plus the "Download
 * Configuration" action this module's task (0078) actually needs, not a
 * full management UI. Follows ADR-0004 (custom controllers over view
 * builders), matching every other hivelog entity's canonical page.
 */
class SensorDeviceController extends ControllerBase {

  /**
   * Builds the Sensor Device canonical page.
   *
   * The route's own `_permission` requirement is a flat own/any/admin
   * check (matching every other custom hivelog route); the real,
   * apiary-scoped check happens here, per ADR-0020's access-parity
   * requirement — mirroring how every other hivelog entity's canonical
   * page enforces its finer-grained access from inside the controller.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device to display.
   *
   * @return array
   *   A render array.
   */
  public function view(SensorDevice $sensor_device): array {
    if (!$sensor_device->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $rows = [
      [$this->t('Label'), $sensor_device->label()],
      [$this->t('Scope'), $sensor_device->get('scope')->value],
    ];
    if ($apiary = $sensor_device->get('apiary')->entity) {
      $rows[] = [$this->t('Apiary'), $apiary->toLink()];
    }
    if ($hive = $sensor_device->get('hive')->entity) {
      $rows[] = [$this->t('Hive'), $hive->toLink()];
    }
    if (!$sensor_device->get('device_type')->isEmpty()) {
      $rows[] = [$this->t('Device type'), $sensor_device->get('device_type')->value];
    }
    if (!$sensor_device->get('transport')->isEmpty()) {
      $rows[] = [$this->t('Transport'), $sensor_device->get('transport')->value];
    }
    $rows[] = [$this->t('Enabled'), $sensor_device->get('enabled')->value ? $this->t('Yes') : $this->t('No')];
    if (!$sensor_device->get('last_seen')->isEmpty()) {
      $rows[] = [
        $this->t('Last seen'),
        $this->dateFormatter()->format((int) $sensor_device->get('last_seen')->value),
      ];
    }
    else {
      $rows[] = [$this->t('Last seen'), $this->t('Never')];
    }

    $build['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Value')],
      '#attributes' => ['class' => ['hivelog-sensor-device-table']],
      '#attached' => ['library' => ['hivelog/tables']],
      '#rows' => $rows,
    ];

    if ($sensor_device->access('update')) {
      $build['config'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['nanoprobe-sensor-device-actions']],
        '#attached' => ['library' => ['hivelog/notices']],
        'description' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['hivelog-notice--warning']],
          'text' => [
            '#markup' => '<p>' . $this->t('Downloading the configuration regenerates this device\'s token, immediately invalidating any previously downloaded configuration — the device (or its receiver/bridge) will need to be re-provisioned with the newly downloaded file.') . '</p>',
          ],
        ],
        'download' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Download Configuration'),
            'url' => Url::fromRoute('nanoprobe.sensor_device.config', ['sensor_device' => $sensor_device->id()])->toString(),
            'variant' => 'danger',
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Builds the full-history sensor readings page (task 0110).
   *
   * Linked from the per-sensor stat tile `SensorPanelBuilder` contributes
   * to the Hive/Apiary canonical pages — unlike that panel's own
   * trend chart (capped at `SensorPanelBuilder::TREND_WINDOW_DAYS`),
   * this shows every metric's complete history, filterable by metric
   * and date range via `SensorReadingFilterForm`.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device to show readings for.
   *
   * @return array
   *   A render array.
   */
  public function readings(SensorDevice $sensor_device): array {
    if (!$sensor_device->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $build['filter'] = $this->formBuilder()->getForm(SensorReadingFilterForm::class, $sensor_device);

    [$start, $end, $range_label] = $this->extractReadingsDateRange($sensor_device);
    $metrics = $this->extractReadingsMetricFilter($sensor_device);

    $chart_builder = $this->trendChartBuilder();
    $chart_sections = [];
    foreach ($metrics as $metric) {
      $metric_label = SensorReading::METRIC_TYPES[$metric] ?? $metric;
      $chart_label = $this->t('@metric, @range', ['@metric' => $metric_label, '@range' => $range_label]);
      $chart = $chart_builder->buildChart($sensor_device, $metric, $start, $end, $chart_label);
      if (!empty($chart)) {
        $chart_sections['metric_' . $metric] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['nanoprobe-sensor-metric']],
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $metric_label,
          ],
          'chart' => $chart,
        ];
      }
    }

    if ($chart_sections) {
      $build['charts'] = $chart_sections;
    }
    else {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No readings with at least two distinct days of data match this filter yet.') . '</p>',
      ];
    }

    return $build;
  }

  /**
   * Extracts the date range for readings(), from filters or full history.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device being shown — its own `created` time is the default
   *   range start when no `date_from` filter is given.
   *
   * @return array
   *   `[int $start, int $end, \Drupal\Core\StringTranslation\TranslatableMarkup $range_label]`.
   */
  protected function extractReadingsDateRange(SensorDevice $sensor_device): array {
    $request = $this->getRequest();
    $date_from = $this->extractValidDate((string) $request->query->get('date_from', ''));
    $date_to = $this->extractValidDate((string) $request->query->get('date_to', ''));

    $start = $date_from !== '' ? (int) strtotime($date_from . ' 00:00:00') : (int) $sensor_device->get('created')->value;
    $end = $date_to !== '' ? (int) strtotime($date_to . ' 23:59:59') : $this->time()->getRequestTime();

    $range_label = $date_from !== '' || $date_to !== ''
      ? $this->t('@from to @to', [
        '@from' => $date_from !== '' ? $date_from : $this->dateFormatter()->format($start, 'custom', 'Y-m-d'),
        '@to' => $date_to !== '' ? $date_to : $this->dateFormatter()->format($end, 'custom', 'Y-m-d'),
      ])
      : $this->t('full history');

    return [$start, $end, $range_label];
  }

  /**
   * Validates a `date_from`/`date_to` query value as a plain `Y-m-d` string.
   *
   * @param string $value
   *   The raw query string value.
   *
   * @return string
   *   $value if it's a genuine `Y-m-d` date, otherwise an empty string —
   *   a malformed/hand-crafted query value falls back to the default
   *   range rather than being passed to strtotime() unchecked.
   */
  protected function extractValidDate(string $value): string {
    $value = trim($value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return '';
    }
    return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : '';
  }

  /**
   * Extracts which metrics readings() should chart.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device being shown.
   *
   * @return string[]
   *   The single filtered metric, if valid for this device — otherwise
   *   every metric the device is expected to report.
   */
  protected function extractReadingsMetricFilter(SensorDevice $sensor_device): array {
    $requested = trim((string) $this->getRequest()->query->get('metric', ''));
    $available = $sensor_device->getConfigMetrics();
    return ($requested !== '' && in_array($requested, $available, TRUE)) ? [$requested] : $available;
  }

  /**
   * Title callback for the readings() page.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device being shown.
   *
   * @return string
   *   The page title.
   */
  public function readingsTitle(SensorDevice $sensor_device): string {
    return (string) $this->t('Sensor Readings: @label', ['@label' => $sensor_device->label()]);
  }

  /**
   * The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The current request.
   */
  protected function getRequest() {
    return \Drupal::request();
  }

  /**
   * The sensor trend chart builder service.
   *
   * @return \Drupal\nanoprobe\SensorTrendChartBuilder
   *   The trend chart builder.
   */
  protected function trendChartBuilder() {
    return \Drupal::service('nanoprobe.sensor_trend_chart_builder');
  }

  /**
   * The time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  protected function time() {
    return \Drupal::time();
  }

  /**
   * Provides the add form pre-filled for a hive-scoped device.
   *
   * Mirrors `QueenController::addForm()`/`CalendarActionController::
   * addForm()`'s established pattern for adding a child entity from its
   * parent's canonical page — task 0106.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive to pre-fill.
   *
   * @return array
   *   The add form's render array.
   */
  public function addFormForHive(Hive $hive): array {
    $device = $this->entityTypeManager()->getStorage('sensor_device')->create([
      'apiary' => $hive->get('apiary')->target_id,
      'hive' => $hive->id(),
      'scope' => 'hive',
    ]);
    return $this->entityFormBuilder()->getForm($device, 'add');
  }

  /**
   * Provides the add form pre-filled for an apiary-scoped device.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary to pre-fill.
   *
   * @return array
   *   The add form's render array.
   */
  public function addFormForApiary(Apiary $apiary): array {
    $device = $this->entityTypeManager()->getStorage('sensor_device')->create([
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
    ]);
    return $this->entityFormBuilder()->getForm($device, 'add');
  }

  /**
   * Title callback for the canonical page.
   *
   * @param \Drupal\nanoprobe\Entity\SensorDevice $sensor_device
   *   The device being displayed.
   *
   * @return string
   *   The page title.
   */
  public function title(SensorDevice $sensor_device): string {
    return $sensor_device->label();
  }

  /**
   * The date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  protected function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
