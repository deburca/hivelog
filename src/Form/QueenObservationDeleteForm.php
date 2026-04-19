<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Queen Observation delete forms.
 */
class QueenObservationDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this queen observation?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.queen_observation.canonical', [
      'queen_observation' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queen_id = $this->entity->get('queen')->target_id;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Queen observation has been deleted.'));

    if ($queen_id) {
      $form_state->setRedirectUrl(new Url('entity.queen.canonical', ['queen' => $queen_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.queen.collection'));
    }
  }

}
