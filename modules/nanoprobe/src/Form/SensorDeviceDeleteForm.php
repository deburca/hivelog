<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Sensor Device delete forms.
 */
class SensorDeviceDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete sensor device %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. The device (or its receiver/bridge) will immediately start getting 401 responses from the ingestion endpoint, and its past readings remain but are no longer added to.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $this->entity->label();
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Sensor device %label has been deleted.', [
      '%label' => $label,
    ]));
    $form_state->setRedirect('entity.sensor_device.collection');
  }

}
