<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Hive add/edit forms.
 */
class HiveForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Hive %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Hive %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the parent apiary view.
    $apiary_id = $entity->get('apiary')->target_id;
    if ($apiary_id) {
      $form_state->setRedirect('entity.apiary.canonical', ['apiary' => $apiary_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

}
