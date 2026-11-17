<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\nanoprobe\Entity\SensorDevice;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Download Configuration action for a Sensor Device.
 *
 * Per docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §3, a device's plaintext token is never persisted — only its
 * `password_hash()` digest is — so there is no way to recover a
 * previously-generated plaintext for an existing, already-saved device.
 * The only way "download config" can ever produce a file with a real,
 * working token is to generate a fresh one right here, in the same
 * request that serves the file. **This form is therefore both the
 * "Download config" and the "Regenerate token" action task 0078
 * describes** — deliberately unified rather than built as two separate
 * actions, since a non-mutating "download" could never include a usable
 * token for a device that wasn't just created or just regenerated. The
 * form's own explanatory text makes the regeneration/invalidation
 * consequence explicit before the beekeeper submits.
 *
 * Implemented as a real Drupal form (POST, CSRF-protected by the Form
 * API) rather than a bare GET link, since this action mutates the
 * device's token — consistent with [[0018-csrf-and-safe-http-methods]]'s
 * "no state change via GET" principle, applied here exactly as it is
 * everywhere else in hivelog.
 */
class SensorDeviceConfigDownloadForm extends FormBase {

  /**
   * The device this form regenerates a token for and builds a config for.
   */
  protected SensorDevice $sensorDevice;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nanoprobe_sensor_device_config_download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SensorDevice $sensor_device = NULL): array {
    if ($sensor_device === NULL || !$sensor_device->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $this->sensorDevice = $sensor_device;

    $form['warning'] = [
      '#type' => 'container',
      '#markup' => '<p>' . $this->t('Downloading a new configuration regenerates this device\'s token, which <strong>immediately invalidates</strong> any token currently in use — including one already flashed onto a physical device. The device (or its receiver/bridge) will need to be re-provisioned with the file downloaded here.') . '</p>',
    ];

    $form['metrics'] = [
      '#type' => 'item',
      '#title' => $this->t('Metrics this device will report'),
      '#markup' => implode(', ', $sensor_device->getConfigMetrics()),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download Configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $token = $this->sensorDevice->generateToken();
    $this->sensorDevice->save();

    $descriptor = $this->sensorDevice->buildConfigDescriptor($token);
    $filename = 'sensor-device-' . $this->sensorDevice->id() . '-config.json';

    $response = new Response(
      json_encode($descriptor, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      Response::HTTP_OK,
      [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      ]
    );
    $form_state->setResponse($response);
  }

}
