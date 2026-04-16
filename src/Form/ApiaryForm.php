<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Apiary add/edit forms.
 */
class ApiaryForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Apiary %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Apiary %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    $form_state->setRedirect('entity.apiary.canonical', ['apiary' => $entity->id()]);
  }

}
