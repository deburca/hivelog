<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Apiary delete forms.
 */
class ApiaryDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete apiary %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.apiary.canonical', ['apiary' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Apiary %name has been deleted.', [
      '%name' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl(new Url('entity.apiary.collection'));
  }

}
