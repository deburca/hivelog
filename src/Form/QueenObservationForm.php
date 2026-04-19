<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Queen Observation add/edit forms.
 */
class QueenObservationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Default observation_date to today for new observations so the user
    // can usually skip straight to the content.
    if ($this->entity->isNew() && $this->entity->get('observation_date')->isEmpty()) {
      $this->entity->set('observation_date', date('Y-m-d'));
    }
    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Queen observation has been recorded.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Queen observation has been updated.'));
    }

    // Redirect to the parent queen view when possible.
    $queen_id = $entity->get('queen')->target_id;
    if ($queen_id) {
      $form_state->setRedirect('entity.queen.canonical', ['queen' => $queen_id]);
    }
    else {
      $form_state->setRedirect('entity.queen.collection');
    }
  }

}
