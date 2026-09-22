<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\nanoprobe\Entity\SensorDevice;
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
        'description' => [
          '#markup' => '<p>' . $this->t('Downloading the configuration regenerates this device\'s token, immediately invalidating any previously downloaded configuration — the device (or its receiver/bridge) will need to be re-provisioned with the newly downloaded file.') . '</p>',
        ],
        'download' => [
          '#type' => 'link',
          '#title' => $this->t('Download Configuration'),
          '#url' => Url::fromRoute('nanoprobe.sensor_device.config', ['sensor_device' => $sensor_device->id()]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ];
    }

    return $build;
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
