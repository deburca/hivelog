<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Queen add/edit forms.
 */
class QueenForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Queen %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Queen %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the associated hive when the queen is attached to one;
    // otherwise fall back to the global queen collection.
    $hive_id = $entity->get('hive')->target_id;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.queen.collection');
    }
  }

}
