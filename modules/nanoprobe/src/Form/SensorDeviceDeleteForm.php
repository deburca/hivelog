<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\hivelog\Form\HivelogEntityDeleteForm;

/**
 * Form handler for Sensor Device delete forms.
 */
class SensorDeviceDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. The device (or its receiver/bridge) will immediately start getting 401 responses from the ingestion endpoint, and its past readings remain but are no longer added to.');
  }

}
