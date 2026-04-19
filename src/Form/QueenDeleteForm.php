<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Queen delete forms.
 */
class QueenDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete queen %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.queen.canonical', ['queen' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $hive_id = $this->entity->get('hive')->target_id;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Queen %name has been deleted.', [
      '%name' => $this->entity->label(),
    ]));

    if ($hive_id) {
      $form_state->setRedirectUrl(new Url('entity.hive.canonical', ['hive' => $hive_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.queen.collection'));
    }
  }

}
